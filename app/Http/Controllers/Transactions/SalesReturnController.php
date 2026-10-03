<?php

namespace App\Http\Controllers\Transactions;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Concerns\ChecksReturnLimits;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\SalesReturnDetail;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesReturnController extends Controller implements HasMiddleware
{
    use ChecksReturnLimits;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.sales-returns.view', only: ['index', 'lines']),
            new Middleware('permission:transactions.sales-returns.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $salesReturns = SalesReturn::query()
            ->with(['sale.customer', 'salesReturnDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('sale', function ($saleQuery) use ($search) {
                            $saleQuery->where('invoice_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('return_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.sales-returns.index', [
            'salesReturns' => $salesReturns,
            'search' => $search,
            'sales' => Sale::with('customer')->orderByDesc('sale_date')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSalesReturn($request);

        DB::transaction(function () use ($data) {
            $salesReturn = SalesReturn::create([
                'return_number' => $data['return_number'] ?? null,
                'return_date' => $data['return_date'],
                'total_amount' => $data['total_amount'],
                'sale_id' => $data['sale_id'],
            ]);

            $salesReturn->salesReturnDetails()->createMany($data['items']);

            // Goods come back from the customer: put them into the sale's warehouse.
            $this->syncStock($salesReturn, $data);
        });

        return redirect()->route('transactions.sales-returns.index')->with('success', __('Sales return added successfully.'));
    }

    public function update(Request $request, SalesReturn $salesReturn): RedirectResponse
    {
        $data = $this->validateSalesReturn($request, $salesReturn->id);

        DB::transaction(function () use ($data, $salesReturn) {
            $salesReturn->update([
                'return_number' => $data['return_number'] ?? null,
                'return_date' => $data['return_date'],
                'total_amount' => $data['total_amount'],
                'sale_id' => $data['sale_id'],
            ]);

            $salesReturn->salesReturnDetails()->delete();
            $salesReturn->salesReturnDetails()->createMany($data['items']);

            $this->syncStock($salesReturn, $data);
        });

        return redirect()->route('transactions.sales-returns.index')->with('success', __('Sales return updated successfully.'));
    }

    public function destroy(SalesReturn $salesReturn): RedirectResponse
    {
        try {
            DB::transaction(function () use ($salesReturn) {
                // The goods are no longer returned: take them back out of stock
                // (refused if they have already been sold again).
                app(StockService::class)->sync($salesReturn, null, [], 'in', $salesReturn->return_number);

                $salesReturn->salesReturnDetails()->delete();
                $salesReturn->delete();
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', implode(' ', $e->shortages));
        }

        return redirect()->route('transactions.sales-returns.index')->with('success', __('Sales return deleted successfully.'));
    }

    /**
     * The products on one sale, for the return form: how many were sold, how many
     * other returns already took back, how many may still be returned. When a
     * return is being edited its own id is passed as ?exclude so its lines count
     * as still available.
     */
    public function lines(Request $request, Sale $sale): JsonResponse
    {
        $exclude = (int) $request->query('exclude', 0) ?: null;

        return response()->json([
            'source' => $sale->invoice_number,
            'returnable' => true,
            'message' => null,
            'lines' => $this->returnLines($this->sumByProduct($sale->saleDetails()), $this->returnedOn($sale, $exclude)),
        ]);
    }

    /** product_id => qty already returned on this sale by returns other than $ignoreId. */
    protected function returnedOn(Sale $sale, ?int $ignoreId = null): array
    {
        return $this->sumByProduct(
            SalesReturnDetail::query()->whereHas('salesReturn', function ($q) use ($sale, $ignoreId) {
                $q->where('sale_id', $sale->id)
                    ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));
            })
        );
    }

    protected function validateSalesReturn(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'return_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('sales_returns', 'return_number')->ignore($ignoreId),
            ],
            'return_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'sale_id' => ['required', 'exists:sales,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        $data['items'] = array_map(fn ($item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
        ], $data['items']);

        $sale = Sale::findOrFail($data['sale_id']);

        $this->assertWithinReturnLimits(
            $sale->invoice_number,
            $data['items'],
            $this->sumByProduct($sale->saleDetails()),
            $this->returnedOn($sale, $ignoreId)
        );

        // Stock goes back into the warehouse the sale was taken from.
        $data['warehouse_id'] = $sale->warehouse_id;

        return $data;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $lines
     * @return array<int, int>  product_id => total qty
     */
    protected function sumByProduct($lines): array
    {
        return $lines->selectRaw('product_id, sum(qty) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();
    }

    protected function syncStock(SalesReturn $salesReturn, array $data): void
    {
        app(StockService::class)->sync(
            $salesReturn,
            (int) $data['warehouse_id'],
            $data['items'],
            'in',
            $salesReturn->return_number,
            $data['return_date']
        );
    }
}
