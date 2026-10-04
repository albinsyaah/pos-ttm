<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Automatic numbers and codes, digits only.
 *
 * Documents (nota, retur, pembayaran, mutasi):  TT YYMMDD NNNN  (12 digits)
 *   TT      document type (see TYPE_* below)
 *   YYMMDD  the document date
 *   NNNN    running number for that type on that day, starting at 0001
 *   e.g. 112610030001 = first sale of 3 Oct 2026.
 *
 * Master data (barang, pelanggan, ...): a plain running number, zero padded,
 * e.g. 000001. Existing codes that are not purely digits are left alone and
 * ignored when the series starts.
 *
 * A number is taken inside the same database transaction that saves the
 * record. If the save is rolled back, the counter goes back with it, so a
 * failed save leaves no gap in the numbering.
 */
class NumberingService
{
    public const TYPE_SALE = '11';
    public const TYPE_SALES_RETURN = '12';
    public const TYPE_SALES_ORDER = '13';
    public const TYPE_PURCHASE = '21';
    public const TYPE_PURCHASE_RETURN = '22';
    public const TYPE_PURCHASE_ORDER = '23';
    public const TYPE_AR_PAYMENT = '31';
    public const TYPE_AP_PAYMENT = '32';
    public const TYPE_TRANSFER = '41';
    public const TYPE_INTERNAL_RECEIPT = '42';
    public const TYPE_INTERNAL_EXPENDITURE = '43';
    public const TYPE_DEVIATION = '44';
    public const TYPE_ITEM_REQUEST = '45';

    /**
     * Number for a model that is about to be created. The model describes
     * itself through autoNumberConfig() (see HasAutoNumber).
     */
    public function generate(Model $model): string
    {
        $config = $model->autoNumberConfig();
        $table = $model->getTable();
        $column = $config['column'];

        if (isset($config['prefix'])) {
            return $this->document($config['prefix'], $table, $column, $model->getAttribute($config['date']));
        }

        return $this->code($config['series'], $table, $column, $config['width']);
    }

    /** Document number: type + date + four digit running number. */
    public function document(string $type, string $table, string $column, mixed $date = null): string
    {
        $day = $date ? Carbon::parse($date)->format('ymd') : now()->format('ymd');
        $prefix = $type.$day;

        $sequence = $this->allocate(
            "doc:{$type}:{$day}",
            fn (int $n) => DB::table($table)->where($column, $prefix.$this->pad($n, 4))->exists(),
        );

        return $prefix.$this->pad($sequence, 4);
    }

    /** Master data code: a zero padded running number. */
    public function code(string $series, string $table, string $column, int $width): string
    {
        $sequence = $this->allocate(
            "code:{$series}",
            fn (int $n) => DB::table($table)->where($column, $this->pad($n, $width))->exists(),
            fn () => $this->highestDigitsCode($table, $column),
        );

        return $this->pad($sequence, $width);
    }

    /**
     * Take the next free value of a series. Must run in a transaction (it
     * opens one itself when the caller has none) because the counter row is
     * locked until the surrounding save ends.
     *
     * $taken tells whether a candidate is already used, so a number typed in
     * by hand, imported, or seeded never collides with a generated one.
     * $seed gives the starting value when the series is used for the first time.
     */
    protected function allocate(string $series, callable $taken, ?callable $seed = null): int
    {
        return DB::transaction(function () use ($series, $taken, $seed) {
            if (! DB::table('number_sequences')->where('series', $series)->exists()) {
                DB::table('number_sequences')->insertOrIgnore([
                    'series' => $series,
                    'last_value' => $seed ? $seed() : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $row = DB::table('number_sequences')->where('series', $series)->lockForUpdate()->first();

            $value = (int) $row->last_value;
            do {
                $value++;
            } while ($taken($value));

            DB::table('number_sequences')->where('series', $series)->update([
                'last_value' => $value,
                'updated_at' => now(),
            ]);

            return $value;
        });
    }

    /** Highest value among codes that consist of digits only. */
    protected function highestDigitsCode(string $table, string $column): int
    {
        return (int) DB::table($table)
            ->pluck($column)
            ->filter(fn ($code) => is_string($code) && $code !== '' && ctype_digit($code) && strlen($code) <= 12)
            ->map(fn ($code) => (int) $code)
            ->max();
    }

    protected function pad(int $value, int $width): string
    {
        return str_pad((string) $value, $width, '0', STR_PAD_LEFT);
    }
}
