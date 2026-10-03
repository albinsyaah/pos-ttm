<?php

namespace App\Services;

use App\Models\ApPayment;
use App\Models\ArPayment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalesReturn;
use App\Models\SalesReturnDetail;
use Illuminate\Database\Eloquent\Builder;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Numbers behind the dashboard and the sales reports.
 *
 * Cost of a free item ("barang gratis", a sale line priced at Rp0): its cost
 * is the last purchase price of that product. "Last" means the most recent
 * received purchase on or before the sale date, so an old sale keeps the
 * cost that was true then. When the product was only bought after the sale
 * date, the earliest known purchase price is used. A product that was never
 * bought has cost 0 and is flagged so the report can say so.
 *
 * Sales are reported net of sales returns. In a period (dashboard) a return counts on the day
 * it was made. In a report that lists invoices, a return is netted against its own invoice.
 *
 * Income per payment method: cash sales (paid on the spot, with the method
 * picked at the till) plus receivable payments (money coming in for credit
 * sales), minus refunds of cash sales returned by the same method. A credit sale is not income until the customer pays it. Supplier
 * payments are reported alongside as money going out, never mixed in.
 */
class SalesInsightService
{
    public const RANGES = ['day', 'week', 'month'];

    public const DEFAULT_RANGE = 'day';

    /** Clamp a user-supplied range name to a known one. */
    public static function normalizeRange(?string $range): string
    {
        return in_array($range, self::RANGES, true) ? $range : self::DEFAULT_RANGE;
    }

