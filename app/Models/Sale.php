<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory;

    protected $table = 'sales';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_CREDIT = 'credit';

    protected $fillable = [
        'invoice_number',
        'sale_date',
        'total_amount',
        'source',
        'payment_type',
        'payment_method_id',
        'sales_order_id',
        'customer_id',
        'salesman_id',
        'warehouse_id',
        'driver_name',
    ];

    /**
     * Every sale gets an unguessable token on creation. The receipt barcode
     * links to the digital receipt by this token, never by the numeric id.
     */
    protected static function booted(): void
    {
        static::creating(function (Sale $sale) {
            if (blank($sale->public_token)) {
                $sale->public_token = Str::random(32);
            }
        });
    }

    /**
     * Sales that count as a receivable (piutang): sold on credit to a
     * registered customer. Cash sales never enter the receivable reports,
     * even when a customer is attached to them.
     */
    public function scopeReceivable($query)
    {
        return $query->where('payment_type', self::PAYMENT_CREDIT)
            ->whereNotNull('customer_id');
    }

    /** Link the receipt barcode points to (public, no sign-in needed). */
    public function digitalReceiptUrl(): string
    {
        return route('receipts.show', $this->public_token);
    }

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
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
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
