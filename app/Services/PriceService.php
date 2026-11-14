<?php

namespace App\Services;

use App\Models\PriceSetup;
use Illuminate\Support\Carbon;

/**
 * Looks up the reference selling price ("harga patokan").
 *
 * A price applies to ALL stock of a product from its effective date until a
 * newer effective date replaces it (not per purchase batch). Per product and
 * category, the row with the latest effective_date that is on or before the
 * reference date wins; ties go to the newest row. A price dated in the future
 * is ignored until its date arrives.
 */
class PriceService
{
    public const RETAIL = 'Retail';
    public const WHOLESALE = 'Grosir';
    /** Harga beli: what the shop pays its suppliers. Sensitive: it reveals the margin. */
    public const PURCHASE = 'Beli';

    /** The standard categories offered when setting up a price. Free-text categories (e.g. "Member") still work. */
    public const CATEGORIES = [self::RETAIL, self::WHOLESALE, self::PURCHASE];

    /** Spellings (lower case) that all mean one standard category. */
    private const ALIASES = [
        'retail' => self::RETAIL,
        'grosir' => self::WHOLESALE,
        'wholesale' => self::WHOLESALE,
        'beli' => self::PURCHASE,
        'purchase' => self::PURCHASE,
        'harga beli' => self::PURCHASE,
    ];

    /**
     * The standard name for a typed category ("beli", "Purchase" => "Beli");
     * anything that is not a known spelling is returned trimmed, unchanged.
     */
    public static function canonicalCategory(string $category): string
    {
        $category = trim($category);

        return self::ALIASES[mb_strtolower($category)] ?? $category;
    }

    /**
     * Name shown to people for a category: "Retail" is shown as Sell Price /
     * Harga Jual (the stored value stays "Retail"). Categories without a
     * translation (e.g. "Member") are shown as they are.
     */
    public static function label(string $category): string
    {
        $key = 'app.price_categories.'.self::canonicalCategory($category);
        $label = __($key);

        return $label === $key ? $category : $label;
    }

    /** Every lower-case spelling that means the given standard category. */
    public static function aliasesOf(string $canonical): array
    {
        return array_keys(array_filter(self::ALIASES, fn ($c) => $c === $canonical));
    }

    /**
     * Current purchase price per product: [product_id => amount].
     * Products with no purchase price are left out.
     */
    public function purchasePriceMap(iterable $productIds, $asOf = null): array
    {
        return collect($this->currentPrices($productIds, self::PURCHASE, $asOf))
            ->map(fn ($price) => $price['amount'])
            ->all();
    }

    /**
     * Price per product on a date: [product_id => ['amount' => float, 'effective_date' => 'Y-m-d']].
     * Products with no applicable price are left out.
     */
    public function currentPrices(iterable $productIds, string $category = self::RETAIL, $asOf = null): array
    {
        $ids = collect($productIds)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $date = Carbon::parse($asOf ?? now())->toDateString();

        return PriceSetup::query()
            ->whereIn('product_id', $ids)
            ->where('price_category', $category)
            ->whereDate('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get(['id', 'product_id', 'amount', 'effective_date'])
            ->groupBy('product_id')
            ->map(fn ($rows) => [
                'amount' => (float) $rows->first()->amount,
                'effective_date' => Carbon::parse($rows->first()->effective_date)->toDateString(),
            ])
            ->all();
    }

    /**
     * Every dated price of a category per product, newest first, so a page can
     * pick the right one for any sale date without another request:
     * [product_id => [['date' => 'Y-m-d', 'amount' => float], ...]].
     */
    public function priceBook(iterable $productIds, string $category = self::RETAIL): array
    {
        $ids = collect($productIds)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return PriceSetup::query()
            ->whereIn('product_id', $ids)
            ->where('price_category', $category)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get(['product_id', 'amount', 'effective_date'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'date' => Carbon::parse($r->effective_date)->toDateString(),
                'amount' => (float) $r->amount,
            ])->values()->all())
            ->all();
    }
}
