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
 * Internal Expenditures are InternalMutation records with a fixed type of
 * "Internal Expenditure" — stock leaving a warehouse for internal use.
 * This gives that subset its own page (matching the sidebar's
 * Transaksi > Mutasi Internal > Internal Expenditure entry) without
 * needing a separate table.
 */
class InternalExpenditureController extends Controller implements HasMiddleware
{
    protected const TYPE = 'Internal Expenditure';

    /**
     * Fixed internal mutation statuses.
     */
    public const STATUSES = ['pending', 'approved', 'completed', 'rejected'];

    public static function middleware(): array
    {
        return [
            new Middleware('permission:transactions.internal-expenditures.view', only: ['index']),
            new Middleware('permission:transactions.internal-expenditures.manage', only: ['store', 'update', 'destroy']),
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $internalExpenditures = InternalMutation::query()
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

        return view('transactions.internal-expenditures.index', [
            'internalExpenditures' => $internalExpenditures,
            'search' => $search,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'employees' => Employee::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
            'statuses' => self::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateInternalExpenditure($request);

        DB::transaction(function () use ($data) {
            $internalExpenditure = InternalMutation::create([
                'mutation_number' => $data['mutation_number'],
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $internalExpenditure->internalMutationDetails()->createMany($data['items']);

            // Only a "completed" internal expenditure moves stock. Refused (and rolled back)
            // if a warehouse is short.
            $this->syncStock($internalExpenditure, $data);
        });

        return redirect()->route('transactions.internal-expenditures.index')->with('success', 'Internal expenditure added successfully.');
    }

    public function update(Request $request, InternalMutation $internalExpenditure): RedirectResponse
    {
        $data = $this->validateInternalExpenditure($request, $internalExpenditure->id);

        DB::transaction(function () use ($data, $internalExpenditure) {
            $internalExpenditure->update([
                'mutation_number' => $data['mutation_number'],
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'from_warehouse_id' => $data['from_warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $internalExpenditure->internalMutationDetails()->delete();
            $internalExpenditure->internalMutationDetails()->createMany($data['items']);

            $this->syncStock($internalExpenditure, $data);
        });

        return redirect()->route('transactions.internal-expenditures.index')->with('success', 'Internal expenditure updated successfully.');
    }

    public function destroy(InternalMutation $internalExpenditure): RedirectResponse
    {
        try {
            DB::transaction(function () use ($internalExpenditure) {
                // Undo whatever this internal expenditure did to stock (refused if that stock
                // has already been used).
                app(StockService::class)->sync($internalExpenditure, null, [], 'out', $internalExpenditure->mutation_number);

                $internalExpenditure->internalMutationDetails()->delete();
                $internalExpenditure->delete();
            });
        } catch (InsufficientStockException $e) {
            return back()->with('error', implode(' ', $e->shortages));
        }

        return redirect()->route('transactions.internal-expenditures.index')->with('success', 'Internal expenditure deleted successfully.');
    }

    /**
     * Stock leaves the warehouse only while the expenditure is "completed".
     * Must be called inside the caller's DB::transaction().
     */
    protected function syncStock(InternalMutation $internalExpenditure, array $data): void
    {
        $counts = $data['status'] === 'completed';

        app(StockService::class)->sync(
            $internalExpenditure,
            $counts ? (int) $data['from_warehouse_id'] : null,
            $counts ? $data['items'] : [],
            'out',
            $data['mutation_number'],
            $data['mutation_date']
        );
    }

    protected function validateInternalExpenditure(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'mutation_number' => [
                'required', 'string', 'max:100',
                Rule::unique('internal_mutations', 'mutation_number')->ignore($ignoreId),
            ],
            'mutation_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
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
