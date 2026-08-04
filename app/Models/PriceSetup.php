<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriceSetup extends Model
{
    use HasFactory;

    protected $table = 'price_setups';

    protected $fillable = [
        'price_category',
        'amount',
        'effective_date',
        'product_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
