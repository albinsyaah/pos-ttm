<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArPayment extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'ar_payments';

    protected $fillable = [
        'payment_number',
        'amount',
        'payment_date',
        'payment_method_id',
        'customer_id'
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /** Automatic number: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'payment_number', 'prefix' => NumberingService::TYPE_AR_PAYMENT, 'date' => 'payment_date'];
    }
}
