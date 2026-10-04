<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate cash sales from credit sales (piutang).
     *
     * Until now every sale with a customer counted as a receivable. The
     * default is 'credit' so existing rows and the older sales pages keep
     * behaving exactly as before; the checkout terminal sets the value
     * explicitly.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_type', 10)->default('credit')->after('source')->index();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['payment_type']);
            $table->dropColumn('payment_type');
        });
    }
};
