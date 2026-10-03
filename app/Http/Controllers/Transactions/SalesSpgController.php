<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Concerns\SyncsSaleStock;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesSpgController extends Controller implements HasMiddleware
{
    use SyncsSaleStock;

    /**
     * Sales SPG (sales promotion girl / booth sales) transactions. They
     * share the 'sales' table with the regular Sales and Point of Sale
     * pages, tagged with this source (see
     * App\Http\Controllers\Transactions\SaleController::SOURCE and
     * App\Http\Controllers\Transactions\PointOfSaleController::SOURCE).
     */
    public const SOURCE = 'spg';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.sales-spg.view', only: ['index']),
            new Middleware('permission:transactions.sales-spg.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $salesSpg = Sale::query()
            ->where('source', self::SOURCE)
            ->with(['customer', 'warehouse', 'salesman', 'saleDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('sale_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.sales-spg.index', [
            'salesSpg' => $salesSpg,
            'search' => $search,
            'customers' => Customer::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'salesmen' => Employee::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSalesSpg($request);

        DB::transaction(function () use ($data) {
            $sale = Sale::create([
                'invoice_number' => $data['invoice_number'] ?? null,
                'sale_date' => $data['sale_date'],
                'total_amount' => $data['total_amount'],
                'source' => self::SOURCE,
                'sales_order_id' => null,
                'customer_id' => $data['customer_id'],
                'salesman_id' => $data['salesman_id'],
                'warehouse_id' => $data['warehouse_id'],
            ]);

            $sale->saleDetails()->createMany($data['items']);

            // Take the items out of the chosen warehouse; refused (and rolled
            // back) if a warehouse is short. Same product on two lines is added up.
            $this->syncSaleStock($sale, $data['items']);
        });

        return redirect()->route('transactions.sales-spg.index')->with('success', __('Sales SPG transaction added successfully.'));
    }

    public function update(Request $request, Sale $salesSpg): RedirectResponse
    {
        $data = $this->validateSalesSpg($request, $salesSpg->id);

        DB::transaction(function () use ($data, $salesSpg) {
            $salesSpg->update([
                'invoice_number' => $data['invoice_number'] ?? null,
                'sale_date' => $data['sale_date'],
                'total_amount' => $data['total_amount'],
                'customer_id' => $data['customer_id'],
                'salesman_id' => $data['salesman_id'],
                'warehouse_id' => $data['warehouse_id'],
            ]);

            $salesSpg->saleDetails()->delete();
            $salesSpg->saleDetails()->createMany($data['items']);

            // Writes only the difference from what this sale already took out
            // of stock; refused (and rolled back) if a warehouse is short.
            $this->syncSaleStock($salesSpg, $data['items']);
        });

        return redirect()->route('transactions.sales-spg.index')->with('success', __('Sales SPG transaction updated successfully.'));
    }

    public function destroy(Sale $salesSpg): RedirectResponse
    {
        if ($salesSpg->salesReturns()->exists()) {
            return back()->with('error', __('This transaction already has returns recorded and cannot be deleted.'));
        }

        DB::transaction(function () use ($salesSpg) {
            // Put the stock this sale took out back into its warehouse.
            $this->syncSaleStock($salesSpg);

            $salesSpg->saleDetails()->delete();
            $salesSpg->delete();
        });

        return redirect()->route('transactions.sales-spg.index')->with('success', __('Sales SPG transaction deleted successfully.'));
    }

    protected function validateSalesSpg(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'invoice_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('sales', 'invoice_number')->ignore($ignoreId),
            ],
            'sale_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'salesman_id' => ['nullable', 'exists:employees,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        $data['items'] = array_map(fn ($item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'price' => $item['price'],
        ], $data['items']);

        $data['total_amount'] = collect($data['items'])->sum(fn ($item) => $item['qty'] * $item['price']);

        $data['customer_id'] = $data['customer_id'] ?? null;
        $data['salesman_id'] = $data['salesman_id'] ?? null;

        return $data;
    }
}
