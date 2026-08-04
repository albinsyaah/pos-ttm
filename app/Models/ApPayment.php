<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApPayment extends Model
{
    use HasFactory;

    protected $table = 'ap_payments';

    protected $fillable = [
        'payment_number',
        'amount',
        'payment_date',
        'payment_method',
        'supplier_id'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
