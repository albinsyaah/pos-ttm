<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'assets';

    protected $fillable = [
        'asset_code',
        'name',
        'purchase_date',
        'value'
    ];

    /** Automatic code: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'asset_code', 'series' => 'asset', 'width' => 4];
    }
}
