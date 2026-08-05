<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Gives a handful of employees a login account, on top of the default
     * `superadmin` account already created by RolePermissionSeeder.
     */
    public function run(): void
    {
        $roleByPosition = [
            'Store Manager' => 'Admin',
            'Admin Keuangan' => 'Admin',
            'Kepala Gudang' => 'Admin',
            'Kasir' => 'Staff',
            'Sales Lapangan' => 'Staff',
            'Staff Gudang' => 'Staff',
            'Staff Pembelian' => 'Staff',
        ];

        Employee::query()
            ->whereIn('position', array_keys($roleByPosition))
            ->get()
            ->each(function (Employee $employee) use ($roleByPosition) {
                $role = $roleByPosition[$employee->position] ?? 'Staff';

                $user = User::firstOrCreate(
                    ['username' => Str::slug($employee->name, '.')],
                    [
                        'password' => Hash::make('password'),
                        'role' => $role,
                        'employee_id' => $employee->id,
                        'is_active' => true,
                    ]
                );

                if (! $user->hasRole($role)) {
                    $user->syncRoles([$role]);
                }
            });
    }
}
