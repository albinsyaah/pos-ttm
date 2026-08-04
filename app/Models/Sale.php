<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    protected $fillable = [
        'invoice_number',
        'sale_date',
        'total_amount',
        'source',
        'sales_order_id',
        'customer_id',
        'salesman_id',
        'warehouse_id'
    ];

    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    public function salesman()
    {
        return $this->belongsTo(Employee::class, 'salesman_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class, 'sale_id');
    }
    public function salesReturns()
    {
        return $this->hasMany(SalesReturn::class, 'sale_id');
    }
}
