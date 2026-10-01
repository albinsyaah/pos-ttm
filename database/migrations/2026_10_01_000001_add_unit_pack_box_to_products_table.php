<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satuan / pack / box.
     *
     * Stock is always stored in the smallest unit (satuan). Pack and box are
     * optional bigger packagings, each with its conversion ("isi") expressed in
     * satuan: 1 pack = pack_qty satuan, 1 box = box_qty satuan.
     * All quantities are integers; there are no decimals.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('unit_name', 30)->default('pcs')->after('name');
            $table->string('pack_name', 30)->nullable()->after('unit_name');
            $table->unsignedInteger('pack_qty')->nullable()->after('pack_name');
            $table->string('box_name', 30)->nullable()->after('pack_qty');
            $table->unsignedInteger('box_qty')->nullable()->after('box_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unit_name', 'pack_name', 'pack_qty', 'box_name', 'box_qty']);
        });
    }
};
