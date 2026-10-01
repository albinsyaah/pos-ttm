<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payable payment now settles one purchase invoice (partial payments
     * allowed). The column is nullable: payments recorded before this change
     * stay supplier-level and are applied to the oldest open invoices when the
     * balance is computed (see PayableService), so no history is rewritten.
     */
    public function up(): void
    {
        Schema::table('ap_payments', function (Blueprint $table) {
            $table->foreignId('purchase_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('purchases')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ap_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_id');
        });
    }
};
