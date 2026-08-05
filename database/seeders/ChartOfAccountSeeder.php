<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['1000', 'Kas', 'Asset'],
            ['1010', 'Bank BCA', 'Asset'],
            ['1020', 'Bank Mandiri', 'Asset'],
            ['1100', 'Piutang Usaha', 'Asset'],
            ['1200', 'Persediaan Barang Dagang', 'Asset'],
            ['1500', 'Aset Tetap', 'Asset'],
            ['2000', 'Utang Usaha', 'Liability'],
            ['2100', 'Utang Pajak', 'Liability'],
            ['3000', 'Modal Pemilik', 'Equity'],
            ['4000', 'Pendapatan Penjualan', 'Revenue'],
            ['4100', 'Retur Penjualan', 'Revenue'],
            ['5000', 'Harga Pokok Penjualan', 'Expense'],
            ['5100', 'Beban Gaji', 'Expense'],
            ['5200', 'Beban Operasional', 'Expense'],
            ['5300', 'Beban Angkut & Pengiriman', 'Expense'],
        ];

        foreach ($accounts as [$code, $name, $type]) {
            ChartOfAccount::firstOrCreate(
                ['account_code' => $code],
                ['account_name' => $name, 'type' => $type]
            );
        }
    }
}
