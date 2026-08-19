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
                'mutation_number' => $data['mutation_number'],
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $internalReceipt->internalMutationDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.internal-receipts.index')->with('success', 'Internal receipt added successfully.');
    }

    public function update(Request $request, InternalMutation $internalReceipt): RedirectResponse
    {
        $data = $this->validateInternalReceipt($request, $internalReceipt->id);

        DB::transaction(function () use ($data, $internalReceipt) {
            $internalReceipt->update([
                'mutation_number' => $data['mutation_number'],
                'type' => self::TYPE,
                'mutation_date' => $data['mutation_date'],
                'status' => $data['status'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'requested_by' => $data['requested_by'],
            ]);

            $internalReceipt->internalMutationDetails()->delete();
            $internalReceipt->internalMutationDetails()->createMany($data['items']);
        });

        return redirect()->route('transactions.internal-receipts.index')->with('success', 'Internal receipt updated successfully.');
    }

    public function destroy(InternalMutation $internalReceipt): RedirectResponse
    {
        DB::transaction(function () use ($internalReceipt) {
            $internalReceipt->internalMutationDetails()->delete();
            $internalReceipt->delete();
        });

        return redirect()->route('transactions.internal-receipts.index')->with('success', 'Internal receipt deleted successfully.');
    }

    protected function validateInternalReceipt(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'mutation_number' => [
                'required', 'string', 'max:100',
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
