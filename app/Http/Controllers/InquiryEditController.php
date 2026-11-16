<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Transactions\DeviationController;
use App\Models\InternalMutation;
use App\Models\PriceHistory;
use App\Models\PriceSetup;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\PriceService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Add / edit from the Inquiry page (permission inquiry.manage): the data, the
 * prices and the stock per warehouse of a product, in one form.
 *
 * The product itself is always picked from the products list (it is created
 * on the Products page), never typed: the name cannot be changed here.
 *
 *  - Product data (code, unit, brand, item type) is saved on the product.
 *    A blank code keeps the current one. Pack / box settings are not part of
 *    this form and are never touched.
 *  - A price field that is filled in and different from the price in force on
 *    the effective date writes a price setup, and logs it in Riwayat Harga
 *    exactly like Setup Harga does. A blank field changes nothing. The purchase
 *    price (harga beli) is only handled for users who may also open pricing.
 *  - A stock field that differs from the warehouse's current stock writes a
 *    completed Deviation for the difference (the same record the stock opname
 *    page uses), so the change shows in the stock card and the deviation report
 *    and the ledger stays append-only. A blank field changes nothing.
 */
class InquiryEditController extends Controller
{
    /** Current data of a product, to fill the form once it is picked. */
    public function show(Request $request, Product $product)
    {
        $service = app(PriceService::class);
        $prices = [];

        foreach (self::PRICE_FIELDS as $field => $category) {
            if ($field === 'purchase' && ! $request->user()?->can('pricing.view')) {
                continue;
            }

            $prices[$field] = $service->currentPrices([$product->id], $category)[$product->id]['amount'] ?? null;
        }

        $stock = app(StockService::class);

        return response()->json([
            'code' => $product->code,
            'unit_name' => $product->unit_name,
            'brand_id' => $product->brand_id,
            'item_type_id' => $product->item_type_id,
            'prices' => $prices,
            'stock' => Warehouse::pluck('id')
                ->mapWithKeys(fn ($id) => [$id => $stock->available($product->id, (int) $id)])
                ->all(),
        ]);
    }

    /** "Add": the same as editing, for a product picked from the list. */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['product_id' => ['required', 'exists:products,id']]);

        return $this->update($request, Product::findOrFail($request->input('product_id')));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product->id);

        DB::transaction(function () use ($data, $request, $product) {
            $product->update($this->productAttributes($data));

            $this->savePrices($product, $data, $request);
            $this->saveStock($product, $data);
        });

        return back()->with('success', __('Product updated successfully.'));
    }

    protected function productAttributes(array $data): array
    {
        return [
            'code' => $data['code'] ?? null,
            'unit_name' => $data['unit_name'],
            'brand_id' => $data['brand_id'] ?? null,
            'item_type_id' => $data['item_type_id'] ?? null,
        ];
    }

    /** The price fields of the form and the category each one is stored under. */
    protected const PRICE_FIELDS = [
        'purchase' => PriceService::PURCHASE,
        'retail' => PriceService::RETAIL,
        'wholesale' => PriceService::WHOLESALE,
    ];

    /**
     * Writes the price fields that are filled in and differ from the price in
     * force on the effective date. A row for the same product, category and
     * date is corrected in place (so one day never holds two prices);
     * otherwise a new dated row is added and the older price stays in history.
     */
    protected function savePrices(Product $product, array $data, Request $request): void
    {
        $date = Carbon::parse($data['price_effective_date'] ?? now())->toDateString();
        $prices = $data['prices'] ?? [];

        // The purchase price reveals the margin: same rule as seeing it in Inquiry.
        if (! $request->user()?->can('pricing.view')) {
            unset($prices['purchase']);
        }

        foreach (self::PRICE_FIELDS as $field => $category) {
            $amount = $prices[$field] ?? null;

            if ($amount === null || $amount === '') {
                continue;
            }

            $amount = (float) $amount;
            $current = app(PriceService::class)->currentPrices([$product->id], $category, $date)[$product->id]['amount'] ?? null;

            if ($current !== null && abs($current - $amount) < 0.005) {
                continue;
            }

            $existing = PriceSetup::query()
                ->where('product_id', $product->id)
                ->where('price_category', $category)
                ->whereDate('effective_date', $date)
                ->orderByDesc('id')
                ->first();

            if ($existing) {
                $before = clone $existing;
                $existing->update(['amount' => $amount]);
                PriceHistory::record(PriceHistory::UPDATED, $before, $existing);

                continue;
            }

            $created = PriceSetup::create([
                'product_id' => $product->id,
                'price_category' => $category,
                'amount' => $amount,
                'effective_date' => $date,
            ]);
            PriceHistory::record(PriceHistory::CREATED, null, $created);
        }
    }

    /**
     * Brings each filled-in warehouse field to the typed quantity (in satuan)
     * with one completed Deviation for the difference.
     */
    protected function saveStock(Product $product, array $data): void
    {
        $stock = app(StockService::class);
        $today = now()->toDateString();

        foreach ($data['stock'] ?? [] as $warehouseId => $target) {
            if ($target === null || $target === '') {
                continue;
            }

            $warehouseId = (int) $warehouseId;
            $difference = (int) $target - $stock->available($product->id, $warehouseId);

            if ($difference === 0) {
                continue;
            }

            $deviation = InternalMutation::create([
                'type' => DeviationController::TYPE,
                'mutation_date' => $today,
                'status' => 'completed',
                'from_warehouse_id' => $warehouseId,
            ]);

            $deviation->internalMutationDetails()->create([
                'product_id' => $product->id,
                'qty' => $difference,
                'notes' => __('app.inquiry.stock_note'),
            ]);

            $stock->syncAdjustment(
                $deviation,
                $warehouseId,
                [['product_id' => $product->id, 'qty' => $difference]],
                $deviation->mutation_number,
                $today
            );
        }
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'];

        $validator = validator($request->all(), [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'code')->ignore($ignoreId)],
            'unit_name' => ['required', 'string', 'max:30'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'item_type_id' => ['nullable', 'exists:item_types,id'],
            'prices' => ['nullable', 'array'],
            'prices.purchase' => $money,
            'prices.retail' => $money,
            'prices.wholesale' => $money,
            'price_effective_date' => ['nullable', 'date'],
            'stock' => ['nullable', 'array'],
            'stock.*' => ['nullable', 'integer', 'min:0', 'max:2000000000'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $known = Warehouse::pluck('id')->map(fn ($id) => (string) $id)->all();

            foreach (array_keys((array) $request->input('stock', [])) as $warehouseId) {
                if (! in_array((string) $warehouseId, $known, true)) {
                    $validator->errors()->add('stock', __('validation.exists', ['attribute' => 'warehouse']));
                }
            }
        });

        return $validator->validate();
    }
}
