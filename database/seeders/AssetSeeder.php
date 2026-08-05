<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        $assets = [
            ['name' => 'Mobil Pickup Pengiriman', 'purchase_date' => '2021-03-10', 'value' => 185000000],
            ['name' => 'Truk Engkel Box', 'purchase_date' => '2020-07-22', 'value' => 265000000],
            ['name' => 'Timbangan Digital 500kg', 'purchase_date' => '2022-01-15', 'value' => 8500000],
            ['name' => 'Forklift Manual', 'purchase_date' => '2021-11-05', 'value' => 32000000],
            ['name' => 'Rak Gudang Baja (Set)', 'purchase_date' => '2022-05-18', 'value' => 15000000],
            ['name' => 'Komputer Kasir POS', 'purchase_date' => '2023-02-01', 'value' => 9500000],
            ['name' => 'Printer Struk Thermal', 'purchase_date' => '2023-02-01', 'value' => 1200000],
            ['name' => 'Genset 5000 Watt', 'purchase_date' => '2022-09-12', 'value' => 18500000],
            ['name' => 'CCTV Sistem Keamanan Gudang', 'purchase_date' => '2023-06-20', 'value' => 12000000],
            ['name' => 'Mesin Jahit Karung Pupuk', 'purchase_date' => '2022-03-30', 'value' => 4500000],
        ];

        foreach ($assets as $index => $asset) {
            Asset::firstOrCreate(
                ['asset_code' => 'AST-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'name' => $asset['name'],
                    'purchase_date' => Carbon::parse($asset['purchase_date']),
                    'value' => $asset['value'],
                ]
            );
        }
    }
}
