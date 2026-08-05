<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = [
            'Pupuk Indonesia',
            'Petrokimia Gresik',
            'Syngenta',
            'Bayer CropScience',
            'BASF Agro',
            'Corteva Agriscience',
            'Cap Panah Merah (East West Seed)',
            'Charoen Pokphand',
            'Meroke Tetap Jaya',
            'Nusa Tani Alam',
        ];

        foreach ($brands as $name) {
            Brand::firstOrCreate(['name' => $name]);
        }
    }
}
