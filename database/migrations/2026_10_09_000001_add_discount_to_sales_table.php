<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discount on a whole sale (entered on the cashier terminals).
 *
 *  - discount_percent: the percentage the cashier typed (0 to 100)
 *  - discount_amount : the fixed rupiah amount the cashier typed
 *  - discount_total  : what the two come to in rupiah, as actually given
 *
 * sales.total_amount keeps meaning "what the customer owes": it is the lines minus
 * discount_total. So receivables, payment-method income and the dashboard stay correct
 * without knowing about discounts. Sales that existed before have no discount (0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('total_amount');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_percent');
            $table->decimal('discount_total', 15, 2)->default(0)->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'discount_amount', 'discount_total']);
        });
    }
};
