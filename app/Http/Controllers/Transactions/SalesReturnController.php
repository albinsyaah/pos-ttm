<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesReturnController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.sales-returns.view', only: ['index']),
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
                'return_number' => $data['return_number'],
                'return_date' => $data['return_date'],
                'total_amount' => $data['total_amount'],
                'sale_id' => $data['sale_id'],
            ]);

            $salesReturn->salesReturnDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.sales-returns.index')->with('success', 'Sales return added successfully.');
    }

    public function update(Request $request, SalesReturn $salesReturn): RedirectResponse
    {
        $data = $this->validateSalesReturn($request, $salesReturn->id);

        DB::transaction(function () use ($data, $salesReturn) {
            $salesReturn->update([
                'return_number' => $data['return_number'],
                'return_date' => $data['return_date'],
                'total_amount' => $data['total_amount'],
                'sale_id' => $data['sale_id'],
            ]);

            $salesReturn->salesReturnDetails()->delete();
            $salesReturn->salesReturnDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.sales-returns.index')->with('success', 'Sales return updated successfully.');
    }

    public function destroy(SalesReturn $salesReturn): RedirectResponse
    {
        DB::transaction(function () use ($salesReturn) {
            $salesReturn->salesReturnDetails()->delete();
            $salesReturn->delete();
        });

        return redirect()->route('transactions.sales-returns.index')->with('success', 'Sales return deleted successfully.');
    }

    protected function validateSalesReturn(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'return_number' => [
                'required', 'string', 'max:100',
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

        return $data;
    }
}
