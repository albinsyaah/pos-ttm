<?php

namespace Database\Seeders;

use App\Models\PriceSetup;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PriceSetupSeeder extends Seeder
{
    /**
     * Rough base price per product code prefix, in Rupiah, to keep
     * generated prices in a believable range for each category.
     */
    private const BASE_PRICE_BY_PREFIX = [
        'FRT' => 250000, // fertilizer sacks
        'PST' => 85000,  // pesticides
        'SED' => 60000,  // seeds
        'TLS' => 120000, // tools
        'FED' => 320000, // animal feed sacks
        'MED' => 45000,  // growing media
    ];

    public function run(): void
    {
        $effectiveDate = Carbon::create(2024, 1, 1);

        Product::all()->each(function (Product $product) use ($effectiveDate) {
            $prefix = strtoupper(substr($product->code, 0, 3));
            $base = self::BASE_PRICE_BY_PREFIX[$prefix] ?? 100000;

            // Slight per-product variance so not every item in a category
            // has an identical price.
            $variance = fake()->numberBetween(-10, 15) / 100;
            $retail = round(($base * (1 + $variance)) / 500) * 500;
            $wholesale = round(($retail * 0.9) / 500) * 500;

            PriceSetup::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'price_category' => 'Retail',
                    'effective_date' => $effectiveDate,
                ],
                ['amount' => $retail]
            );

            PriceSetup::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'price_category' => 'Grosir',
                    'effective_date' => $effectiveDate,
                ],
                ['amount' => $wholesale]
            );
        });
    }
}
