<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SalesOrderController extends Controller implements HasMiddleware
{
    /**
     * Fixed sales order statuses.
     */
    public const STATUSES = ['pending', 'approved', 'completed', 'cancelled'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.sales-orders.view', only: ['index']),
            new Middleware('permission:transactions.sales-orders.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $salesOrders = SalesOrder::query()
            ->with(['customer', 'salesOrderDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('so_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('order_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.sales-orders.index', [
            'salesOrders' => $salesOrders,
            'search' => $search,
            'customers' => Customer::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSalesOrder($request);

        DB::transaction(function () use ($data) {
            $salesOrder = SalesOrder::create([
                'so_number' => $data['so_number'],
                'order_date' => $data['order_date'],
                'status' => $data['status'],
                'customer_id' => $data['customer_id'],
            ]);

            $salesOrder->salesOrderDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.sales-orders.index')->with('success', 'Sales order added successfully.');
    }

    public function update(Request $request, SalesOrder $salesOrder): RedirectResponse
    {
        $data = $this->validateSalesOrder($request, $salesOrder->id);

        DB::transaction(function () use ($data, $salesOrder) {
            $salesOrder->update([
                'so_number' => $data['so_number'],
                'order_date' => $data['order_date'],
                'status' => $data['status'],
                'customer_id' => $data['customer_id'],
            ]);

            $salesOrder->salesOrderDetails()->delete();
            $salesOrder->salesOrderDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.sales-orders.index')->with('success', 'Sales order updated successfully.');
    }

    public function destroy(SalesOrder $salesOrder): RedirectResponse
    {
        if ($salesOrder->sales()->exists()) {
            return back()->with('error', 'This sales order already has sales recorded and cannot be deleted.');
        }

        DB::transaction(function () use ($salesOrder) {
            $salesOrder->salesOrderDetails()->delete();
            $salesOrder->delete();
        });

        return redirect()->route('transactions.sales-orders.index')->with('success', 'Sales order deleted successfully.');
    }

    protected function validateSalesOrder(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'so_number' => [
                'required', 'string', 'max:100',
                Rule::unique('sales_orders', 'so_number')->ignore($ignoreId),
            ],
            'order_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'customer_id' => ['required', 'exists:customers,id'],
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

        return $data;
    }
}
