<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\ItemType;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $brands = Brand::pluck('id', 'name');
        $itemTypes = ItemType::pluck('id', 'name');
        $groups = ProductGroup::pluck('id', 'name');

        // [code, name, brand, item_type, product_group]
        $products = [
            // Pupuk (Fertilizers)
            ['FRT-001', 'Pupuk Urea 50kg', 'Pupuk Indonesia', 'Pupuk', 'Tanaman Pangan'],
            ['FRT-002', 'Pupuk NPK Phonska 50kg', 'Petrokimia Gresik', 'Pupuk', 'Tanaman Pangan'],
            ['FRT-003', 'Pupuk ZA 50kg', 'Petrokimia Gresik', 'Pupuk', 'Tanaman Pangan'],
            ['FRT-004', 'Pupuk SP-36 50kg', 'Petrokimia Gresik', 'Pupuk', 'Tanaman Pangan'],
            ['FRT-005', 'Pupuk KCl 50kg', 'Pupuk Indonesia', 'Pupuk', 'Perkebunan'],
            ['FRT-006', 'Pupuk Organik Granul 40kg', 'Nusa Tani Alam', 'Pupuk', 'Hortikultura'],
            ['FRT-007', 'Pupuk Cair NPK 1L', 'Meroke Tetap Jaya', 'Pupuk', 'Hortikultura'],
            ['FRT-008', 'Pupuk Daun Grow More 100gr', 'Meroke Tetap Jaya', 'Pupuk', 'Hortikultura'],

            // Pestisida (Pesticides)
            ['PST-001', 'Insektisida Decis 25 EC 500ml', 'Bayer CropScience', 'Pestisida', 'Tanaman Pangan'],
            ['PST-002', 'Fungisida Antracol 70 WP 500gr', 'Bayer CropScience', 'Pestisida', 'Hortikultura'],
            ['PST-003', 'Herbisida Roundup 486 SL 1L', 'Bayer CropScience', 'Pestisida', 'Perkebunan'],
            ['PST-004', 'Insektisida Curacron 500 EC 500ml', 'Syngenta', 'Pestisida', 'Tanaman Pangan'],
            ['PST-005', 'Fungisida Score 250 EC 250ml', 'Syngenta', 'Pestisida', 'Hortikultura'],
            ['PST-006', 'Herbisida Gramoxone 276 SL 1L', 'Syngenta', 'Pestisida', 'Perkebunan'],
            ['PST-007', 'Insektisida Regent 50 SC 250ml', 'BASF Agro', 'Pestisida', 'Tanaman Pangan'],
            ['PST-008', 'Fungisida Cabrio 250 EC 250ml', 'BASF Agro', 'Pestisida', 'Hortikultura'],

            // Benih (Seeds)
            ['SED-001', 'Benih Padi Ciherang 5kg', 'Cap Panah Merah (East West Seed)', 'Benih', 'Tanaman Pangan'],
            ['SED-002', 'Benih Jagung Hibrida NK 212 1kg', 'Corteva Agriscience', 'Benih', 'Tanaman Pangan'],
            ['SED-003', 'Benih Cabai Merah Hot Beauty 10gr', 'Cap Panah Merah (East West Seed)', 'Benih', 'Hortikultura'],
            ['SED-004', 'Benih Tomat Servo 10gr', 'Cap Panah Merah (East West Seed)', 'Benih', 'Hortikultura'],
            ['SED-005', 'Benih Mentimun Roberto 10gr', 'Cap Panah Merah (East West Seed)', 'Benih', 'Hortikultura'],
            ['SED-006', 'Benih Kedelai Anjasmoro 1kg', 'Corteva Agriscience', 'Benih', 'Tanaman Pangan'],

            // Alat Pertanian (Tools/Equipment)
            ['TLS-001', 'Cangkul Baja Gagang Kayu', 'Nusa Tani Alam', 'Alat Pertanian', 'Alat & Mesin Pertanian'],
            ['TLS-002', 'Sabit Bergerigi', 'Nusa Tani Alam', 'Alat Pertanian', 'Alat & Mesin Pertanian'],
            ['TLS-003', 'Sprayer Elektrik 16L', 'Nusa Tani Alam', 'Alat Pertanian', 'Alat & Mesin Pertanian'],
            ['TLS-004', 'Sprayer Manual Punggung 15L', 'Nusa Tani Alam', 'Alat Pertanian', 'Alat & Mesin Pertanian'],
            ['TLS-005', 'Selang Air Taman 20m', 'Nusa Tani Alam', 'Alat Pertanian', 'Alat & Mesin Pertanian'],
            ['TLS-006', 'Gunting Pangkas Tanaman', 'Nusa Tani Alam', 'Alat Pertanian', 'Alat & Mesin Pertanian'],
            ['TLS-007', 'Garu Tangan 4 Mata', 'Nusa Tani Alam', 'Alat Pertanian', 'Alat & Mesin Pertanian'],
            ['TLS-008', 'Terpal Jemur Padi 4x6m', 'Nusa Tani Alam', 'Alat Pertanian', 'Sarana Produksi Lainnya'],

            // Pakan Ternak (Animal Feed)
            ['FED-001', 'Pakan Ayam Petelur BR1 50kg', 'Charoen Pokphand', 'Pakan Ternak', 'Peternakan'],
            ['FED-002', 'Pakan Ayam Pedaging BR2 50kg', 'Charoen Pokphand', 'Pakan Ternak', 'Peternakan'],
            ['FED-003', 'Pakan Sapi Konsentrat 50kg', 'Charoen Pokphand', 'Pakan Ternak', 'Peternakan'],
            ['FED-004', 'Pakan Ikan Lele Terapung 30kg', 'Charoen Pokphand', 'Pakan Ternak', 'Peternakan'],
            ['FED-005', 'Pakan Bebek Petelur 50kg', 'Charoen Pokphand', 'Pakan Ternak', 'Peternakan'],

            // Media Tanam (Growing Media)
            ['MED-001', 'Media Tanam Sekam Bakar 20L', 'Nusa Tani Alam', 'Media Tanam', 'Hortikultura'],
            ['MED-002', 'Media Tanam Kompos Organik 20L', 'Nusa Tani Alam', 'Media Tanam', 'Hortikultura'],
            ['MED-003', 'Cocopeat Blok 5kg', 'Nusa Tani Alam', 'Media Tanam', 'Hortikultura'],
            ['MED-004', 'Polybag Semai 15x20 (isi 100)', 'Nusa Tani Alam', 'Media Tanam', 'Hortikultura'],
        ];

        foreach ($products as [$code, $name, $brand, $itemType, $group]) {
            Product::firstOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'brand_id' => $brands[$brand] ?? null,
                    'item_type_id' => $itemTypes[$itemType] ?? null,
                    'product_group_id' => $groups[$group] ?? null,
                ]
            );
        }
    }
}
