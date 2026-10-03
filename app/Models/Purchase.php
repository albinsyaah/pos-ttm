<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Purchase extends Model
{
    use HasFactory;
    use HasAutoNumber;

    /** Days a supplier invoice may stay unpaid. */
    public const PAYMENT_TERM_DAYS = 30;

    /** Days before the due date that the reminder starts. */
    public const REMINDER_DAYS = 7;

    /**
     * Statuses that create a payable. A pending or cancelled purchase has not
     * brought goods in, so it owes nothing yet. The capitalised spellings are
     * what the demo seeder writes.
     */
    public const PAYABLE_STATUSES = ['received', 'Received', 'completed', 'Completed'];

    protected $table = 'purchases';

    protected $fillable = [
        'invoice_number',
        'purchase_date',
        'due_date',
        'total_amount',
        'status',
        'purchase_order_id',
        'supplier_id',
        'warehouse_id'
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    protected static function booted(): void
    {
        // The deadline always follows the purchase date, whichever code path
        // creates or edits the purchase.
        static::saving(function (Purchase $purchase) {
            if ($purchase->purchase_date !== null && ($purchase->due_date === null || $purchase->isDirty('purchase_date'))) {
                $purchase->due_date = Carbon::parse($purchase->purchase_date)
                    ->startOfDay()
                    ->addDays(self::PAYMENT_TERM_DAYS)
                    ->toDateString();
            }
        });
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class, 'purchase_id');
    }
    public function payments()
    {
        return $this->hasMany(ApPayment::class, 'purchase_id');
    }
    public function purchaseReturns()
    {
        return $this->hasMany(PurchaseReturn::class, 'purchase_id');
    }

    /** Automatic number: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'invoice_number', 'prefix' => NumberingService::TYPE_PURCHASE, 'date' => 'purchase_date'];
    }
}
