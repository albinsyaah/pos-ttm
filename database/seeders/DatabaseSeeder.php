<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Order follows the migration/FK dependency chain: master data first,
     * then documents that reference it, then the ledgers/payments that are
     * derived from those documents.
     */
    public function run(): void
    {
        $this->call([
            // Roles, permissions & the default superadmin account.
            RolePermissionSeeder::class,

            // Master data.
            EmployeeSeeder::class,
            // WarehouseSeeder::class,
            // BrandSeeder::class,
            // ItemTypeSeeder::class,
            // ProductGroupSeeder::class,
            // CustomerSeeder::class,
            // SupplierSeeder::class,
            // UserSeeder::class,
            // ProductSeeder::class,
            // PriceSetupSeeder::class,
            // AssetSeeder::class,
            // ChartOfAccountSeeder::class,

            // Purchasing cycle (Procure to Pay).
            // PurchaseOrderSeeder::class,
            // PurchaseSeeder::class,
            // PurchaseReturnSeeder::class,
            // ApPaymentSeeder::class,

            // Sales cycle (Order to Cash).
            // SalesOrderSeeder::class,
            // SaleSeeder::class,
            // SalesReturnSeeder::class,
            // ArPaymentSeeder::class,

            // Warehouse-to-warehouse stock transfers.
            // InternalMutationSeeder::class,

            // Accounting: derived from the transactions above.
            // GeneralLedgerSeeder::class,
            // CashFlowSeeder::class,
        ]);
    }
}
