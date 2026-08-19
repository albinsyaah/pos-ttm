<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller implements HasMiddleware
{
    /**
     * Fixed purchase statuses.
     */
    public const STATUSES = ['pending', 'received', 'cancelled'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.purchases.view', only: ['index']),
            new Middleware('permission:transactions.purchases.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $purchases = Purchase::query()
            ->with(['supplier', 'warehouse', 'purchaseOrder', 'purchaseDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('purchase_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.purchases.index', [
            'purchases' => $purchases,
            'search' => $search,
            'suppliers' => Supplier::orderBy('name')->get(),
            'warehouses' => Warehouse::orderBy('name')->get(),
            'purchaseOrders' => PurchaseOrder::orderByDesc('order_date')->get(),
            'products' => Product::orderBy('name')->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePurchase($request);

        DB::transaction(function () use ($data) {
            $purchase = Purchase::create([
                'invoice_number' => $data['invoice_number'],
                'purchase_date' => $data['purchase_date'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
            ]);

            $purchase->purchaseDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.purchases.index')->with('success', 'Purchase added successfully.');
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $this->validatePurchase($request, $purchase->id);

        DB::transaction(function () use ($data, $purchase) {
            $purchase->update([
                'invoice_number' => $data['invoice_number'],
                'purchase_date' => $data['purchase_date'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
            ]);

            $purchase->purchaseDetails()->delete();
            $purchase->purchaseDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.purchases.index')->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        if ($purchase->purchaseReturns()->exists()) {
            return back()->with('error', 'This purchase already has returns recorded and cannot be deleted.');
        }

        DB::transaction(function () use ($purchase) {
            $purchase->purchaseDetails()->delete();
            $purchase->delete();
        });

        return redirect()->route('transactions.purchases.index')->with('success', 'Purchase deleted successfully.');
    }

    protected function validatePurchase(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'invoice_number' => [
                'required', 'string', 'max:100',
                Rule::unique('purchases', 'invoice_number')->ignore($ignoreId),
            ],
            'purchase_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
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

        // Total is derived from the line items rather than trusted from the
        // client, so it always matches what was actually submitted.
        $data['total_amount'] = collect($data['items'])->sum(fn ($item) => $item['qty'] * $item['price']);

        $data['purchase_order_id'] = $data['purchase_order_id'] ?? null;

        return $data;
    }
}
