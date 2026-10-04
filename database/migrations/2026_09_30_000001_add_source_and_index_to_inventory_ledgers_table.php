<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * - source_type / source_id: which transaction (purchase, sale, return,
     *   internal mutation, ...) produced the ledger row, so StockService can
     *   find and reverse it reliably. Nullable because rows written by the
     *   seeders (ManagesInventoryLedger) have no source.
     * - composite index: StockService looks up the latest balance per
     *   product + warehouse on every stock movement.
     */
    public function up(): void
    {
        Schema::table('inventory_ledgers', function (Blueprint $table) {
            $table->nullableMorphs('source');
            $table->index(['product_id', 'warehouse_id', 'id'], 'inventory_ledgers_product_warehouse_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_ledgers', function (Blueprint $table) {
            $table->dropIndex('inventory_ledgers_product_warehouse_id_index');
            $table->dropMorphs('source');
        });
    }
};
