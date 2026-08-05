<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('id_ID');

        $named = [
            'PT Pupuk Indonesia (Persero)',
            'PT Petrokimia Gresik',
            'PT Syngenta Indonesia',
            'PT Bayer Indonesia',
            'PT BASF Indonesia',
            'PT Corteva Agriscience Indonesia',
            'PT East West Seed Indonesia',
            'PT Charoen Pokphand Indonesia',
            'PT Meroke Tetap Jaya',
            'CV Nusa Tani Alam',
        ];

        foreach ($named as $index => $name) {
            Supplier::firstOrCreate(
                ['code' => 'SUP-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT)],
                [
                    'name' => $name,
                    'contact_person' => $faker->name(),
                    'phone' => $faker->numerify('021-#######'),
                    'address' => $faker->address(),
                ]
            );
        }
    }
}
