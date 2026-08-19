<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Creates one permission per sidebar page/submenu item (see
     * App\Support\AccessControl), three starter roles, and a default
     * Super Admin account.
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionSlugs = array_keys(AccessControl::allPermissions());

        foreach ($permissionSlugs as $slug) {
            Permission::firstOrCreate(['name' => $slug, 'guard_name' => 'web']);
        }

        // Super Admin: every permission, plus the Gate::before bypass in
        // AppServiceProvider means it can never be locked out of anything.
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissionSlugs);

        // Admin: day-to-day operational access, but not user/role management.
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(array_filter(
            $permissionSlugs,
            fn (string $slug) => $slug !== 'administrator.manage'
        ));

        // Staff: read access plus day-to-day customer/transaction handling.
        // "Full inventory/transactions/reports access" now means every
        // page-level permission under those modules (see AccessControl),
        // since permissions were split from one-per-module to
        // one-per-sidebar-page.
        $staff = Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
        $staff->syncPermissions(array_filter(
            $permissionSlugs,
            fn (string $slug) => in_array($slug, ['dashboard.view', 'customers.view', 'customers.manage', 'inquiry.view'], true)
                || str_starts_with($slug, 'inventory.')
                || str_starts_with($slug, 'transactions.')
                || str_starts_with($slug, 'reports.')
        ));

        // A default Super Admin account so the app is usable immediately
        // after a fresh migrate. Change this password before going live.
        $user = User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'password' => Hash::make('password'),
                'role' => 'Super Admin',
                'is_active' => true,
            ]
        );

        if (! $user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
        }
    }
}
