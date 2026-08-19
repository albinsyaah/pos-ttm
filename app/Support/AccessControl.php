<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth mapping each sidebar module to its permission
 * slug(s). Used by RolePermissionSeeder (to create the permissions) and by
 * the sidebar view (to decide what a signed-in user can see).
 */
class AccessControl
{
    /**
     * Ordered list of "landing pages" a signed-in user can be sent to,
     * mirroring the order modules appear in the sidebar. Each entry maps
     * a permission required to view the module to one or more candidate
     * route names for that module (some modules, like Transaksi and
     * Report, have several sub-pages sharing a single permission, so we
     * fall back through the list and use the first route that exists).
     *
     * This is what makes login/"/" redirects role-aware: instead of always
     * sending everyone to /dashboard (which 403s for a role without
     * dashboard.view), we walk this list and send the user to the first
     * page their permissions actually allow.
     */
    protected static function landingRoutes(): array
    {
        return [
            'dashboard.view' => ['dashboard'],
            'customers.view' => ['customers.index'],
            'inventory.view' => [
                'inventory.brands.index',
                'inventory.item-types.index',
                'inventory.product-groups.index',
                'inventory.products.index',
            ],
            'assets.view' => ['assets.index'],
            'pricing.view' => ['pricing.price-setups.index'],
            'finance.view' => [
                'finance.chart-of-accounts.index',
                'finance.cash-flows.index',
                'finance.general-ledgers.index',
            ],
            'suppliers.view' => ['suppliers.index'],
            'warehouses.view' => ['warehouses.index'],
            'hr.view' => [
                'hr.employees.index',
                'hr.salesmen.index',
            ],
            'transactions.view' => [
                'transactions.purchase-orders.index',
                'transactions.purchases.index',
                'transactions.purchase-returns.index',
                'transactions.payable-payments.index',
                'transactions.sales-orders.index',
                'transactions.sales.index',
                'transactions.point-of-sale-new.index',
                'transactions.point-of-sale.index',
                'transactions.sales-returns.index',
                'transactions.sales-spg.index',
                'transactions.receivable-payments.index',
                'transactions.item-requests.index',
                'transactions.internal-expenditures.index',
                'transactions.internal-receipts.index',
                'transactions.warehouse-transfers.index',
                'transactions.deviations.index',
                'transactions.cash-management.index',
                'transactions.general-ledger.index',
            ],
            'reports.view' => [
                'reports.purchase-orders',
                'reports.purchases',
                'reports.purchase-returns',
                'reports.payable-payments',
                'reports.sales-orders',
                'reports.sales',
                'reports.sales-summary',
                'reports.sales-returns',
                'reports.receivable-payments',
                'reports.receivable-card',
                'reports.receivable-aging',
                'reports.expenditure',
                'reports.receipt',
                'reports.transfers',
                'reports.deviations',
                'reports.stock-card',
                'reports.position',
                'reports.inventory',
            ],
            'inquiry.view' => ['inquiry.index'],
        ];
    }

    /**
     * The first route name a signed-in user is allowed to land on, in
     * sidebar order. Super Admin (which bypasses every @can check, see
     * AppServiceProvider) always resolves to the admin user list if
     * nothing else matches, since it also gets every permission on
     * creation/update (see RoleController).
     *
     * Returns null if the user has no accessible page at all (e.g. a role
     * with every permission unchecked) so the caller can handle that case
     * explicitly instead of redirecting into another 403.
     */
    public static function firstAccessibleRoute(User $user): ?string
    {
        foreach (self::landingRoutes() as $permission => $routeNames) {
            if (! $user->can($permission)) {
                continue;
            }

            foreach ($routeNames as $routeName) {
                if (Route::has($routeName)) {
                    return $routeName;
                }
            }
        }

        if ($user->isSuperAdmin() && Route::has('admin.users.index')) {
            return 'admin.users.index';
        }

        return null;
    }

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
