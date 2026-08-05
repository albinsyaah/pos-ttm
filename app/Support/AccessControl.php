<?php

namespace App\Support;

/**
 * Single source of truth mapping each sidebar module to its permission
 * slug(s). Used by RolePermissionSeeder (to create the permissions) and by
 * the sidebar view (to decide what a signed-in user can see).
 */
class AccessControl
{
    /**
     * module key => [label, [permission => label]]
     */
    public static function modules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'permissions' => [
                    'dashboard.view' => 'View dashboard',
                ],
            ],
            'customers' => [
                'label' => 'Customer',
                'permissions' => [
                    'customers.view' => 'View customers',
                    'customers.manage' => 'Add/edit/delete customers',
                ],
            ],
            'inventory' => [
                'label' => 'Inventory',
                'permissions' => [
                    'inventory.view' => 'View inventory (brands, item types, product groups, products)',
                    'inventory.manage' => 'Manage inventory',
                ],
            ],
            'assets' => [
                'label' => 'Asset',
                'permissions' => [
                    'assets.view' => 'View assets',
                    'assets.manage' => 'Manage assets',
                ],
            ],
            'pricing' => [
                'label' => 'Setup Harga',
                'permissions' => [
                    'pricing.view' => 'View price setups',
                    'pricing.manage' => 'Manage price setups',
                ],
            ],
            'finance' => [
                'label' => 'Keuangan',
                'permissions' => [
                    'finance.view' => 'View cash flow / general ledger / chart of accounts',
                    'finance.manage' => 'Manage finance records',
                ],
            ],
            'suppliers' => [
                'label' => 'Supplier',
                'permissions' => [
                    'suppliers.view' => 'View suppliers',
                    'suppliers.manage' => 'Manage suppliers',
                ],
            ],
            'warehouses' => [
                'label' => 'Gudang',
                'permissions' => [
                    'warehouses.view' => 'View warehouses',
                    'warehouses.manage' => 'Manage warehouses',
                ],
            ],
            'hr' => [
                'label' => 'Kepegawaian',
                'permissions' => [
                    'hr.view' => 'View employees & salesmen',
                    'hr.manage' => 'Manage employees & salesmen',
                ],
            ],
            'transactions' => [
                'label' => 'Transaksi',
                'permissions' => [
                    'transactions.view' => 'View AP/AR/mutation/cash transactions',
                    'transactions.manage' => 'Create/edit transactions (PO, sales, payments, mutations)',
                ],
            ],
            'reports' => [
                'label' => 'Report',
                'permissions' => [
                    'reports.view' => 'View reports',
                ],
            ],
            'inquiry' => [
                'label' => 'Inquery',
                'permissions' => [
                    'inquiry.view' => 'Use inquiry/search tools',
                ],
            ],
            'administrator' => [
                'label' => 'Administrator',
                'permissions' => [
                    'administrator.manage' => 'Manage users, roles & permissions',
                ],
            ],
        ];
    }

    /**
     * Flat list of every permission slug => label, e.g. for seeding.
     */
    public static function allPermissions(): array
    {
        $permissions = [];

        foreach (self::modules() as $module) {
            $permissions += $module['permissions'];
        }

        return $permissions;
    }
}
