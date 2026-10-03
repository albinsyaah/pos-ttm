<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'transactions.point-of-sale-induk.view',
        'transactions.point-of-sale-induk.manage',
    ];

    /**
     * Permissions of the head cashier's terminal. Admin and Super Admin get them here; any
     * other role (the head cashier role) gets them on the Role screen.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (['Super Admin', 'Admin'] as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()
                ?->givePermissionTo(self::PERMISSIONS);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
