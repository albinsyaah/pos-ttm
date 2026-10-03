<?php

namespace App\Http\Controllers\Transactions;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\PayableService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller implements HasMiddleware
{
    /**
     * Fixed purchase statuses.
     */
    public const STATUSES = ['pending', 'received', 'cancelled'];

    public function __construct(
        private readonly StockService $stock,
        private readonly PayableService $payables,
    ) {
    }

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

        // Balance per invoice on this page (what is still owed, and how late).
        $balances = $this->payables->invoices($purchases->pluck('supplier_id')->unique()->all());

        return view('transactions.purchases.index', [
            'purchases' => $purchases,
            'balances' => $balances,
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
                'invoice_number' => $data['invoice_number'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
            ]);

            $purchase->purchaseDetails()->createMany($data['items']);

            $this->syncStock($purchase, $data);
        });

        return redirect()->route('transactions.purchases.index')->with('success', 'Purchase added successfully.');
    }

    public function update(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $this->validatePurchase($request, $purchase->id);

        DB::transaction(function () use ($data, $purchase) {
            $this->assertPaymentsStillFit($purchase, $data);

            $purchase->update([
                'invoice_number' => $data['invoice_number'] ?? null,
                'purchase_date' => $data['purchase_date'],
                'total_amount' => $data['total_amount'],
                'status' => $data['status'],
                'purchase_order_id' => $data['purchase_order_id'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
            ]);

            $purchase->purchaseDetails()->delete();
            $purchase->purchaseDetails()->createMany($data['items']);

            // Writes only the difference from what this purchase already did
            // to stock; throws (and rolls everything back) if an edit would
            // take back stock that has already been used.
            $this->syncStock($purchase, $data);
        });

        return redirect()->route('transactions.purchases.index')->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        if ($purchase->purchaseReturns()->exists()) {
            return back()->with('error', 'This purchase already has returns recorded and cannot be deleted.');
        }

        if ($purchase->payments()->exists()) {
            return back()->with('error', __('app.purchases.has_payments'));
        }

        try {
            DB::transaction(function () use ($purchase) {
                // Take back the stock this purchase added (refused if it was already used).
                $this->stock->sync($purchase, null, [], 'in', $purchase->invoice_number);

                $purchase->purchaseDetails()->delete();
                $purchase->delete();
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', implode(' ', $e->shortages));
        }

        return redirect()->route('transactions.purchases.index')->with('success', 'Purchase deleted successfully.');
    }

    /**
     * Once payments are recorded against an invoice, the edit may not strand
     * them: same supplier, still a payable status, and a total that still
     * covers what was paid.
     */
    protected function assertPaymentsStillFit(Purchase $purchase, array $data): void
    {
        $paid = (float) $purchase->payments()->sum('amount');

        if ($paid <= 0) {
            return;
        }

        $returned = (float) $purchase->purchaseReturns()->sum('total_amount');

        $problem = match (true) {
            (int) $data['supplier_id'] !== (int) $purchase->supplier_id => 'supplier_id',
            ! in_array($data['status'], Purchase::PAYABLE_STATUSES, true) => 'status',
            (float) $data['total_amount'] - $returned < $paid - 0.005 => 'items',
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages([$problem => __('app.purchases.payments_conflict')]);
        }
    }

    /**
     * Only a "received" purchase counts as stock; pending and cancelled ones
     * do not. Must be called inside the caller's DB::transaction().
     */
    protected function syncStock(Purchase $purchase, array $data): void
    {
        $counts = $data['status'] === 'received';

        $this->stock->sync(
            $purchase,
            $counts ? (int) $data['warehouse_id'] : null,
            $counts ? $data['items'] : [],
            'in',
            $purchase->invoice_number,
            $data['purchase_date']
        );
    }

    protected function validatePurchase(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'invoice_number' => [
                'nullable', 'string', 'max:100',
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
