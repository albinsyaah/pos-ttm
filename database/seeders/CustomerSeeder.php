<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('id_ID');

        // A handful of named "anchor" customers (co-ops / farmer groups),
        // topped up with random walk-in customers.
        $named = [
            'Kelompok Tani Sumber Makmur',
            'Kelompok Tani Sri Rejeki',
            'Gapoktan Tani Mulya',
            'Toko Tani Berkah',
            'UD Sumber Tani',
            'Koperasi Tani Sejahtera',
        ];

        $sequence = 1;

        foreach ($named as $name) {
            Customer::firstOrCreate(
                ['code' => 'CUST-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT)],
                [
                    'name' => $name,
                    'phone' => $faker->numerify('08##########'),
                    'address' => $faker->address(),
                ]
            );
            $sequence++;
        }

        for ($i = $sequence; $i <= 25; $i++) {
            Customer::firstOrCreate(
                ['code' => 'CUST-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)],
                [
                    'name' => $faker->name(),
                    'phone' => $faker->numerify('08##########'),
                    'address' => $faker->address(),
                ]
            );
        }
    }
}
