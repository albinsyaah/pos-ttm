<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * The starter payment methods. The migration already creates them, so this
     * only restores a method that was removed; nothing existing is changed
     * (a method an admin renamed or deactivated stays that way).
     */
    public function run(): void
    {
        foreach ([
            ['code' => 'cash', 'name' => 'Tunai', 'is_cash' => true],
            ['code' => 'bank_transfer', 'name' => 'Transfer', 'is_cash' => false],
            ['code' => 'qris', 'name' => 'QRIS', 'is_cash' => false],
        ] as $method) {
            PaymentMethod::firstOrCreate(['code' => $method['code']], $method + ['is_active' => true]);
        }
    }
}
