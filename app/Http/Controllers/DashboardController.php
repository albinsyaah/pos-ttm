<?php

namespace App\Http\Controllers;

use App\Models\ArPayment;
use App\Models\ApPayment;
use App\Models\InventoryLedger;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalesOrder;
use App\Services\SalesInsightService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Products with a total balance below this are counted as "low stock".
     * There's no dedicated "reorder level" column on the Product model,
     * so this is a simple fixed threshold — adjust to taste.
     */
    protected int $lowStockThreshold = 10;

    public function index(Request $request, SalesInsightService $insight)
    {
        $today = Carbon::today();

        // Sales cards: day, week (Monday to Sunday) and month, each as a date range
        // (the old month figure matched the month number only, mixing in other years).
        [$dayFrom, $dayTo] = SalesInsightService::bounds('day', $today);
        [$weekFrom, $weekTo] = SalesInsightService::bounds('week', $today);
        [$monthFrom, $monthTo] = SalesInsightService::bounds('month', $today);

        $daySales = $insight->salesTotal($dayFrom, $dayTo);
        $weekSales = $insight->salesTotal($weekFrom, $weekTo);
        $monthSales = $insight->salesTotal($monthFrom, $monthTo);

        $todaySales = $daySales['total'];
        $weeklySales = $weekSales['total'];
        $ordersToday = $daySales['count'];
        $avgOrderValue = $ordersToday > 0 ? $todaySales / $ordersToday : 0;

        // One range switch (day / week / month) drives the best sellers and the income per method.
        $range = SalesInsightService::normalizeRange($request->query('range'));
        [$rangeFrom, $rangeTo] = SalesInsightService::bounds($range, $today);

        $lowStockProducts = $this->lowStockProducts();
        $lowStockCount = $lowStockProducts->count();

        $receivablesOutstanding = Sale::receivable()->sum('total_amount') - ArPayment::sum('amount');
        $payablesOutstanding = Purchase::sum('total_amount') - ApPayment::sum('amount');

        // "Pending" = raised but not yet fulfilled by an actual sale/purchase.
        $pendingSalesOrders = SalesOrder::whereDoesntHave('sales')->count();
        $pendingPurchaseOrders = PurchaseOrder::whereDoesntHave('purchases')->count();

        $days = $this->weeklySalesChart($today);
        $topProducts = $insight->topProducts($rangeFrom, $rangeTo, 10);
        $incomeByMethod = $insight->incomeByMethod($rangeFrom, $rangeTo);
        $topCustomers = $this->topCustomers();
        $orders = $this->recentOrders();

        return view('dashboard', [
            'todaySales' => $todaySales,
            'weeklySales' => $weeklySales,
            'monthSales' => $monthSales['total'],
            'weekCount' => $weekSales['count'],
            'monthCount' => $monthSales['count'],
            'range' => $range,
            'rangeFrom' => $rangeFrom,
            'rangeTo' => $rangeTo,
            'incomeByMethod' => $incomeByMethod,
            'ordersToday' => $ordersToday,
            'avgOrderValue' => $avgOrderValue,
            'lowStockCount' => $lowStockCount,
            'lowStockProducts' => $lowStockProducts,
            'receivablesOutstanding' => max(0, $receivablesOutstanding),
            'payablesOutstanding' => max(0, $payablesOutstanding),
            'pendingSalesOrders' => $pendingSalesOrders,
            'pendingPurchaseOrders' => $pendingPurchaseOrders,
            'days' => $days,
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
            'orders' => $orders,
        ]);
    }

    /**
     * Products whose latest inventory ledger balance (summed across
     * warehouses) is below the low-stock threshold, lowest first.
     */
    protected function lowStockProducts()
    {
        $latestPerProductWarehouse = InventoryLedger::select('product_id', 'warehouse_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('product_id', 'warehouse_id');

        return InventoryLedger::joinSub($latestPerProductWarehouse, 'latest', function ($join) {
                $join->on('inventory_ledgers.id', '=', 'latest.last_id');
            })
            ->join('products', 'products.id', '=', 'inventory_ledgers.product_id')
            ->select('products.id', 'products.name', 'products.code', DB::raw('SUM(inventory_ledgers.balance) as balance'))
            ->groupBy('products.id', 'products.name', 'products.code')
            ->having('balance', '<', $this->lowStockThreshold)
            ->orderBy('balance')
            ->limit(8)
            ->get();
    }

    /**
     * Daily sales totals for this week vs the same weekday last week,
     * feeding the bar chart on the dashboard.
     */
    protected function weeklySalesChart(Carbon $today): array
    {
        $startOfThisWeek = $today->copy()->startOfWeek();
        $startOfLastWeek = $startOfThisWeek->copy()->subWeek();

        $thisWeekSales = Sale::whereBetween('sale_date', [$startOfThisWeek, $startOfThisWeek->copy()->endOfWeek()])
            ->selectRaw('DATE(sale_date) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $lastWeekSales = Sale::whereBetween('sale_date', [$startOfLastWeek, $startOfLastWeek->copy()->endOfWeek()])
            ->selectRaw('DATE(sale_date) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $thisDay = $startOfThisWeek->copy()->addDays($i);
            $lastDay = $startOfLastWeek->copy()->addDays($i);

            $days[] = [
                'label' => $thisDay->format('D'),
                'this' => (float) ($thisWeekSales[$thisDay->toDateString()] ?? 0),
                'last' => (float) ($lastWeekSales[$lastDay->toDateString()] ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Top 5 customers by total revenue (all time).
     */
    protected function topCustomers()
    {
        return Sale::query()
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->selectRaw('customers.id, customers.name, customers.code, COUNT(sales.id) as orders, SUM(sales.total_amount) as total')
            ->groupBy('customers.id', 'customers.name', 'customers.code')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
    }

    /**
     * Most recent sales, with customer and item count.
     */
    protected function recentOrders(): array
    {
        return Sale::with('customer')
            ->withCount('saleDetails')
            ->latest('sale_date')
            ->take(10)
            ->get()
            ->map(function (Sale $sale) {
                return [
                    'id' => $sale->invoice_number,
                    'customer' => $sale->customer->name ?? '—',
                    'items' => $sale->sale_details_count,
                    'source' => $sale->source ?? '—',
                    'time' => optional($sale->sale_date)->format('H:i'),
                    'date' => optional($sale->sale_date)->format('d M Y'),
                    'total' => (float) $sale->total_amount,
                ];
            })
            ->all();
    }
}
