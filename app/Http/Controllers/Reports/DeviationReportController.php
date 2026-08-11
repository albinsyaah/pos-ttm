<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Transactions\DeviationController;
use App\Models\InternalMutation;
use App\Models\Warehouse;
use Illuminate\Http\Request;

/**
 * Read-only Deviation report: lets any user with reports.view filter
 * stock opname deviations by date range, warehouse, status and free
 * text, and see per-deviation + grand totals.
 *
 * Built on the same `internal_mutations` table/model as
 * Transactions\DeviationController, scoped to
 * type = DeviationController::TYPE — the same pattern
 * ReceiptReportController uses on top of CashFlow. Qty stays signed
 * (positive = overage, negative = shortage), so the "net qty" total
 * shows whether filtered deviations skew toward overage or shortage.
 * No create/update/delete — this is reporting only, matching the other
 * Reports\* controllers.
 */
class DeviationReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $warehouseId = $request->query('warehouse_id');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = InternalMutation::query()
            ->where('type', DeviationController::TYPE)
            ->with(['fromWarehouse', 'requestedBy', 'internalMutationDetails.product'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('mutation_number', 'like', "%{$search}%")
                        ->orWhereHas('fromWarehouse', function ($warehouseQuery) use ($search) {
                            $warehouseQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->when($warehouseId, fn ($q) => $q->where('from_warehouse_id', $warehouseId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('mutation_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('mutation_date', '<=', $dateTo))
            ->orderByDesc('mutation_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating.
        $allFiltered = (clone $query)->with('internalMutationDetails:id,internal_mutation_id,qty')->get();
        $totalDeviations = $allFiltered->count();
        $totalNetQty = $allFiltered->sum(fn ($deviation) => $deviation->internalMutationDetails->sum('qty'));

        $deviations = $query->paginate(20)->withQueryString();

        return view('reports.deviations.index', [
            'deviations' => $deviations,
            'search' => $search,
            'warehouseId' => $warehouseId,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'statuses' => DeviationController::STATUSES,
            'totalDeviations' => $totalDeviations,
            'totalNetQty' => $totalNetQty,
        ]);
    }
}
