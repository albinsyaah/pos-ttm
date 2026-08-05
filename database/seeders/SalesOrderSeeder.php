<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SalesOrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::pluck('id')->all();
        $products = Product::with('priceSetups')->get();
        $startDate = Carbon::create(2024, 1, 8);

        for ($i = 1; $i <= 24; $i++) {
            $orderDate = (clone $startDate)->addDays(($i - 1) * 3);
            $status = $i <= 20 ? 'Fulfilled' : 'Pending';

            $so = SalesOrder::firstOrCreate(
                ['so_number' => 'SO-'.$orderDate->format('Ym').'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)],
                [
                    'order_date' => $orderDate,
                    'status' => $status,
                    'customer_id' => fake()->randomElement($customers),
                ]
            );

            if ($so->salesOrderDetails()->exists()) {
                continue;
            }

            $lineItems = $products->random(fake()->numberBetween(2, 6));

            foreach ($lineItems as $product) {
                $retailPrice = $product->priceSetups
                    ->firstWhere('price_category', 'Retail')
                    ?->amount ?? 50000;

                SalesOrderDetail::create([
                    'qty' => fake()->numberBetween(1, 20),
                    'price' => $retailPrice,
                    'sales_order_id' => $so->id,
                    'product_id' => $product->id,
                ]);
            }
        }
    }
}
