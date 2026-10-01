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
 * Warehouse Transfers are InternalMutation records with a fixed type of
 * "Transfer Antar Gudang" (the type already used by InternalMutationSeeder
 * for stock moved between warehouses) — both a source and destination
 * warehouse are recorded. This gives that subset its own page (matching
 * the sidebar's Transaksi > Mutasi Internal > Warehouse Transfer entry)
 * without needing a separate table.
 *
 * TYPE is public so Reports\TransferReportController can filter on the
 * same constant instead of duplicating the literal.
 */
class WarehouseTransferController extends Controller implements HasMiddleware
{
    public const TYPE = 'Transfer Antar Gudang';

    /**
     * Fixed internal mutation statuses.
     */
    public const STATUSES = ['pending', 'approved', 'completed', 'rejected'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.warehouse-transfers.view', only: ['index']),
            new Middleware('permission:transactions.warehouse-transfers.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $warehouseTransfers = InternalMutation::query()
            ->where('type', self::TYPE)
            ->with(['fromWarehouse', 'toWarehouse', 'requestedBy', 'internalMutationDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('mutation_number', 'like', "%{$search}%")
                        ->orWhereHas('fromWarehouse', function ($warehouseQuery) use ($search) {
                            $warehouseQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('toWarehouse', function ($warehouseQuery) use ($search) {
                            $warehouseQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('mutation_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.warehouse-transfers.index', [
            'warehouseTransfers' => $warehouseTransfers,
            'search' => $search,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'employees' => Employee::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateWarehouseTransfer($request);

        DB::transaction(function () use ($data) {
            $warehouseTransfer = InternalMutation::create([
                'mutation_number' => $data['mutation_number'],
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $warehouseTransfer->internalMutationDetails()->createMany($data['items']);

            // Only a "completed" warehouse transfer moves stock. Refused (and rolled back)
            // if a warehouse is short.
            $this->syncStock($warehouseTransfer, $data);
        });

        return redirect()->route('transactions.warehouse-transfers.index')->with('success', 'Warehouse transfer added successfully.');
    }

    public function update(Request $request, InternalMutation $warehouseTransfer): RedirectResponse
    {
        $data = $this->validateWarehouseTransfer($request, $warehouseTransfer->id);

        DB::transaction(function () use ($data, $warehouseTransfer) {
            $warehouseTransfer->update([
                'mutation_number' => $data['mutation_number'],
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $warehouseTransfer->internalMutationDetails()->delete();
            $warehouseTransfer->internalMutationDetails()->createMany($data['items']);

            $this->syncStock($warehouseTransfer, $data);
        });

        return redirect()->route('transactions.warehouse-transfers.index')->with('success', 'Warehouse transfer updated successfully.');
    }

    public function destroy(InternalMutation $warehouseTransfer): RedirectResponse
    {
        try {
            DB::transaction(function () use ($warehouseTransfer) {
                // Undo whatever this warehouse transfer did to stock (refused if that stock
                // has already been used).
                app(StockService::class)->syncTransfer($warehouseTransfer, null, null, [], $warehouseTransfer->mutation_number);

                $warehouseTransfer->internalMutationDetails()->delete();
                $warehouseTransfer->delete();
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', implode(' ', $e->shortages));
        }

        return redirect()->route('transactions.warehouse-transfers.index')->with('success', 'Warehouse transfer deleted successfully.');
    }

    /**
     * Stock moves from the source to the destination warehouse only while the
     * transfer is "completed"; pending, approved and rejected ones do not.
     * Must be called inside the caller's DB::transaction().
     */
    protected function syncStock(InternalMutation $warehouseTransfer, array $data): void
    {
        $counts = $data['status'] === 'completed';

        app(StockService::class)->syncTransfer(
            $warehouseTransfer,
            $counts ? (int) $data['from_warehouse_id'] : null,
            $counts ? (int) $data['to_warehouse_id'] : null,
            $counts ? $data['items'] : [],
            $data['mutation_number'],
            $data['mutation_date']
        );
    }

    protected function validateWarehouseTransfer(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'mutation_number' => [
                'required', 'string', 'max:100',
                Rule::unique('internal_mutations', 'mutation_number')->ignore($ignoreId),
            ],
            'mutation_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'from_warehouse_id' => ['required', 'exists:warehouses,id', 'different:to_warehouse_id'],
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
