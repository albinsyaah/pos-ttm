<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Concerns\ChecksReturnLimits;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseReturnController extends Controller implements HasMiddleware
{
    use ChecksReturnLimits;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.purchase-returns.view', only: ['index', 'lines']),
            new Middleware('permission:transactions.purchase-returns.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $purchaseReturns = PurchaseReturn::query()
            ->with(['purchase.supplier', 'purchaseReturnDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('return_number', 'like', "%{$search}%")
                        ->orWhereHas('purchase', function ($purchaseQuery) use ($search) {
                            $purchaseQuery->where('invoice_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('return_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.purchase-returns.index', [
            'purchaseReturns' => $purchaseReturns,
            'search' => $search,
            'purchases' => Purchase::with('supplier')->orderByDesc('purchase_date')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePurchaseReturn($request);

        DB::transaction(function () use ($data) {
            $purchaseReturn = PurchaseReturn::create([
                'return_number' => $data['return_number'] ?? null,
                'return_date' => $data['return_date'],
                'total_amount' => $data['total_amount'],
                'purchase_id' => $data['purchase_id'],
            ]);

            $purchaseReturn->purchaseReturnDetails()->createMany($data['items']);

            // Goods go back to the supplier: take them out of the purchase's
            // warehouse. Refused (and rolled back) if that warehouse is short.
            $this->syncStock($purchaseReturn, $data);
        });

        return redirect()->route('transactions.purchase-returns.index')->with('success', __('Purchase return added successfully.'));
    }

    public function update(Request $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $data = $this->validatePurchaseReturn($request, $purchaseReturn->id);

        DB::transaction(function () use ($data, $purchaseReturn) {
            $purchaseReturn->update([
                'return_number' => $data['return_number'] ?? null,
                'return_date' => $data['return_date'],
                'total_amount' => $data['total_amount'],
                'purchase_id' => $data['purchase_id'],
            ]);

            $purchaseReturn->purchaseReturnDetails()->delete();
            $purchaseReturn->purchaseReturnDetails()->createMany($data['items']);

            $this->syncStock($purchaseReturn, $data);
        });

        return redirect()->route('transactions.purchase-returns.index')->with('success', __('Purchase return updated successfully.'));
    }

    public function destroy(PurchaseReturn $purchaseReturn): RedirectResponse
    {
        DB::transaction(function () use ($purchaseReturn) {
            // The goods are no longer returned: put them back into stock.
            app(StockService::class)->sync($purchaseReturn, null, [], 'out', $purchaseReturn->return_number);

            $purchaseReturn->purchaseReturnDetails()->delete();
            $purchaseReturn->delete();
        });

        return redirect()->route('transactions.purchase-returns.index')->with('success', __('Purchase return deleted successfully.'));
    }

    /**
     * The products on one purchase, for the return form (see SalesReturnController::lines).
     * A purchase that has not been received yet has nothing to return.
     */
    public function lines(Request $request, Purchase $purchase): JsonResponse
    {
        $exclude = (int) $request->query('exclude', 0) ?: null;

        if ($purchase->status !== 'received') {
            return response()->json([
                'source' => $purchase->invoice_number,
                'returnable' => false,
                'message' => __('stock.return_not_received', ['source' => $purchase->invoice_number]),
                'lines' => [],
            ]);
        }

        return response()->json([
            'source' => $purchase->invoice_number,
            'returnable' => true,
            'message' => null,
            'lines' => $this->returnLines($this->sumByProduct($purchase->purchaseDetails()), $this->returnedOn($purchase, $exclude)),
        ]);
    }

    /** product_id => qty already returned on this purchase by returns other than $ignoreId. */
    protected function returnedOn(Purchase $purchase, ?int $ignoreId = null): array
    {
        return $this->sumByProduct(
            PurchaseReturnDetail::query()->whereHas('purchaseReturn', function ($q) use ($purchase, $ignoreId) {
                $q->where('purchase_id', $purchase->id)
                    ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));
            })
        );
    }

    protected function validatePurchaseReturn(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'return_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('purchase_returns', 'return_number')->ignore($ignoreId),
            ],
            'return_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'purchase_id' => ['required', 'exists:purchases,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $data['items'] = array_map(fn ($item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'reason' => $item['reason'] ?? null,
        ], $data['items']);

        $purchase = Purchase::findOrFail($data['purchase_id']);

        // Only goods that actually arrived can go back to the supplier.
        if ($purchase->status !== 'received') {
            throw ValidationException::withMessages([
                'purchase_id' => __('stock.return_not_received', ['source' => $purchase->invoice_number]),
            ]);
        }

        $this->assertWithinReturnLimits(
            $purchase->invoice_number,
            $data['items'],
            $this->sumByProduct($purchase->purchaseDetails()),
            $this->returnedOn($purchase, $ignoreId)
        );

        // Stock leaves the warehouse the purchase was received into.
        $data['warehouse_id'] = $purchase->warehouse_id;

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

    protected function syncStock(PurchaseReturn $purchaseReturn, array $data): void
    {
        app(StockService::class)->sync(
            $purchaseReturn,
            (int) $data['warehouse_id'],
            $data['items'],
            'out',
            $purchaseReturn->return_number,
            $data['return_date']
        );
    }
}
