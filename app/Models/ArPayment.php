<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArPayment extends Model
{
    use HasFactory;

    protected $table = 'ar_payments';

    protected $fillable = [
        'payment_number',
        'amount',
        'payment_date',
        'payment_method',
        'customer_id'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
