<?php

namespace Database\Seeders;

use App\Models\ArPayment;
use App\Models\Sale;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ArPaymentSeeder extends Seeder
{
    private const METHODS = ['Transfer Bank', 'Tunai', 'QRIS'];

    public function run(): void
    {
        $sales = Sale::whereNotNull('customer_id')->orderBy('id')->get();
        $sequence = 1;

        foreach ($sales as $index => $sale) {
            // Not every credit sale has been collected yet.
            if ($index % 4 === 3) {
                continue;
            }

            $paymentDate = Carbon::parse($sale->sale_date)->addDays(fake()->numberBetween(1, 14));

            $amount = $index % 7 === 0
                ? round($sale->total_amount * 0.6)
                : $sale->total_amount;

            ArPayment::create([
                'payment_number' => 'ARPAY-'.$paymentDate->format('Ym').'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_method' => fake()->randomElement(self::METHODS),
                'customer_id' => $sale->customer_id,
            ]);

            $sequence++;
        }
    }
}
