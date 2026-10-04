<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every purchase gets a payment deadline: purchase date + 30 days. Existing
     * purchases are back-filled in PHP (not with DATE_ADD) so the migration
     * runs on MySQL and SQLite alike.
     */
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('purchase_date');
            $table->index('due_date');
        });

        DB::table('purchases')->orderBy('id')->select(['id', 'purchase_date'])->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::table('purchases')->where('id', $row->id)->update([
                    'due_date' => Carbon::parse($row->purchase_date)->addDays(30)->toDateString(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['due_date']);
            $table->dropColumn('due_date');
        });
    }
};
