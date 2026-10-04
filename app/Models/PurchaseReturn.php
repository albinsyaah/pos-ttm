<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'purchase_returns';

    protected $fillable = [
        'return_number',
        'return_date',
        'total_amount',
        'purchase_id'
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }
    public function purchaseReturnDetails()
    {
        return $this->hasMany(PurchaseReturnDetail::class, 'purchase_return_id');
    }

    /** Automatic number: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'return_number', 'prefix' => NumberingService::TYPE_PURCHASE_RETURN, 'date' => 'return_date'];
    }
}
