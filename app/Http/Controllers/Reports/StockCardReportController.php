<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-only Stock Card ("Kartu Stok") report: a running ledger of stock
 * movements for a single product, built on the `inventory_ledgers` table
 * that every stock-moving seeder (purchases, sales, returns, internal
 * mutations — see Database\Seeders\Concerns\ManagesInventoryLedger) already
 * writes to. Optionally narrowed to one warehouse.
 *
 * A product must be selected; without one the page shows an empty state
 * rather than an unfiltered (meaningless, cross-product) ledger — the same
 * pattern ArCardReportController uses for the Receivable Card report. The
 * running balance is always recalculated here (rather than trusting the
 * `balance` column stored per row) so it stays correct whether a single
 * warehouse or "all warehouses" is selected; date filters narrow which
 * rows are shown, but the opening balance is still computed from
 * everything before date_from.
 *
 * Permission check lives on the route itself (single action, no
 * HasMiddleware needed), matching the pattern used by the other
 * Reports\* controllers.
 */
class StockCardReportController extends Controller
{
    public function index(Request $request)
    {
        $productId = $request->query('product_id');
        $warehouseId = $request->query('warehouse_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $products = Product::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();
        $product = $productId ? $products->firstWhere('id', (int) $productId) : null;

        $typeLabels = [
            'IN' => __('app.reports.stock_card.type_in'),
            'OUT' => __('app.reports.stock_card.type_out'),
            'TRANSFER_IN' => __('app.reports.stock_card.type_transfer_in'),
            'TRANSFER_OUT' => __('app.reports.stock_card.type_transfer_out'),
        ];

        $rows = collect();
        $openingBalance = 0;
        $totalIn = 0;
        $totalOut = 0;

        if ($product) {
            $entries = InventoryLedger::where('product_id', $product->id)
                ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
                ->with('warehouse')
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get()
                ->map(fn ($entry) => [
                    'date' => $entry->transaction_date,
                    'sort_id' => $entry->id,
                    'reference_number' => $entry->reference_number,
                    'type' => $entry->type,
                    'type_label' => $typeLabels[$entry->type] ?? $entry->type,
                    'warehouse' => $entry->warehouse?->name,
                    'qty' => (int) $entry->qty,
                ]);

            $balance = 0;
            $entries = $entries->map(function ($row) use (&$balance) {
                $balance += $row['qty'];
                $row['balance'] = $balance;

                return $row;
            });

            if ($dateFrom) {
                $fromCutoff = Carbon::parse($dateFrom);
                $beforeFrom = $entries->last(fn ($row) => Carbon::parse($row['date'])->lt($fromCutoff));
                $openingBalance = $beforeFrom['balance'] ?? 0;
            }

            $rows = $entries->filter(function ($row) use ($dateFrom, $dateTo) {
                $date = Carbon::parse($row['date']);

                if ($dateFrom && $date->lt(Carbon::parse($dateFrom))) {
                    return false;
                }
                if ($dateTo && $date->gt(Carbon::parse($dateTo))) {
                    return false;
                }

                return true;
            })->values();

            $totalIn = $rows->sum(fn ($row) => $row['qty'] > 0 ? $row['qty'] : 0);
            $totalOut = $rows->sum(fn ($row) => $row['qty'] < 0 ? abs($row['qty']) : 0);
        }

        $endingBalance = $openingBalance + $totalIn - $totalOut;

        return view('reports.stock-card.index', [
            'products' => $products,
            'warehouses' => $warehouses,
            'product' => $product,
            'productId' => $productId,
            'warehouseId' => $warehouseId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'openingBalance' => $openingBalance,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'endingBalance' => $endingBalance,
        ]);
    }
}
