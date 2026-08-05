<?php

namespace Database\Seeders;

use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Tanaman Pangan',
            'Hortikultura',
            'Perkebunan',
            'Peternakan',
            'Alat & Mesin Pertanian',
            'Sarana Produksi Lainnya',
        ];

        foreach ($groups as $name) {
            ProductGroup::firstOrCreate(['name' => $name]);
        }
    }
}
