<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Concerns\SyncsSaleStock;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SaleController extends Controller implements HasMiddleware
{
    use SyncsSaleStock;

    /**
     * The 'sales' page manages regular (non point-of-sale) invoiced sales.
     * Records created here are always tagged with this source, keeping them
     * separate from the Point of Sale pages, which share the same table.
     */
    public const SOURCE = 'sales';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.sales.view', only: ['index']),
            new Middleware('permission:transactions.sales.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $sales = Sale::query()
            ->where('source', self::SOURCE)
            ->with(['customer', 'warehouse', 'salesman', 'salesOrder', 'saleDetails.product'])
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

        return view('transactions.sales.index', [
            'sales' => $sales,
            'search' => $search,
            'customers' => Customer::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'salesmen' => Employee::orderBy('name')->get(),
            'salesOrders' => SalesOrder::orderByDesc('order_date')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSale($request);

        DB::transaction(function () use ($data) {
            $sale = Sale::create([
                'invoice_number' => $data['invoice_number'],
                'sale_date' => $data['sale_date'],
                'total_amount' => $data['total_amount'],
                'source' => self::SOURCE,
                'sales_order_id' => $data['sales_order_id'],
                'customer_id' => $data['customer_id'],
                'salesman_id' => $data['salesman_id'],
                'warehouse_id' => $data['warehouse_id'],
                'driver_name' => $data['driver_name'],
            ]);

            $sale->saleDetails()->createMany($data['items']);

            // Take the items out of the chosen warehouse; refused (and rolled
            // back) if a warehouse is short. Same product on two lines is added up.
            $this->syncSaleStock($sale, $data['items']);
        });

        return redirect()->route('transactions.sales.index')->with('success', 'Sale added successfully.');
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        $data = $this->validateSale($request, $sale->id);

        DB::transaction(function () use ($data, $sale) {
            $sale->update([
                'invoice_number' => $data['invoice_number'],
                'sale_date' => $data['sale_date'],
                'total_amount' => $data['total_amount'],
                'sales_order_id' => $data['sales_order_id'],
                'customer_id' => $data['customer_id'],
                'salesman_id' => $data['salesman_id'],
                'warehouse_id' => $data['warehouse_id'],
                'driver_name' => $data['driver_name'],
            ]);

            $sale->saleDetails()->delete();
            $sale->saleDetails()->createMany($data['items']);

            // Writes only the difference from what this sale already took out
            // of stock; refused (and rolled back) if a warehouse is short.
            $this->syncSaleStock($sale, $data['items']);
        });

        return redirect()->route('transactions.sales.index')->with('success', 'Sale updated successfully.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        if ($sale->salesReturns()->exists()) {
            return back()->with('error', 'This sale already has returns recorded and cannot be deleted.');
        }

        DB::transaction(function () use ($sale) {
            // Put the stock this sale took out back into its warehouse.
            $this->syncSaleStock($sale);

            $sale->saleDetails()->delete();
            $sale->delete();
        });

        return redirect()->route('transactions.sales.index')->with('success', 'Sale deleted successfully.');
    }

    protected function validateSale(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'invoice_number' => [
                'required', 'string', 'max:100',
                Rule::unique('sales', 'invoice_number')->ignore($ignoreId),
            ],
            'sale_date' => ['required', 'date'],
            'sales_order_id' => ['nullable', 'exists:sales_orders,id'],
            'customer_id' => ['required', 'exists:customers,id'],
            'salesman_id' => ['nullable', 'exists:employees,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            // Printed on the delivery note (surat jalan).
            'driver_name' => ['nullable', 'string', 'max:100'],
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

        // Total is derived from the line items rather than trusted from the
        // client, so it always matches what was actually submitted.
        $data['total_amount'] = collect($data['items'])->sum(fn ($item) => $item['qty'] * $item['price']);

        $data['sales_order_id'] = $data['sales_order_id'] ?? null;
        $data['salesman_id'] = $data['salesman_id'] ?? null;
        $data['driver_name'] = filled($data['driver_name'] ?? null) ? trim($data['driver_name']) : null;

        return $data;
    }
}
