<?php

namespace Database\Seeders;

use App\Models\ApPayment;
use App\Models\ArPayment;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Database\Seeder;

class GeneralLedgerSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = ChartOfAccount::pluck('id', 'account_code');

        $inventory = $accounts['1200'];
        $accountsPayable = $accounts['2000'];
        $cash = $accounts['1000'];
        $bank = $accounts['1010'];
        $receivable = $accounts['1100'];
        $revenue = $accounts['4000'];
        $cogs = $accounts['5000'];

        // Purchases: debit Inventory, credit Accounts Payable.
        Purchase::orderBy('id')->get()->each(function (Purchase $purchase) use ($inventory, $accountsPayable) {
            GeneralLedger::create([
                'transaction_date' => $purchase->purchase_date,
                'debit' => $purchase->total_amount,
                'credit' => 0,
                'reference_number' => $purchase->invoice_number,
                'account_id' => $inventory,
            ]);
            GeneralLedger::create([
                'transaction_date' => $purchase->purchase_date,
                'debit' => 0,
                'credit' => $purchase->total_amount,
                'reference_number' => $purchase->invoice_number,
                'account_id' => $accountsPayable,
            ]);
        });

        // AP payments: debit Accounts Payable, credit Cash/Bank.
        ApPayment::orderBy('id')->get()->each(function (ApPayment $payment) use ($accountsPayable, $cash, $bank) {
            $paidFrom = $payment->payment_method === 'Tunai' ? $cash : $bank;

            GeneralLedger::create([
                'transaction_date' => $payment->payment_date,
                'debit' => $payment->amount,
                'credit' => 0,
                'reference_number' => $payment->payment_number,
                'account_id' => $accountsPayable,
            ]);
            GeneralLedger::create([
                'transaction_date' => $payment->payment_date,
                'debit' => 0,
                'credit' => $payment->amount,
                'reference_number' => $payment->payment_number,
                'account_id' => $paidFrom,
            ]);
        });

        // Sales: debit Receivable/Cash, credit Revenue; plus COGS at ~70% of sale.
        Sale::orderBy('id')->get()->each(function (Sale $sale) use ($receivable, $cash, $revenue, $cogs, $inventory) {
            $debitAccount = $sale->source === 'POS' ? $cash : $receivable;

            GeneralLedger::create([
                'transaction_date' => $sale->sale_date,
                'debit' => $sale->total_amount,
                'credit' => 0,
                'reference_number' => $sale->invoice_number,
                'account_id' => $debitAccount,
            ]);
            GeneralLedger::create([
                'transaction_date' => $sale->sale_date,
                'debit' => 0,
                'credit' => $sale->total_amount,
                'reference_number' => $sale->invoice_number,
                'account_id' => $revenue,
            ]);

            $estimatedCost = round($sale->total_amount * 0.7);
            GeneralLedger::create([
                'transaction_date' => $sale->sale_date,
                'debit' => $estimatedCost,
                'credit' => 0,
                'reference_number' => $sale->invoice_number,
                'account_id' => $cogs,
            ]);
            GeneralLedger::create([
                'transaction_date' => $sale->sale_date,
                'debit' => 0,
                'credit' => $estimatedCost,
                'reference_number' => $sale->invoice_number,
                'account_id' => $inventory,
            ]);
        });

        // AR payments: debit Cash/Bank, credit Receivable.
        ArPayment::orderBy('id')->get()->each(function (ArPayment $payment) use ($receivable, $cash, $bank) {
            $receivedInto = $payment->payment_method === 'Tunai' ? $cash : $bank;

            GeneralLedger::create([
                'transaction_date' => $payment->payment_date,
                'debit' => $payment->amount,
                'credit' => 0,
                'reference_number' => $payment->payment_number,
                'account_id' => $receivedInto,
            ]);
            GeneralLedger::create([
                'transaction_date' => $payment->payment_date,
                'debit' => 0,
                'credit' => $payment->amount,
                'reference_number' => $payment->payment_number,
                'account_id' => $receivable,
            ]);
        });
    }
}
