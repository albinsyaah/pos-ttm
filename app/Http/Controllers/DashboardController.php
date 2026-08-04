<?php

namespace App\Http\Controllers;

use App\Models\InventoryLedger;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Products with a total balance below this are counted as "low stock".
     * There's no dedicated "reorder level" column on the Product model,
     * so this is a simple fixed threshold — adjust to taste.
     */
    protected int $lowStockThreshold = 10;

    public function index()
    {
        $today = Carbon::today();

        $todaySales = Sale::whereDate('sale_date', $today)->sum('total_amount');
        $ordersToday = Sale::whereDate('sale_date', $today)->count();
        $avgOrderValue = $ordersToday > 0 ? $todaySales / $ordersToday : 0;
        $lowStockCount = $this->lowStockCount();

        $days = $this->weeklySalesChart($today);
        $topProducts = $this->topProducts($today);
        $orders = $this->recentOrders();

        return view('dashboard', [
            'todaySales' => $todaySales,
            'ordersToday' => $ordersToday,
            'avgOrderValue' => $avgOrderValue,
            'lowStockCount' => $lowStockCount,
            'days' => $days,
            'topProducts' => $topProducts,
            'orders' => $orders,
        ]);
    }

    /**
     * Count of products whose latest inventory ledger balance is below
     * the low-stock threshold (summed across all warehouses).
     */
    protected function lowStockCount(): int
    {
        $latestPerProductWarehouse = InventoryLedger::select('product_id', 'warehouse_id', DB::raw('MAX(id) as last_id'))
            ->groupBy('product_id', 'warehouse_id');

        $balances = InventoryLedger::joinSub($latestPerProductWarehouse, 'latest', function ($join) {
                $join->on('inventory_ledgers.id', '=', 'latest.last_id');
            })
            ->select('inventory_ledgers.product_id', DB::raw('SUM(inventory_ledgers.balance) as total_balance'))
            ->groupBy('inventory_ledgers.product_id')
            ->get();

        return $balances->filter(fn ($row) => $row->total_balance < $this->lowStockThreshold)->count();
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
     * Top 5 products by units sold today.
     */
    protected function topProducts(Carbon $today): array
    {
        $colors = ['brand', 'warn', 'good', 'bad'];

        return SaleDetail::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->whereDate('sales.sale_date', $today)
            ->selectRaw('products.id, products.name, SUM(sale_details.qty) as sold, SUM(sale_details.qty * sale_details.price) as revenue')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('sold')
            ->limit(5)
            ->get()
            ->map(function ($row, $i) use ($colors) {
                return [
                    'icon' => 'fa-box',
                    'bg' => $colors[$i % count($colors)],
                    'name' => $row->name,
                    'sold' => (int) $row->sold,
                    'revenue' => (float) $row->revenue,
                ];
            })
            ->all();
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
