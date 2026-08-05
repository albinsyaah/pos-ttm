<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller implements HasMiddleware
{
    /**
     * Fixed purchase order statuses.
     */
    public const STATUSES = ['pending', 'approved', 'completed', 'cancelled'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.view', only: ['index']),
            new Middleware('permission:transactions.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'purchaseOrderDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('supplier', function ($supplierQuery) use ($search) {
                            $supplierQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('order_date')
            ->paginate(10)
            ->withQueryString();

        return view('transactions.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'search' => $search,
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePurchaseOrder($request);

        DB::transaction(function () use ($data) {
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $data['po_number'],
                'order_date' => $data['order_date'],
                'status' => $data['status'],
                'supplier_id' => $data['supplier_id'],
            ]);

            $purchaseOrder->purchaseOrderDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.purchase-orders.index')->with('success', 'Purchase order added successfully.');
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $data = $this->validatePurchaseOrder($request, $purchaseOrder->id);

        DB::transaction(function () use ($data, $purchaseOrder) {
            $purchaseOrder->update([
                'po_number' => $data['po_number'],
                'order_date' => $data['order_date'],
                'status' => $data['status'],
                'supplier_id' => $data['supplier_id'],
            ]);

            $purchaseOrder->purchaseOrderDetails()->delete();
            $purchaseOrder->purchaseOrderDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.purchase-orders.index')->with('success', 'Purchase order updated successfully.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->purchases()->exists()) {
            return back()->with('error', 'This purchase order already has purchases recorded and cannot be deleted.');
        }

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->purchaseOrderDetails()->delete();
            $purchaseOrder->delete();
        });

        return redirect()->route('transactions.purchase-orders.index')->with('success', 'Purchase order deleted successfully.');
    }

    protected function validatePurchaseOrder(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'po_number' => [
                'required', 'string', 'max:100',
                Rule::unique('purchase_orders', 'po_number')->ignore($ignoreId),
            ],
            'order_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'supplier_id' => ['required', 'exists:suppliers,id'],
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
