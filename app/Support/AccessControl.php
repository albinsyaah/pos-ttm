<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth mapping each sidebar link/submenu item to its own
 * permission slug(s). Used by RolePermissionSeeder (to create the
 * permissions), the Role management screen (to render one checkbox per
 * sidebar page for the Super Admin to toggle), and the sidebar view itself
 * (to decide what a signed-in user can see).
 *
 * Granularity: most modules that show as a single sidebar link (Dashboard,
 * Customer, Asset, Setup Harga, Supplier, Gudang, Inquiry) keep one
 * view/manage permission pair. Modules that expand into several sidebar
 * links/submenus (Inventory, Keuangan, Kepegawaian, Transaksi, Report) get
 * one view/manage pair *per link*, so a role can be scoped down to, say,
 * just "Transactions > Account Receivable > Receivable Payment" instead of
 * the whole Transaksi module.
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
                    'inventory.brands.view' => 'View brands',
                    'inventory.brands.manage' => 'Add/edit/delete brands',
                    'inventory.item-types.view' => 'View item types',
                    'inventory.item-types.manage' => 'Add/edit/delete item types',
                    'inventory.product-groups.view' => 'View product groups',
                    'inventory.product-groups.manage' => 'Add/edit/delete product groups',
                    'inventory.products.view' => 'View products',
                    'inventory.products.manage' => 'Add/edit/delete products',
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
                    'finance.chart-of-accounts.view' => 'View chart of accounts',
                    'finance.chart-of-accounts.manage' => 'Add/edit/delete chart of accounts',
                    'finance.cash-flows.view' => 'View cash flow',
                    'finance.cash-flows.manage' => 'Add/edit/delete cash flow entries',
                    'finance.payment-methods.view' => 'View payment methods',
                    'finance.payment-methods.manage' => 'Add/edit/delete payment methods',
                    'finance.general-ledgers.view' => 'View general ledger (Keuangan)',
                    'finance.general-ledgers.manage' => 'Add/edit/delete general ledger entries (Keuangan)',
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
                    'hr.employees.view' => 'View employees',
                    'hr.employees.manage' => 'Add/edit/delete employees',
                    'hr.salesmen.view' => 'View salesmen',
                    'hr.salesmen.manage' => 'Add/edit/delete salesmen',
                ],
            ],
            'transactions' => [
                'label' => 'Transaksi',
                'permissions' => [
                    // Account Payable
                    'transactions.purchase-orders.view' => 'Account Payable — View purchase orders',
                    'transactions.purchase-orders.manage' => 'Account Payable — Add/edit/delete purchase orders',
                    'transactions.purchases.view' => 'Account Payable — View purchases',
                    'transactions.purchases.manage' => 'Account Payable — Add/edit/delete purchases',
                    'transactions.purchase-returns.view' => 'Account Payable — View purchase returns',
                    'transactions.purchase-returns.manage' => 'Account Payable — Add/edit/delete purchase returns',
                    'transactions.payable-payments.view' => 'Account Payable — View payable payments',
                    'transactions.payable-payments.manage' => 'Account Payable — Add/edit/delete payable payments',
                    // Account Receivable
                    'transactions.sales-orders.view' => 'Account Receivable — View sales orders',
                    'transactions.sales-orders.manage' => 'Account Receivable — Add/edit/delete sales orders',
                    'transactions.sales.view' => 'Account Receivable — View sales',
                    'transactions.sales.manage' => 'Account Receivable — Add/edit/delete sales',
                    'transactions.point-of-sale-new.view' => 'Account Receivable — View point of sales (new)',
                    'transactions.point-of-sale-new.manage' => 'Account Receivable — Use point of sales (new)',
                    'transactions.point-of-sale-induk.view' => 'Account Receivable — View head cashier terminal (driver, large receipt, delivery note)',
                    'transactions.point-of-sale-induk.manage' => 'Account Receivable — Use head cashier terminal (driver, large receipt, delivery note)',
                    'transactions.point-of-sale.view' => 'Account Receivable — View point of sales',
                    'transactions.point-of-sale.manage' => 'Account Receivable — Add/edit/delete point of sales',
                    'transactions.sales-returns.view' => 'Account Receivable — View sales returns',
                    'transactions.sales-returns.manage' => 'Account Receivable — Add/edit/delete sales returns',
                    'transactions.sales-spg.view' => 'Account Receivable — View sales SPG',
                    'transactions.sales-spg.manage' => 'Account Receivable — Add/edit/delete sales SPG',
                    'transactions.receivable-payments.view' => 'Account Receivable — View receivable payments',
                    'transactions.receivable-payments.manage' => 'Account Receivable — Add/edit/delete receivable payments',
                    // Mutasi Internal
                    'transactions.item-requests.view' => 'Mutasi Internal — View item requests',
                    'transactions.item-requests.manage' => 'Mutasi Internal — Add/edit/delete item requests',
                    'transactions.internal-expenditures.view' => 'Mutasi Internal — View internal expenditures',
                    'transactions.internal-expenditures.manage' => 'Mutasi Internal — Add/edit/delete internal expenditures',
                    'transactions.internal-receipts.view' => 'Mutasi Internal — View internal receipts',
                    'transactions.internal-receipts.manage' => 'Mutasi Internal — Add/edit/delete internal receipts',
                    'transactions.warehouse-transfers.view' => 'Mutasi Internal — View warehouse transfers',
                    'transactions.warehouse-transfers.manage' => 'Mutasi Internal — Add/edit/delete warehouse transfers',
                    'transactions.deviations.view' => 'Mutasi Internal — View deviations',
                    'transactions.deviations.manage' => 'Mutasi Internal — Add/edit/delete deviations',
                    // Direct children
                    'transactions.cash-management.view' => 'View cash management',
                    'transactions.cash-management.manage' => 'Add/edit/delete cash management entries',
                    'transactions.general-ledger.view' => 'View general ledger (Transaksi)',
                    'transactions.general-ledger.manage' => 'Add/edit/delete general ledger entries (Transaksi)',
                ],
            ],
            'reports' => [
                'label' => 'Report',
                'permissions' => [
                    'reports.purchase-orders.view' => 'Purchase order report',
                    'reports.purchases.view' => 'Purchase report',
                    'reports.purchase-returns.view' => 'Purchase return report',
                    'reports.payable-payments.view' => 'Payable payment report',
                    'reports.sales-orders.view' => 'Sales order report',
                    'reports.sales.view' => 'Sales report',
                    'reports.sales-summary.view' => 'Sales summary report',
                    'reports.sales-by-product.view' => 'Sales by product report',
                    'reports.salesman.view' => 'Salesman report',
                    'reports.payment-methods.view' => 'Payment method report',
                    'reports.sales-returns.view' => 'Sales return report',
                    'reports.receivable-payments.view' => 'Receivable payment report',
                    'reports.receivable-card.view' => 'Receivable card report',
                    'reports.receivable-aging.view' => 'Receivable aging report',
                    'reports.expenditure.view' => 'Expenditure report',
                    'reports.receipt.view' => 'Receipt report',
                    'reports.transfers.view' => 'Transfer report',
                    'reports.deviations.view' => 'Deviation report',
                    'reports.stock-card.view' => 'Stock card report',
                    'reports.position.view' => 'Position report',
                    'reports.inventory.view' => 'Inventory report',
                ],
            ],
            'inquiry' => [
                'label' => 'Inquery',
                'permissions' => [
                    'inquiry.view' => 'Use inquiry/search tools',
                ],
            ],
            'notifications' => [
                'label' => 'Notifikasi',
                'permissions' => [
                    'notifications.view' => 'Receive notifications (purchase due-date reminders)',
                ],
            ],
            'print' => [
                'label' => 'Cetak',
                'permissions' => [
                    'print.receipt-small' => 'Print small receipt (100 x 150 mm)',
                    'print.receipt-large' => 'Print large receipt/invoice (A4)',
                    'print.delivery-note' => 'Print delivery note (surat jalan)',
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

    /**
     * Every ".view" permission slug that belongs to a given module, used to
     * decide whether to show a module's group in the sidebar at all (the
     * group is visible if the user can see at least one page inside it).
     */
    public static function viewPermissions(string $moduleKey): array
    {
        $permissions = self::modules()[$moduleKey]['permissions'] ?? [];

        return array_values(array_filter(
            array_keys($permissions),
            fn (string $slug) => str_ends_with($slug, '.view')
        ));
    }

    /**
     * Ordered list of "landing pages" a signed-in user can be sent to,
     * mirroring the order modules (and, within Transaksi/Report, the order
     * of their individual pages) appear in the sidebar. Each entry maps a
     * permission required to view a specific page to that page's route
     * name.
     *
     * This is what makes login/"/" redirects role-aware: instead of always
     * sending everyone to /dashboard (which 403s for a role without
     * dashboard.view), we walk this list and send the user to the first
     * page their permissions actually allow.
     */
    protected static function landingRoutes(): array
    {
        return [
            'dashboard.view' => 'dashboard',
            'customers.view' => 'customers.index',
            'inventory.brands.view' => 'inventory.brands.index',
            'inventory.item-types.view' => 'inventory.item-types.index',
            'inventory.product-groups.view' => 'inventory.product-groups.index',
            'inventory.products.view' => 'inventory.products.index',
            'assets.view' => 'assets.index',
            'pricing.view' => 'pricing.price-setups.index',
            'finance.chart-of-accounts.view' => 'finance.chart-of-accounts.index',
            'finance.cash-flows.view' => 'finance.cash-flows.index',
            'finance.payment-methods.view' => 'finance.payment-methods.index',
            'finance.general-ledgers.view' => 'finance.general-ledgers.index',
            'suppliers.view' => 'suppliers.index',
            'warehouses.view' => 'warehouses.index',
            'hr.employees.view' => 'hr.employees.index',
            'hr.salesmen.view' => 'hr.salesmen.index',
            'transactions.purchase-orders.view' => 'transactions.purchase-orders.index',
            'transactions.purchases.view' => 'transactions.purchases.index',
            'transactions.purchase-returns.view' => 'transactions.purchase-returns.index',
            'transactions.payable-payments.view' => 'transactions.payable-payments.index',
            'transactions.sales-orders.view' => 'transactions.sales-orders.index',
            'transactions.sales.view' => 'transactions.sales.index',
            'transactions.point-of-sale-new.view' => 'transactions.point-of-sale-new.index',
            'transactions.point-of-sale-induk.view' => 'transactions.point-of-sale-induk.index',
            'transactions.point-of-sale.view' => 'transactions.point-of-sale.index',
            'transactions.sales-returns.view' => 'transactions.sales-returns.index',
            'transactions.sales-spg.view' => 'transactions.sales-spg.index',
            'transactions.receivable-payments.view' => 'transactions.receivable-payments.index',
            'transactions.item-requests.view' => 'transactions.item-requests.index',
            'transactions.internal-expenditures.view' => 'transactions.internal-expenditures.index',
            'transactions.internal-receipts.view' => 'transactions.internal-receipts.index',
            'transactions.warehouse-transfers.view' => 'transactions.warehouse-transfers.index',
            'transactions.deviations.view' => 'transactions.deviations.index',
            'transactions.cash-management.view' => 'transactions.cash-management.index',
            'transactions.general-ledger.view' => 'transactions.general-ledger.index',
            'reports.purchase-orders.view' => 'reports.purchase-orders',
            'reports.purchases.view' => 'reports.purchases',
            'reports.purchase-returns.view' => 'reports.purchase-returns',
            'reports.payable-payments.view' => 'reports.payable-payments',
            'reports.sales-orders.view' => 'reports.sales-orders',
            'reports.sales.view' => 'reports.sales',
            'reports.sales-summary.view' => 'reports.sales-summary',
            'reports.sales-by-product.view' => 'reports.sales-by-product',
            'reports.salesman.view' => 'reports.salesman',
            'reports.payment-methods.view' => 'reports.payment-methods',
            'reports.sales-returns.view' => 'reports.sales-returns',
            'reports.receivable-payments.view' => 'reports.receivable-payments',
            'reports.receivable-card.view' => 'reports.receivable-card',
            'reports.receivable-aging.view' => 'reports.receivable-aging',
            'reports.expenditure.view' => 'reports.expenditure',
            'reports.receipt.view' => 'reports.receipt',
            'reports.transfers.view' => 'reports.transfers',
            'reports.deviations.view' => 'reports.deviations',
            'reports.stock-card.view' => 'reports.stock-card',
            'reports.position.view' => 'reports.position',
            'reports.inventory.view' => 'reports.inventory',
            'inquiry.view' => 'inquiry.index',
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
        foreach (self::landingRoutes() as $permission => $routeName) {
            if ($user->can($permission) && Route::has($routeName)) {
                return $routeName;
            }
        }

        if ($user->isSuperAdmin() && Route::has('admin.users.index')) {
            return 'admin.users.index';
        }

        return null;
    }
}
