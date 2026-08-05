<?php

namespace Database\Seeders;

use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnDetail;
use Database\Seeders\Concerns\ManagesInventoryLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PurchaseReturnSeeder extends Seeder
{
    use ManagesInventoryLedger;

    private const REASONS = [
        'Barang cacat/rusak',
        'Kemasan robek',
        'Salah kirim produk',
        'Kualitas tidak sesuai pesanan',
        'Kadaluarsa',
    ];

    public function run(): void
    {
        // Only a subset of purchases end up with a return, same as a real store.
        $purchases = Purchase::with('purchaseDetails')
            ->orderBy('id')
            ->get()
            ->filter(fn ($purchase, $index) => $index % 4 === 0);

        foreach ($purchases as $purchase) {
            if ($purchase->purchaseDetails->isEmpty()) {
                continue;
            }

            $returnDate = Carbon::parse($purchase->purchase_date)->addDays(fake()->numberBetween(1, 5));
            $detailsToReturn = $purchase->purchaseDetails->random(min(2, $purchase->purchaseDetails->count()));

            $totalAmount = 0;
            $lines = [];

            foreach ($detailsToReturn as $detail) {
                $returnQty = min($detail->qty, fake()->numberBetween(1, 10));
                $totalAmount += $returnQty * $detail->price;
                $lines[] = ['detail' => $detail, 'qty' => $returnQty];
            }

            $purchaseReturn = PurchaseReturn::create([
                'return_number' => 'PRET-'.$returnDate->format('Ym').'-'.str_pad((string) $purchase->id, 4, '0', STR_PAD_LEFT),
                'return_date' => $returnDate,
                'total_amount' => $totalAmount,
                'purchase_id' => $purchase->id,
            ]);

            foreach ($lines as $line) {
                PurchaseReturnDetail::create([
                    'qty' => $line['qty'],
                    'reason' => fake()->randomElement(self::REASONS),
                    'purchase_return_id' => $purchaseReturn->id,
                    'product_id' => $line['detail']->product_id,
                ]);

                $this->moveStock(
                    productId: $line['detail']->product_id,
                    warehouseId: $purchase->warehouse_id,
                    type: 'OUT',
                    qty: -$line['qty'],
                    referenceNumber: $purchaseReturn->return_number,
                    date: $returnDate,
                );
            }
        }
    }
}
