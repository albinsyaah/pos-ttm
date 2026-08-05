<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['name' => 'Budi Santoso', 'position' => 'Store Manager', 'phone' => '081234560001'],
            ['name' => 'Siti Rahayu', 'position' => 'Admin Keuangan', 'phone' => '081234560002'],
            ['name' => 'Agus Wijaya', 'position' => 'Kepala Gudang', 'phone' => '081234560003'],
            ['name' => 'Dewi Lestari', 'position' => 'Kasir', 'phone' => '081234560004'],
            ['name' => 'Eko Prasetyo', 'position' => 'Sales Lapangan', 'phone' => '081234560005'],
            ['name' => 'Rina Marlina', 'position' => 'Sales Lapangan', 'phone' => '081234560006'],
            ['name' => 'Hendra Gunawan', 'position' => 'Staff Gudang', 'phone' => '081234560007'],
            ['name' => 'Yuni Kartika', 'position' => 'Staff Pembelian', 'phone' => '081234560008'],
            ['name' => 'Joko Susilo', 'position' => 'Sopir / Pengiriman', 'phone' => '081234560009'],
            ['name' => 'Wahyu Nugroho', 'position' => 'Sales Lapangan', 'phone' => '081234560010'],
        ];

        foreach ($employees as $index => $employee) {
            Employee::firstOrCreate(
                ['code' => 'EMP-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT)],
                [
                    'name' => $employee['name'],
                    'position' => $employee['position'],
                    'phone' => $employee['phone'],
                ]
            );
        }
    }
}
