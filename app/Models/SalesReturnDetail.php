<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReturnDetail extends Model
{
    use HasFactory;

    protected $table = 'sales_return_details';

    protected $fillable = [
        'qty',
        'sales_return_id',
        'product_id'
    ];

    public function salesReturn()
    {
        return $this->belongsTo(SalesReturn::class, 'sales_return_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
