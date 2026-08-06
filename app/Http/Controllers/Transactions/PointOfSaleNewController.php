<?php

namespace App\Http\Controllers\Transactions;

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

class PointOfSaleNewController extends Controller implements HasMiddleware
{
    /**
     * Shares the 'sales' table with the Sales and Point of Sale pages
     * (see App\Http\Controllers\Transactions\PointOfSaleController::SOURCE).
     */
    public const SOURCE = 'pos';

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.view', only: ['index']),
            new Middleware('permission:transactions.manage', only: ['store']),
        ];
    }

    /**
     * Show the checkout terminal. This is a create-only screen — completed
     * transactions are managed afterwards on the Point of Sale list page.
     */
    public function index()
    {
        return view('transactions.point-of-sale-new.index', [
            'customers' => Customer::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'salesmen' => Employee::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100', 'unique:sales,invoice_number'],
            'sale_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'salesman_id' => ['nullable', 'exists:employees,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ]);

        $items = array_map(fn ($item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'price' => $item['price'],
        ], $data['items']);

        $totalAmount = collect($items)->sum(fn ($item) => $item['qty'] * $item['price']);

        DB::transaction(function () use ($data, $items, $totalAmount) {
            $sale = Sale::create([
                'invoice_number' => $data['invoice_number'],
                'sale_date' => $data['sale_date'],
                'total_amount' => $totalAmount,
                'source' => self::SOURCE,
                'sales_order_id' => null,
                'customer_id' => $data['customer_id'] ?? null,
                'salesman_id' => $data['salesman_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
            ]);

            $sale->saleDetails()->createMany($items);
        });

        // Stay on the terminal (fresh cart) so the cashier can ring up the
        // next transaction immediately, rather than bouncing to a list page.
        return redirect()->route('transactions.point-of-sale-new.index')
            ->with('success', "Transaction {$data['invoice_number']} completed successfully.");
    }
}
