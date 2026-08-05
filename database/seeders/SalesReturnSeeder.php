<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\SalesReturnDetail;
use Database\Seeders\Concerns\ManagesInventoryLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class SalesReturnSeeder extends Seeder
{
    use ManagesInventoryLedger;

    public function run(): void
    {
        $sales = Sale::with('saleDetails')
            ->orderBy('id')
            ->get()
            ->filter(fn ($sale, $index) => $index % 5 === 0);

        foreach ($sales as $sale) {
            if ($sale->saleDetails->isEmpty()) {
                continue;
            }

            $returnDate = Carbon::parse($sale->sale_date)->addDays(fake()->numberBetween(1, 7));
            $detailsToReturn = $sale->saleDetails->random(min(2, $sale->saleDetails->count()));

            $totalAmount = 0;
            $lines = [];

            foreach ($detailsToReturn as $detail) {
                $returnQty = min($detail->qty, fake()->numberBetween(1, max(1, intdiv($detail->qty, 2))));
                if ($returnQty < 1) {
                    continue;
                }
                $totalAmount += $returnQty * $detail->price;
                $lines[] = ['detail' => $detail, 'qty' => $returnQty];
            }

            if (empty($lines)) {
                continue;
            }

            $salesReturn = SalesReturn::create([
                'return_number' => 'SRET-'.$returnDate->format('Ym').'-'.str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT),
                'return_date' => $returnDate,
                'total_amount' => $totalAmount,
                'sale_id' => $sale->id,
            ]);

            foreach ($lines as $line) {
                SalesReturnDetail::create([
                    'qty' => $line['qty'],
                    'sales_return_id' => $salesReturn->id,
                    'product_id' => $line['detail']->product_id,
                ]);

                // Returned goods go back on the shelf.
                $this->moveStock(
                    productId: $line['detail']->product_id,
                    warehouseId: $sale->warehouse_id,
                    type: 'IN',
                    qty: $line['qty'],
                    referenceNumber: $salesReturn->return_number,
                    date: $returnDate,
                );
            }
        }
    }
}
