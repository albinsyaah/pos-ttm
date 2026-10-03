<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'sales_returns';

    protected $fillable = [
        'return_number',
        'return_date',
        'total_amount',
        'sale_id'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
    public function salesReturnDetails()
    {
        return $this->hasMany(SalesReturnDetail::class, 'sales_return_id');
    }

    /** Automatic number: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'return_number', 'prefix' => NumberingService::TYPE_SALES_RETURN, 'date' => 'return_date'];
    }
}
