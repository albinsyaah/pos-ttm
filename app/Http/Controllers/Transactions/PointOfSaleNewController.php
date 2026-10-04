<?php

namespace App\Http\Controllers\Transactions;

use App\Http\Controllers\Concerns\SyncsSaleStock;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Services\PriceService;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PointOfSaleNewController extends Controller implements HasMiddleware
{
    use SyncsSaleStock;

    /**
     * Shares the 'sales' table with the Sales and Point of Sale pages
     * (see App\Http\Controllers\Transactions\PointOfSaleController::SOURCE).
     */
    public const SOURCE = 'pos';

    /**
     * The terminal comes in two modes that share this code. This class is the cashier ("kasir
     * biasa"): small receipt only, no driver name. PointOfSaleIndukController extends it for the
     * head cashier ("kasir induk"): driver name, large receipt and delivery note as well.
     * Each mode has its own routes and its own permission, so the Role screen decides who
     * may open which one.
     */
    public const MODE = 'cashier';

    public const ROUTE = 'transactions.point-of-sale-new';

    public const PERMISSION = 'transactions.point-of-sale-new';

    /** How many matches the product search returns at most. */
    private const SEARCH_LIMIT = 20;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:'.static::PERMISSION.'.view', only: ['index']),
            // The search feeds the terminal, so either terminal permission may use it.
            new Middleware('permission:'.static::PERMISSION.'.view|'.static::PERMISSION.'.manage', only: ['products']),
            new Middleware('permission:'.static::PERMISSION.'.manage', only: ['store']),
        ];
    }

    /**
     * Show the checkout terminal. This is a create-only screen — completed
     * transactions are managed afterwards on the Point of Sale list page.
     *
     * Products are no longer preloaded into the page: the cashier finds them
     * through products(). Only the cart of a refused submission is rebuilt
     * here, so a rejected sale (e.g. not enough stock) does not lose the cart.
     */
    public function index(PriceService $prices, StockService $stock)
    {
        $paymentMethods = PaymentMethod::active()->orderBy('id')->get();

        return view('transactions.point-of-sale-new.index', [
            'terminalMode' => static::MODE,
            'routePrefix' => static::ROUTE,
            'customers' => Customer::orderBy('name')->get(),
            'salesmen' => Employee::orderBy('name')->get(),
            'paymentMethods' => $paymentMethods,
            // Preselect what the cashier chose before a refused submission, else Tunai.
            'selectedMethodId' => (int) (old('payment_method_id') ?: ($paymentMethods->firstWhere('is_cash', true)?->id ?? 0)),
            'cartSeed' => $this->cartSeed((array) old('items', []), $prices, $stock),
        ]);
    }

    /**
     * Live product search for the cashier (JSON).
     *
     * Matches on product name (and code), and reports the stock on hand in all
     * warehouses together (the sale is served from whichever warehouses have it)
     * plus the dated Retail prices so the page can pick the reference price
     * (harga patokan) for the sale date.
     */
    public function products(Request $request, PriceService $prices, StockService $stock): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        if ($term === '') {
            return response()->json(['data' => []]);
        }

        $products = Product::query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get();

        $ids = $products->pluck('id');
        $stocks = $stock->totalAvailableMany($ids);
        $book = $prices->priceBook($ids);

        return response()->json([
            'data' => $products
                ->map(fn (Product $product) => $this->productPayload(
                    $product,
                    $stocks[$product->id] ?? 0,
                    $book[$product->id] ?? [],
                ))
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'invoice_number' => ['nullable', 'string', 'max:100', 'unique:sales,invoice_number'],
            'sale_date' => ['required', 'date'],
            'payment_type' => ['nullable', Rule::in([Sale::PAYMENT_CASH, Sale::PAYMENT_CREDIT])],
            // How a paid-on-the-spot sale is settled (Tunai, Transfer, QRIS, ...). Credit sales have none yet.
            'payment_method_id' => [
                'nullable',
                Rule::exists('payment_methods', 'id')->where('is_active', true),
            ],
            // A credit sale becomes a receivable, which only exists for a registered customer.
            'customer_id' => ['nullable', 'required_if:payment_type,'.Sale::PAYMENT_CREDIT, 'exists:customers,id'],
            'salesman_id' => ['nullable', 'exists:employees,id'],
            // Printed on the delivery note (surat jalan).
            'driver_name' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
        ], [
            'customer_id.required_if' => __('app.point_of_sale_new.credit_requires_customer'),
        ]);

        $items = array_map(fn ($item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'price' => $item['price'],
        ], $data['items']);

        $this->assertNoDuplicateProducts($items);

        $totalAmount = collect($items)->sum(fn ($item) => $item['qty'] * $item['price']);
        $paymentType = $data['payment_type'] ?? Sale::PAYMENT_CASH;

        // Only a sale paid right away has a method; a credit sale (piutang) is
        // settled later through a receivable payment, which records its own.
        // A cash sale that names none is treated as Tunai.
        $paymentMethodId = $paymentType === Sale::PAYMENT_CASH
            ? ($data['payment_method_id'] ?? PaymentMethod::defaultCash()?->id)
            : null;

        $sale = DB::transaction(function () use ($data, $items, $totalAmount, $paymentType, $paymentMethodId) {
            $sale = Sale::create([
                'invoice_number' => $data['invoice_number'] ?? null,
                'sale_date' => $data['sale_date'],
                'total_amount' => $totalAmount,
                'source' => self::SOURCE,
                'payment_type' => $paymentType,
                'payment_method_id' => $paymentMethodId,
                'sales_order_id' => null,
                'customer_id' => $data['customer_id'] ?? null,
                'salesman_id' => $data['salesman_id'] ?? null,
                // No warehouse of its own: the stock is taken from whichever warehouses have it.
                'warehouse_id' => null,
                // Only the head cashier's terminal records a driver; the cashier's has no such field.
                'driver_name' => static::MODE === 'head' && filled($data['driver_name'] ?? null) ? trim($data['driver_name']) : null,
            ]);

            $sale->saleDetails()->createMany($items);

            // Take the items out of the warehouses that have them, most stock first; refused
            // (and rolled back) if all warehouses together are short. Same product on two
            // lines is added up.
            $this->syncSaleStockFromAllWarehouses($sale, $items);

            return $sale;
        });

        // Stay on the terminal (fresh cart) so the cashier can ring up the
        // next transaction immediately, rather than bouncing to a list page.
        // The id lets the terminal offer a "print receipt" link for this sale.
        return redirect()->route(static::ROUTE.'.index')
            ->with('success', __('Transaction :number completed successfully.', ['number' => $sale->invoice_number]))
            ->with('printed_sale_id', $sale->id);
    }

    /**
     * A product may appear at most twice in one sale: once at a price and once
     * as a free item (Rp0). More than one line of the same kind is refused,
     * so the cashier has to add the quantities up on a single line.
     *
     * @param  array<int, array{product_id: int|string, qty: int|string, price: int|float|string}>  $items
     */
    private function assertNoDuplicateProducts(array $items): void
    {
        $paid = [];
        $free = [];
        $duplicatePaid = [];
        $duplicateFree = [];

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $isFree = round((float) $item['price'], 2) <= 0;

            if ($isFree) {
                if (isset($free[$productId])) {
                    $duplicateFree[$productId] = true;
                }
                $free[$productId] = true;
            } else {
                if (isset($paid[$productId])) {
                    $duplicatePaid[$productId] = true;
                }
                $paid[$productId] = true;
            }
        }

        if ($duplicatePaid === [] && $duplicateFree === []) {
            return;
        }

        $names = Product::whereIn('id', array_keys($duplicatePaid + $duplicateFree))->pluck('name', 'id');
        $messages = [];

        foreach (array_keys($duplicatePaid) as $productId) {
            $messages[] = __('app.point_of_sale_new.duplicate_paid', ['product' => $names[$productId] ?? $productId]);
        }
        foreach (array_keys($duplicateFree) as $productId) {
            $messages[] = __('app.point_of_sale_new.duplicate_free', ['product' => $names[$productId] ?? $productId]);
        }

        throw ValidationException::withMessages(['items' => $messages]);
    }

    /**
     * Rebuild the cart rows of a refused submission (empty on a fresh visit).
     *
     * @param  array<int, mixed>  $oldItems
     * @return array<int, array<string, mixed>>
     */
    private function cartSeed(array $oldItems, PriceService $prices, StockService $stock): array
    {
        $rows = collect($oldItems)->filter(fn ($item) => is_array($item) && ! empty($item['product_id']));
        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('product_id')->map(fn ($id) => (int) $id)->unique();
        $products = Product::whereIn('id', $ids)->get()->keyBy('id');
        $stocks = $stock->totalAvailableMany($ids);
        $book = $prices->priceBook($ids);

        return $rows
            ->filter(fn ($item) => $products->has((int) $item['product_id']))
            ->map(function ($item) use ($products, $stocks, $book) {
                $product = $products[(int) $item['product_id']];

                return $this->productPayload($product, $stocks[$product->id] ?? 0, $book[$product->id] ?? [])
                    + [
                        'qty' => (int) ($item['qty'] ?? 1),
                        'price' => isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null,
                    ];
            })
            ->values()
            ->all();
    }

    /**
     * What the page needs to know about a product.
     *
     * @param  array<int, array{date: string, amount: float}>  $prices  dated Retail prices, newest first
     * @return array<string, mixed>
     */
    private function productPayload(Product $product, int $stock, array $prices): array
    {
        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'unit' => $product->unit_name ?: 'pcs',
            'stock' => $stock,
            'stock_label' => $product->formatQuantity($stock),
            'prices' => $prices,
        ];
    }
}
