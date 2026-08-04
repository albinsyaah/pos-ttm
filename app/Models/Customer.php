<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = [
        'code',
        'name',
        'phone',
        'address'
    ];

    public function salesOrders()
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }
    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }
    public function arPayments()
    {
        return $this->hasMany(ArPayment::class, 'customer_id');
    }
}
