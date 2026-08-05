<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['code' => 'WH-01', 'name' => 'Gudang Pusat', 'location' => 'Jl. Raya Karawang - Cikampek KM 5, Karawang, Jawa Barat'],
            ['code' => 'WH-02', 'name' => 'Gudang Cabang Purwakarta', 'location' => 'Jl. Veteran No. 22, Purwakarta, Jawa Barat'],
            ['code' => 'WH-03', 'name' => 'Gudang Cabang Subang', 'location' => 'Jl. Otista No. 10, Subang, Jawa Barat'],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::firstOrCreate(['code' => $warehouse['code']], $warehouse);
        }
    }
}
