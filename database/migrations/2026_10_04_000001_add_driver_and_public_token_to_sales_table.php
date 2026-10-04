<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Two columns for printing:
     *  - driver_name: shown on the delivery note (surat jalan).
     *  - public_token: unguessable id used in the digital-receipt link that the
     *    receipt barcode points to, so the link never exposes the sequential id.
     *
     * Existing sales get a token back-filled so their receipts can be reprinted.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('driver_name', 100)->nullable()->after('warehouse_id');
            $table->string('public_token', 40)->nullable()->unique()->after('driver_name');
        });

        DB::table('sales')->whereNull('public_token')->orderBy('id')->select('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('sales')->where('id', $row->id)->update(['public_token' => Str::random(32)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn(['driver_name', 'public_token']);
        });
    }
};
