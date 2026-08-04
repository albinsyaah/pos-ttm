<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    use HasFactory;

    protected $table = 'sales_orders';

    protected $fillable = [
        'so_number',
        'order_date',
        'status',
        'customer_id'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function salesOrderDetails()
    {
        return $this->hasMany(SalesOrderDetail::class, 'sales_order_id');
    }
    public function sales()
    {
        return $this->hasMany(Sale::class, 'sales_order_id');
    }
}
