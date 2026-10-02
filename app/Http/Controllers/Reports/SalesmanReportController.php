<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * What each salesman has sold: per salesman, every product with the paid
 * quantity, the free quantity (lines priced Rp0) and the revenue.
 * Sales without a salesman are not part of this report. Read-only.
 */
class SalesmanReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $salesmanId = $request->query('salesman_id');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $base = SaleDetail::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->whereNotNull('sales.salesman_id')
            ->when($salesmanId, fn ($q) => $q->where('sales.salesman_id', $salesmanId))
            ->when($dateFrom, fn ($q) => $q->whereDate('sales.sale_date', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('sales.sale_date', '<=', $dateTo))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('products.name', 'like', "%{$search}%")
                        ->orWhere('products.code', 'like', "%{$search}%");
                });
            });

        $lines = (clone $base)
            ->selectRaw('sales.salesman_id, sale_details.product_id,
                SUM(CASE WHEN sale_details.price > 0 THEN sale_details.qty ELSE 0 END) as paid_qty,
                SUM(CASE WHEN sale_details.price > 0 THEN 0 ELSE sale_details.qty END) as free_qty,
                SUM(sale_details.qty * sale_details.price) as revenue')
            ->groupBy('sales.salesman_id', 'sale_details.product_id')
            ->get();

        // Number of sales per salesman (within the same filters, a sale counts once).
        $saleCounts = (clone $base)
            ->selectRaw('sales.salesman_id, COUNT(DISTINCT sales.id) as n')
            ->groupBy('sales.salesman_id')
            ->pluck('n', 'salesman_id');

        $products = Product::whereIn('id', $lines->pluck('product_id')->unique())->get()->keyBy('id');
        $salesmen = Employee::whereIn('id', $lines->pluck('salesman_id')->unique())->orderBy('name')->get();

        $groups = $salesmen->map(function (Employee $salesman) use ($lines, $products, $saleCounts) {
            $rows = $lines->where('salesman_id', $salesman->id)
                ->map(fn ($line) => [
                    'product' => $products->get($line->product_id),
                    'paid_qty' => (int) $line->paid_qty,
                    'free_qty' => (int) $line->free_qty,
                    'revenue' => (float) $line->revenue,
                ])
                ->filter(fn ($row) => $row['product'] !== null)
                ->sortBy(fn ($row) => $row['product']->name)
                ->values();

            return [
                'salesman' => $salesman,
                'sales_count' => (int) ($saleCounts[$salesman->id] ?? 0),
                'rows' => $rows,
                'revenue' => $rows->sum('revenue'),
            ];
        })->values();

        return view('reports.salesman.index', [
            'groups' => $groups,
            'grandTotal' => $groups->sum('revenue'),
            'salesmen' => Employee::whereIn('id', Sale::whereNotNull('salesman_id')->select('salesman_id'))
                ->orderBy('name')->get(),
            'search' => $search,
            'salesmanId' => $salesmanId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
