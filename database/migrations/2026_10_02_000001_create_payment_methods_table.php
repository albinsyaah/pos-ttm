<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Tables whose free-text `payment_method` column becomes a reference to the master. */
    private const PAYMENT_TABLES = ['ap_payments', 'ar_payments'];

    /** Old spellings found in AP/AR rows (code constants and seeder labels) => master code. */
    private const ALIASES = [
        'cash' => 'cash',
        'tunai' => 'cash',
        'bank_transfer' => 'bank_transfer',
        'transfer bank' => 'bank_transfer',
        'transfer' => 'bank_transfer',
        'qris' => 'qris',
    ];

    /**
     * Payment method master data (tunai, transfer, QRIS, ...), managed by admin.
     *
     * - AP/AR payments: the old `payment_method` text column is converted to
     *   `payment_method_id`. Known spellings map onto the three starter methods;
     *   any other value found in the data (e.g. "check", "giro") becomes an
     *   inactive method so old rows keep their label and nothing is lost.
     * - Sales: new nullable `payment_method_id`. Only paid-on-the-spot sales
     *   carry a method; credit sales (piutang) get theirs when the customer pays.
     *   Existing cash sales are marked Tunai.
     */
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100)->unique();
            // Cash lands in the cash account, every other method in the bank account.
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('payment_methods')->insert([
            ['code' => 'cash', 'name' => 'Tunai', 'is_cash' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'bank_transfer', 'name' => 'Transfer', 'is_cash' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'qris', 'name' => 'QRIS', 'is_cash' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach (['ap_payments', 'ar_payments', 'sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('payment_method_id')->nullable()
                    ->constrained('payment_methods')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        $ids = DB::table('payment_methods')->pluck('id', 'code')->all();

        foreach (self::PAYMENT_TABLES as $tableName) {
            $legacyValues = DB::table($tableName)->distinct()->pluck('payment_method');

            foreach ($legacyValues as $legacy) {
                $legacy = (string) $legacy;
                $key = mb_strtolower(trim($legacy));
                $code = self::ALIASES[$key] ?? (Str::slug($key, '_') ?: 'other');

                if (! isset($ids[$code])) {
                    $name = trim($legacy) !== '' ? Str::limit(Str::title(str_replace('_', ' ', trim($legacy))), 100, '') : 'Lainnya';
                    // Keep names unique even if two spellings collapse to one title.
                    while (DB::table('payment_methods')->where('name', $name)->exists()) {
                        $name .= ' (lama)';
                    }

                    $ids[$code] = DB::table('payment_methods')->insertGetId([
                        'code' => $code,
                        'name' => $name,
                        'is_cash' => false,
                        'is_active' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table($tableName)->where('payment_method', $legacy)->update(['payment_method_id' => $ids[$code]]);
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }

        DB::table('sales')
            ->where('payment_type', 'cash')
            ->whereNull('payment_method_id')
            ->update(['payment_method_id' => $ids['cash']]);
    }

    public function down(): void
    {
        $codes = DB::table('payment_methods')->pluck('code', 'id')->all();

        foreach (self::PAYMENT_TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('payment_method')->default('cash');
            });

            foreach ($codes as $id => $code) {
                DB::table($tableName)->where('payment_method_id', $id)->update(['payment_method' => $code]);
            }
        }

        foreach (['ap_payments', 'ar_payments', 'sales'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('payment_method_id');
            });
        }

        Schema::dropIfExists('payment_methods');
    }
};
