<?php

namespace Database\Seeders;

use App\Models\ItemType;
use Illuminate\Database\Seeder;

class ItemTypeSeeder extends Seeder
{
    public function run(): void
    {
        $itemTypes = [
            'Pupuk',
            'Pestisida',
            'Benih',
            'Alat Pertanian',
            'Pakan Ternak',
            'Media Tanam',
        ];

        foreach ($itemTypes as $name) {
            ItemType::firstOrCreate(['name' => $name]);
        }
    }
}
