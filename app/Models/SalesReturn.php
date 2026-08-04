<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturn extends Model
{
    use HasFactory;

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
}
