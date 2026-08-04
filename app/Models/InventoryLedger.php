<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLedger extends Model
{
    use HasFactory;

    protected $table = 'inventory_ledgers';

    protected $fillable = [
        'transaction_date',
        'reference_number',
        'type',
        'qty',
        'balance',
        'product_id',
        'warehouse_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
