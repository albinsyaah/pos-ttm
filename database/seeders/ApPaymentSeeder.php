<?php

namespace Database\Seeders;

use App\Models\ApPayment;
use App\Models\Purchase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ApPaymentSeeder extends Seeder
{
    private const METHODS = ['Transfer Bank', 'Tunai', 'Giro'];

    public function run(): void
    {
        $purchases = Purchase::orderBy('id')->get();
        $sequence = 1;

        foreach ($purchases as $index => $purchase) {
            // Roughly 80% of completed purchases have been paid off so far.
            if ($index % 5 === 4) {
                continue;
            }

            $paymentDate = Carbon::parse($purchase->purchase_date)->addDays(fake()->numberBetween(3, 21));

            // Most are paid in full; a few are partial (down payment).
            $amount = $index % 6 === 0
                ? round($purchase->total_amount * 0.5)
                : $purchase->total_amount;

            ApPayment::create([
                'payment_number' => 'APAY-'.$paymentDate->format('Ym').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_method' => fake()->randomElement(self::METHODS),
                'supplier_id' => $purchase->supplier_id,
            ]);

            $sequence++;
        }
    }
}
