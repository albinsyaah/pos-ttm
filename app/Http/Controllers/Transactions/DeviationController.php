<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\InternalMutation;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Deviations are InternalMutation records with a fixed type of "Deviation"
 * — stock opname discrepancies found in a single warehouse. Qty is signed:
 * positive when the physical count is higher than the system stock
 * (overage) and negative when it's lower (shortage/shrinkage). This gives
 * that subset its own page (matching the sidebar's Transaksi > Mutasi
 * Internal > Deviation entry) without needing a separate table, following
 * the same pattern as InternalExpenditureController / InternalReceiptController
 * / WarehouseTransferController.
 */
class DeviationController extends Controller implements HasMiddleware
{
    protected const TYPE = 'Deviation';

    /**
     * Fixed internal mutation statuses.
     */
    public const STATUSES = ['pending', 'approved', 'completed', 'rejected'];

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

        $deviations = InternalMutation::query()
            ->where('type', self::TYPE)
            ->with(['fromWarehouse', 'requestedBy', 'internalMutationDetails.product'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('mutation_number', 'like', "%{$search}%")
                        ->orWhereHas('fromWarehouse', function ($warehouseQuery) use ($search) {
                            $warehouseQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('mutation_date')
            ->paginate(20)
            ->withQueryString();

        return view('transactions.deviations.index', [
            'deviations' => $deviations,
            'search' => $search,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'employees' => Employee::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateDeviation($request);

        DB::transaction(function () use ($data) {
            $deviation = InternalMutation::create([
                'mutation_number' => $data['mutation_number'],
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'from_warehouse_id' => $data['warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $deviation->internalMutationDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.deviations.index')->with('success', 'Deviation added successfully.');
    }

    public function update(Request $request, InternalMutation $deviation): RedirectResponse
    {
        $data = $this->validateDeviation($request, $deviation->id);

        DB::transaction(function () use ($data, $deviation) {
            $deviation->update([
                'mutation_number' => $data['mutation_number'],
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'from_warehouse_id' => $data['warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $deviation->internalMutationDetails()->delete();
            $deviation->internalMutationDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.deviations.index')->with('success', 'Deviation updated successfully.');
    }

    public function destroy(InternalMutation $deviation): RedirectResponse
    {
        DB::transaction(function () use ($deviation) {
            $deviation->internalMutationDetails()->delete();
            $deviation->delete();
        });

        return redirect()->route('transactions.deviations.index')->with('success', 'Deviation deleted successfully.');
    }

    protected function validateDeviation(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'mutation_number' => [
                'required', 'string', 'max:100',
                Rule::unique('internal_mutations', 'mutation_number')->ignore($ignoreId),
            ],
            'mutation_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'requested_by' => ['nullable', 'exists:employees,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            // Signed: positive = overage (stock found higher than system),
            // negative = shortage (stock found lower than system). Zero is
            // not a deviation.
            'items.*.qty' => ['required', 'integer', 'not_in:0'],
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
