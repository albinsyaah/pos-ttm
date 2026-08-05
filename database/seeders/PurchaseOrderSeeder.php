<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDetail;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::pluck('id')->all();
        $products = Product::all();
        $startDate = Carbon::create(2024, 1, 5);

        for ($i = 1; $i <= 20; $i++) {
            $orderDate = (clone $startDate)->addDays(($i - 1) * 4);
            // The first 16 POs have been fully or partially received;
            // the last few are still open, giving Purchase seeder and
            // the UI something realistic to show as "Pending".
            $status = $i <= 16 ? 'Received' : 'Pending';

            $po = PurchaseOrder::firstOrCreate(
                ['po_number' => 'PO-'.$orderDate->format('Ym').'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)],
                [
                    'order_date' => $orderDate,
                    'status' => $status,
                    'supplier_id' => fake()->randomElement($suppliers),
                ]
            );

            if ($po->purchaseOrderDetails()->exists()) {
                continue;
            }

            $lineItems = $products->random(fake()->numberBetween(2, 5));

            foreach ($lineItems as $product) {
                PurchaseOrderDetail::create([
                    'qty' => fake()->numberBetween(20, 100),
                    'price' => $this->estimatedCost($product->code),
                    'purchase_order_id' => $po->id,
                    'product_id' => $product->id,
                ]);
            }
        }
    }

    private function estimatedCost(string $code): float
    {
        $base = match (substr($code, 0, 3)) {
            'FRT' => 220000,
            'PST' => 70000,
            'SED' => 50000,
            'TLS' => 95000,
            'FED' => 290000,
            'MED' => 35000,
            default => 80000,
        };

        $variance = fake()->numberBetween(-8, 8) / 100;

        return round(($base * (1 + $variance)) / 500) * 500;
    }
}
