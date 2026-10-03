<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApPayment extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'ap_payments';

    protected $fillable = [
        'payment_number',
        'amount',
        'payment_date',
        'payment_method_id',
        'supplier_id',
        'purchase_id',
    ];

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class, 'purchase_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** Automatic number: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'payment_number', 'prefix' => NumberingService::TYPE_AP_PAYMENT, 'date' => 'payment_date'];
    }
}
