<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturnDetail extends Model
{
    use HasFactory;

    protected $table = 'purchase_return_details';

    protected $fillable = [
        'qty',
        'reason',
        'purchase_return_id',
        'product_id'
    ];

    public function purchaseReturn()
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
