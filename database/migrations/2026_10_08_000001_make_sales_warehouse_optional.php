<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A sale made on the cashier terminals no longer belongs to one warehouse: its
 * stock is taken from whichever warehouses have it, and the inventory ledger
 * records which. Sales made on the other pages still name their warehouse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversible once sales without a warehouse exist.
        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();
        });
    }
};
