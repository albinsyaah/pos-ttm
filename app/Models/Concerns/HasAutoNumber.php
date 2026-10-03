<?php

namespace App\Models\Concerns;

use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Model;

/**
 * Gives a model its number or code automatically.
 *
 * - On create, a blank number is filled in by NumberingService. A number that
 *   is supplied (import, seeder) is kept as it is.
 * - On update, a blank number is put back to what it was, so the number of an
 *   existing record never disappears or changes by accident.
 *
 * The model returns its settings from autoNumberConfig():
 *   documents: ['column' => 'invoice_number', 'prefix' => '11', 'date' => 'sale_date']
 *   master:    ['column' => 'code', 'series' => 'product', 'width' => 6]
 */
trait HasAutoNumber
{
    abstract public function autoNumberConfig(): array;

    protected static function bootHasAutoNumber(): void
    {
        static::creating(function (Model $model) {
            $column = $model->autoNumberConfig()['column'];

            if (blank($model->getAttribute($column))) {
                $model->setAttribute($column, app(NumberingService::class)->generate($model));
            }
        });

        static::updating(function (Model $model) {
            $column = $model->autoNumberConfig()['column'];

            if (blank($model->getAttribute($column))) {
                $model->setAttribute($column, $model->getOriginal($column));
            }
        });
    }
}
