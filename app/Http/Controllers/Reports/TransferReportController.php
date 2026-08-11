<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Transactions\WarehouseTransferController;
use App\Models\InternalMutation;
use App\Models\Warehouse;
use Illuminate\Http\Request;

/**
 * Read-only Warehouse Transfer report: lets any user with reports.view
 * filter warehouse transfers by date range, from/to warehouse, status and
 * free text, and see per-transfer + grand totals.
 *
 * Built on the same `internal_mutations` table/model as
 * Transactions\WarehouseTransferController, scoped to
 * type = WarehouseTransferController::TYPE — the same pattern
 * ReceiptReportController uses on top of CashFlow. No create/update/
 * delete — this is reporting only, matching the other Reports\* controllers.
 */
class TransferReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $fromWarehouseId = $request->query('from_warehouse_id');
        $toWarehouseId = $request->query('to_warehouse_id');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $query = InternalMutation::query()
            ->where('type', WarehouseTransferController::TYPE)
            ->with(['fromWarehouse', 'toWarehouse', 'requestedBy', 'internalMutationDetails.product'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('mutation_number', 'like', "%{$search}%")
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
            ->when($fromWarehouseId, fn ($q) => $q->where('from_warehouse_id', $fromWarehouseId))
            ->when($toWarehouseId, fn ($q) => $q->where('to_warehouse_id', $toWarehouseId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($dateFrom, fn ($q) => $q->whereDate('mutation_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('mutation_date', '<=', $dateTo))
            ->orderByDesc('mutation_date')
            ->orderByDesc('id');

        // Summary totals across the whole filtered result set (not just the
        // current page), so the cards above the table stay accurate while
        // paginating.
        $allFiltered = (clone $query)->with('internalMutationDetails:id,internal_mutation_id,qty')->get();
        $totalTransfers = $allFiltered->count();
        $totalQty = $allFiltered->sum(fn ($transfer) => $transfer->internalMutationDetails->sum('qty'));

        $transfers = $query->paginate(20)->withQueryString();

        return view('reports.transfers.index', [
            'transfers' => $transfers,
            'search' => $search,
            'fromWarehouseId' => $fromWarehouseId,
            'toWarehouseId' => $toWarehouseId,
            'status' => $status,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'warehouses' => Warehouse::orderBy('name')->get(),
            'statuses' => WarehouseTransferController::STATUSES,
            'totalTransfers' => $totalTransfers,
            'totalQty' => $totalQty,
        ]);
    }
}
