<?php

namespace Database\Seeders;

use App\Models\ApPayment;
use App\Models\ArPayment;
use App\Models\CashFlow;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class CashFlowSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = ChartOfAccount::pluck('id', 'account_code');
        $cash = $accounts['1000'];
        $bank = $accounts['1010'];
        $salaryExpense = $accounts['5100'];
        $opExpense = $accounts['5200'];
        $shippingExpense = $accounts['5300'];

        // Inflows: every AR payment received is cash coming into the business.
        ArPayment::orderBy('id')->get()->each(function (ArPayment $payment) use ($cash, $bank) {
            CashFlow::create([
                'transaction_date' => $payment->payment_date,
                'type' => 'Inflow',
                'amount' => $payment->amount,
                'description' => 'Penerimaan pembayaran piutang - '.$payment->payment_number,
                'account_id' => $payment->paymentMethod?->is_cash ? $cash : $bank,
            ]);
        });

        // Outflows: every AP payment made is cash going out.
        ApPayment::orderBy('id')->get()->each(function (ApPayment $payment) use ($cash, $bank) {
            CashFlow::create([
                'transaction_date' => $payment->payment_date,
                'type' => 'Outflow',
                'amount' => $payment->amount,
                'description' => 'Pembayaran utang usaha - '.$payment->payment_number,
                'account_id' => $payment->paymentMethod?->is_cash ? $cash : $bank,
            ]);
        });

        // Recurring monthly operating outflows: payroll, utilities, shipping.
        $start = Carbon::create(2024, 1, 25);

        for ($month = 0; $month < 8; $month++) {
            $payrollDate = (clone $start)->addMonthsNoOverflow($month);

            CashFlow::create([
                'transaction_date' => $payrollDate,
                'type' => 'Outflow',
                'amount' => 32000000,
                'description' => 'Pembayaran gaji karyawan bulan '.$payrollDate->format('F Y'),
                'account_id' => $salaryExpense,
            ]);

            CashFlow::create([
                'transaction_date' => (clone $payrollDate)->addDays(2),
                'type' => 'Outflow',
                'amount' => fake()->numberBetween(3500000, 6000000),
                'description' => 'Beban operasional (listrik, air, ATK) bulan '.$payrollDate->format('F Y'),
                'account_id' => $opExpense,
            ]);

            CashFlow::create([
                'transaction_date' => (clone $payrollDate)->addDays(4),
                'type' => 'Outflow',
                'amount' => fake()->numberBetween(2000000, 4500000),
                'description' => 'Beban angkut & pengiriman bulan '.$payrollDate->format('F Y'),
                'account_id' => $shippingExpense,
            ]);
        }
    }
}
