<?php

namespace App\Http\Controllers\Transactions;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\InternalMutation;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Internal Receipts are InternalMutation records with a fixed type of
 * "Internal Receipt" — stock coming into a warehouse from an internal
 * source (e.g. production output, found stock, non-purchase receipt).
 * This gives that subset its own page (matching the sidebar's
 * Transaksi > Mutasi Internal > Internal Receipt entry) without needing
 * a separate table.
 */
class InternalReceiptController extends Controller implements HasMiddleware
{
    protected const TYPE = 'Internal Receipt';

    /**
     * Fixed internal mutation statuses.
     */
    public const STATUSES = ['pending', 'approved', 'completed', 'rejected'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.internal-receipts.view', only: ['index']),
            new Middleware('permission:transactions.internal-receipts.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $internalReceipts = InternalMutation::query()
            ->where('type', self::TYPE)
            ->with(['toWarehouse', 'requestedBy', 'internalMutationDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('mutation_number', 'like', "%{$search}%")
                        ->orWhereHas('toWarehouse', function ($warehouseQuery) use ($search) {
                            $warehouseQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('mutation_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.internal-receipts.index', [
            'internalReceipts' => $internalReceipts,
            'search' => $search,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'employees' => Employee::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInternalReceipt($request);

        DB::transaction(function () use ($data) {
            $internalReceipt = InternalMutation::create([
                'mutation_number' => $data['mutation_number'] ?? null,
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $internalReceipt->internalMutationDetails()->createMany($data['items']);

            // Only a "completed" internal receipt moves stock. Refused (and rolled back)
            // if a warehouse is short.
            $this->syncStock($internalReceipt, $data);
        });

        return redirect()->route('transactions.internal-receipts.index')->with('success', 'Internal receipt added successfully.');
    }

    public function update(Request $request, InternalMutation $internalReceipt): RedirectResponse
    {
        $data = $this->validateInternalReceipt($request, $internalReceipt->id);

        DB::transaction(function () use ($data, $internalReceipt) {
            $internalReceipt->update([
                'mutation_number' => $data['mutation_number'] ?? null,
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $internalReceipt->internalMutationDetails()->delete();
            $internalReceipt->internalMutationDetails()->createMany($data['items']);

            $this->syncStock($internalReceipt, $data);
        });

        return redirect()->route('transactions.internal-receipts.index')->with('success', 'Internal receipt updated successfully.');
    }

    public function destroy(InternalMutation $internalReceipt): RedirectResponse
    {
        try {
            DB::transaction(function () use ($internalReceipt) {
                // Undo whatever this internal receipt did to stock (refused if that stock
                // has already been used).
                app(StockService::class)->sync($internalReceipt, null, [], 'in', $internalReceipt->mutation_number);

                $internalReceipt->internalMutationDetails()->delete();
                $internalReceipt->delete();
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', implode(' ', $e->shortages));
        }

        return redirect()->route('transactions.internal-receipts.index')->with('success', 'Internal receipt deleted successfully.');
    }

    /**
     * Stock comes into the warehouse only while the receipt is "completed".
     * Must be called inside the caller's DB::transaction().
     */
    protected function syncStock(InternalMutation $internalReceipt, array $data): void
    {
        $counts = $data['status'] === 'completed';

        app(StockService::class)->sync(
            $internalReceipt,
            $counts ? (int) $data['to_warehouse_id'] : null,
            $counts ? $data['items'] : [],
            'in',
            $internalReceipt->mutation_number,
            $data['mutation_date']
        );
    }

    protected function validateInternalReceipt(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'mutation_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('internal_mutations', 'mutation_number')->ignore($ignoreId),
            ],
            'mutation_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],
            'requested_by' => ['nullable', 'exists:employees,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $data['items'] = array_map(fn ($item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'notes' => $item['notes'] ?? null,
        ], $data['items']);

        return $data;
    }
}