    /**
     * First and last day (Y-m-d) of the day, week (Monday to Sunday) or
     * month that contains $now.
     *
     * @return array{0: string, 1: string}
     */
    public static function bounds(string $range, ?CarbonInterface $now = null): array
    {
        $now = Carbon::instance(($now ?? Carbon::today())->toDateTime());

        return match (self::normalizeRange($range)) {
            'week' => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()],
            'month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            default => [$now->toDateString(), $now->toDateString()],
        };
    }

    /**
     * Sales between two dates, both inclusive, net of the sales returns made in the same days.
     *
     * @return array{count: int, gross: float, returns: float, total: float}
     */
    public function salesTotal(string $from, string $to): array
    {
        $row = Sale::query()
            ->whereDate('sale_date', '>=', $from)
            ->whereDate('sale_date', '<=', $to)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(total_amount), 0) as total')
            ->first();

        $returns = $this->returnsTotal($from, $to);

        return [
            'count' => (int) $row->n,
            'gross' => (float) $row->total,
            'returns' => $returns,
            'total' => (float) $row->total - $returns,
        ];
    }

    /** Value of the sales returns made between two dates, both inclusive. */
    public function returnsTotal(string $from, string $to): float
    {
        return (float) SalesReturn::query()
            ->whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $to)
            ->sum('total_amount');
    }

    /** Sales returns per day (Y-m-d => value) between two dates, both inclusive. */
    public function returnsByDay(string $from, string $to): array
    {
        return SalesReturn::query()
            ->whereDate('return_date', '>=', $from)
            ->whereDate('return_date', '<=', $to)
            ->selectRaw('DATE(return_date) as day, SUM(total_amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * Quantity returned per paid sale line. A return names the sale and the product, not the
     * line, so it is put on the first paid line of that product in that sale. Free lines are
     * never reduced by a return. Use as a left join on sale_details.id = line_id.
     */
    public static function returnedPerPaidLine(): \Illuminate\Database\Query\Builder
    {
        $firstPaidLine = DB::table('sale_details')
            ->where('price', '>', 0)
            ->selectRaw('sale_id, product_id, MIN(id) as line_id')
            ->groupBy('sale_id', 'product_id');

        return DB::table('sales_return_details as srd')
            ->join('sales_returns as sr', 'sr.id', '=', 'srd.sales_return_id')
            ->joinSub($firstPaidLine, 'fp', function ($join) {
                $join->on('fp.sale_id', '=', 'sr.sale_id')->on('fp.product_id', '=', 'srd.product_id');
            })
            ->selectRaw('fp.line_id, SUM(srd.qty) as rqty')
            ->groupBy('fp.line_id');
    }

    /**
     * Returns that belong to the sales a query matches, for reports that list invoices.
     *
     * @param  Builder  $salesQuery  a query on sales (filters already applied)
     * @return array{total: float, by_source: array<string, float>}
     */
    public function returnsForSales(Builder $salesQuery): array
    {
        $rows = SalesReturn::query()
            ->join('sales', 'sales.id', '=', 'sales_returns.sale_id')
            ->whereIn('sales_returns.sale_id', (clone $salesQuery)->reorder()->select('sales.id'))
            ->selectRaw('sales.source, COALESCE(SUM(sales_returns.total_amount), 0) as total')
            ->groupBy('sales.source')
            ->pluck('total', 'source')
            ->map(fn ($v) => (float) $v);

        return ['total' => (float) $rows->sum(), 'by_source' => $rows->all()];
    }

    /**
     * Best-selling products by units sold in a date range, net of the units returned in the
     * same range. Only paid lines count: a free gift is not a sale that "sold well", and a
     * returned free item does not reduce a paid sale.
     *
     * @return array<int, array{product: Product, sold: int, revenue: float}>
     */
    public function topProducts(string $from, string $to, int $limit = 10): array
    {
        $sold = SaleDetail::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereDate('sales.sale_date', '>=', $from)
            ->whereDate('sales.sale_date', '<=', $to)
            ->where('sale_details.price', '>', 0)
            ->selectRaw('sale_details.product_id, SUM(sale_details.qty) as sold, SUM(sale_details.qty * sale_details.price) as revenue')
            ->groupBy('sale_details.product_id')
            ->get()
            ->keyBy('product_id');

        $paidPrice = DB::table('sale_details')
            ->where('price', '>', 0)
            ->selectRaw('sale_id, product_id, MIN(id) as line_id, AVG(price) as price')
            ->groupBy('sale_id', 'product_id');

        $returned = SalesReturnDetail::query()
            ->join('sales_returns', 'sales_returns.id', '=', 'sales_return_details.sales_return_id')
            ->joinSub($paidPrice, 'fp', function ($join) {
                $join->on('fp.sale_id', '=', 'sales_returns.sale_id')->on('fp.product_id', '=', 'sales_return_details.product_id');
            })
            ->whereDate('sales_returns.return_date', '>=', $from)
            ->whereDate('sales_returns.return_date', '<=', $to)
            ->selectRaw('sales_return_details.product_id, SUM(sales_return_details.qty) as qty, SUM(sales_return_details.qty * fp.price) as value')
            ->groupBy('sales_return_details.product_id')
            ->get()
            ->keyBy('product_id');

        $net = [];
        foreach ($sold->keys()->merge($returned->keys())->unique() as $productId) {
            $units = (int) ($sold[$productId]->sold ?? 0) - (int) ($returned[$productId]->qty ?? 0);

            if ($units > 0) {
                $net[$productId] = [
                    'sold' => $units,
                    'revenue' => (float) ($sold[$productId]->revenue ?? 0) - (float) ($returned[$productId]->value ?? 0),
                ];
            }
        }

        uksort($net, fn ($a, $b) => [$net[$b]['sold'], $a] <=> [$net[$a]['sold'], $b]);
        $net = array_slice($net, 0, $limit, true);

        $products = Product::whereIn('id', array_keys($net))->get()->keyBy('id');

        return collect($net)
            ->filter(fn ($row, $productId) => $products->has($productId))
            ->map(fn ($row, $productId) => ['product' => $products[$productId]] + $row)
            ->values()
            ->all();
    }

    /**
     * Money in (and out) per payment method between two dates, inclusive.
     * Both dates are optional; leave them null for all time.
     *
     * Rows are sorted by total income, largest first. A method with no
     * movement is listed only while it is active, so a new method shows up
     * at zero but a retired one does not clutter the page.
     *
     * @return array<int, array{id: int, name: string, is_active: bool, sales_count: int, cash_sales: float, ar_count: int, ar_received: float, returns_count: int, returns: float, income: float, ap_paid: float}>
     */
    public function incomeByMethod(?string $from = null, ?string $to = null, ?int $onlyMethodId = null): array
    {
        $methods = PaymentMethod::orderBy('id')->get()->keyBy('id');
        $defaultCashId = PaymentMethod::defaultCash()?->id;

        $rows = [];
        foreach ($methods as $method) {
            $rows[$method->id] = [
                'id' => $method->id,
                'name' => $method->name,
                'is_active' => (bool) $method->is_active,
                'sales_count' => 0,
                'cash_sales' => 0.0,
                'ar_count' => 0,
                'ar_received' => 0.0,
                'returns_count' => 0,
                'returns' => 0.0,
                'income' => 0.0,
                'ap_paid' => 0.0,
            ];
        }

        // Cash sales. A cash sale saved without a method counts as the default cash method.
        $cashSales = Sale::query()
            ->where('payment_type', Sale::PAYMENT_CASH)
            ->when($from, fn ($q) => $q->whereDate('sale_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sale_date', '<=', $to))
            ->selectRaw('payment_method_id, COUNT(*) as n, COALESCE(SUM(total_amount), 0) as total')
            ->groupBy('payment_method_id')
            ->get();

        foreach ($cashSales as $row) {
            $id = $row->payment_method_id ?? $defaultCashId;
            if ($id === null || ! isset($rows[$id])) {
                continue;
            }
            $rows[$id]['sales_count'] += (int) $row->n;
            $rows[$id]['cash_sales'] += (float) $row->total;
        }

        // Refunds: a return of a cash sale gives the money back by the method the sale was paid with.
        // (A return of a credit sale lowers the receivable instead; no cash moves.)
        $refunds = SalesReturn::query()
            ->join('sales', 'sales.id', '=', 'sales_returns.sale_id')
            ->where('sales.payment_type', Sale::PAYMENT_CASH)
            ->when($from, fn ($q) => $q->whereDate('sales_returns.return_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sales_returns.return_date', '<=', $to))
            ->selectRaw('sales.payment_method_id, COUNT(*) as n, COALESCE(SUM(sales_returns.total_amount), 0) as total')
            ->groupBy('sales.payment_method_id')
            ->get();

        foreach ($refunds as $row) {
            $id = $row->payment_method_id ?? $defaultCashId;
            if ($id === null || ! isset($rows[$id])) {
                continue;
            }
            $rows[$id]['returns_count'] += (int) $row->n;
            $rows[$id]['returns'] += (float) $row->total;
        }

        // Receivable payments (customers paying off credit sales).
        $arPayments = ArPayment::query()
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->selectRaw('payment_method_id, COUNT(*) as n, COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method_id')
            ->get();

        foreach ($arPayments as $row) {
            if ($row->payment_method_id === null || ! isset($rows[$row->payment_method_id])) {
                continue;
            }
            $rows[$row->payment_method_id]['ar_count'] += (int) $row->n;
            $rows[$row->payment_method_id]['ar_received'] += (float) $row->total;
        }

        // Supplier payments: money out, shown beside the income, never added to it.
        $apPayments = ApPayment::query()
            ->when($from, fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->selectRaw('payment_method_id, COALESCE(SUM(amount), 0) as total')
            ->groupBy('payment_method_id')
            ->get();

        foreach ($apPayments as $row) {
            if ($row->payment_method_id === null || ! isset($rows[$row->payment_method_id])) {
                continue;
            }
            $rows[$row->payment_method_id]['ap_paid'] += (float) $row->total;
        }

        foreach ($rows as $id => &$row) {
            $row['income'] = $row['cash_sales'] + $row['ar_received'] - $row['returns'];
        }
        unset($row);

        return collect($rows)
            ->filter(function ($row) use ($onlyMethodId) {
                if ($onlyMethodId !== null) {
                    return $row['id'] === $onlyMethodId;
                }

                return $row['is_active'] || $row['income'] != 0 || $row['ap_paid'] > 0;
            })
            ->sortByDesc('income')
            ->values()
            ->all();
    }

    // ---- Cost of free items --------------------------------------------------------------

    /**
     * Purchase price history per product, oldest first:
     * [product_id => [['date' => 'Y-m-d', 'price' => float], ...]].
     * Only received purchases count.
     */
    public function purchaseCostBook(iterable $productIds): array
    {
        $ids = collect($productIds)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return PurchaseDetail::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
            ->whereIn('purchase_details.product_id', $ids)
            ->whereIn('purchases.status', Purchase::PAYABLE_STATUSES)
            ->orderBy('purchases.purchase_date')
            ->orderBy('purchases.id')
            ->orderBy('purchase_details.id')
            ->get(['purchase_details.product_id', 'purchase_details.price', 'purchases.purchase_date'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'date' => Carbon::parse($r->purchase_date)->toDateString(),
                'price' => (float) $r->price,
            ])->values()->all())
            ->all();
    }

    /**
     * Unit cost of a product on a date, from a book made by purchaseCostBook().
     * Returns null when the product has never been bought.
     */
    public function costOn(array $book, int $productId, string $date): ?float
    {
        $entries = $book[$productId] ?? [];
        if ($entries === []) {
            return null;
        }

        $cost = null;
        foreach ($entries as $entry) {
            if ($entry['date'] > $date) {
                break;
            }
            $cost = $entry['price'];
        }

        return $cost ?? $entries[0]['price'];
    }

    /**
     * Cost ("kerugian") of the free lines of the sales a query matches.
     *
     * @param  Builder  $salesQuery  a query on sales (filters already applied)
     * @return array{
     *     total: float,
     *     by_sale: array<int, float>,
     *     by_product: array<int, array{product: ?Product, qty: int, loss: float, unknown_cost: bool}>
     * }
     */
    public function freeGoodsLoss(Builder $salesQuery): array
    {
        $lines = SaleDetail::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereIn('sale_details.sale_id', (clone $salesQuery)->reorder()->select('sales.id'))
            ->where('sale_details.price', '<=', 0)
            ->get(['sale_details.sale_id', 'sale_details.product_id', 'sale_details.qty', 'sales.sale_date']);

        if ($lines->isEmpty()) {
            return ['total' => 0.0, 'by_sale' => [], 'by_product' => []];
        }

        $book = $this->purchaseCostBook($lines->pluck('product_id'));

        $total = 0.0;
        $bySale = [];
        $byProduct = [];

        foreach ($lines as $line) {
            $date = Carbon::parse($line->sale_date)->toDateString();
            $cost = $this->costOn($book, (int) $line->product_id, $date);
            $loss = (int) $line->qty * ($cost ?? 0.0);

            $total += $loss;
            $bySale[$line->sale_id] = ($bySale[$line->sale_id] ?? 0.0) + $loss;

            $byProduct[$line->product_id] ??= ['qty' => 0, 'loss' => 0.0, 'unknown_cost' => false];
            $byProduct[$line->product_id]['qty'] += (int) $line->qty;
            $byProduct[$line->product_id]['loss'] += $loss;
            $byProduct[$line->product_id]['unknown_cost'] = $byProduct[$line->product_id]['unknown_cost'] || $cost === null;
        }

        $products = Product::whereIn('id', array_keys($byProduct))->get()->keyBy('id');

        $rows = collect($byProduct)
            ->map(fn ($row, $productId) => ['product' => $products->get($productId)] + $row)
            ->sortByDesc('loss')
            ->values()
            ->all();

        return ['total' => $total, 'by_sale' => $bySale, 'by_product' => $rows];
    }
}
