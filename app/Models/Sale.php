<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sale extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'sales';

    public const PAYMENT_CASH = 'cash';

    public const PAYMENT_CREDIT = 'credit';

    protected $fillable = [
        'invoice_number',
        'sale_date',
        'total_amount',
        'discount_percent',
        'discount_amount',
        'discount_total',
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

    /** True when the sale was given a discount. */
    public function hasDiscount(): bool
    {
        return (float) $this->discount_total > 0;
    }

    /** The lines added up, before the discount (total_amount is what is left after it). */
    public function subtotalBeforeDiscount(): float
    {
        return round((float) $this->total_amount + (float) $this->discount_total, 2);
    }

    /**
     * Short description of how the discount was entered, for receipts:
     * "10%", "Rp 10.000" or "10% + Rp 10.000". Empty when there is none.
     */
    public function discountDescription(): string
    {
        $parts = [];

        if ((float) $this->discount_percent > 0) {
            $parts[] = rtrim(rtrim(number_format((float) $this->discount_percent, 2, ',', '.'), '0'), ',').'%';
        }
        if ((float) $this->discount_amount > 0) {
            $parts[] = \App\Support\Money::rupiah($this->discount_amount);
        }

        return implode(' + ', $parts);
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

    /** Automatic number: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'invoice_number', 'prefix' => NumberingService::TYPE_SALE, 'date' => 'sale_date'];
    }

    /**
     * Sales that took stock from a warehouse: those that name it, plus sales made on a
     * cashier terminal (no warehouse of their own) whose goods came from it, as the
     * inventory ledger records. $productId narrows that to sales that took this product there.
     */
    public function scopeFromWarehouse($query, $warehouseId, $productColumn = null)
    {
        return $query->where(function ($q) use ($warehouseId, $productColumn) {
            $q->where($this->qualifyColumn('warehouse_id'), $warehouseId)
                ->orWhereExists(function ($ledger) use ($warehouseId, $productColumn) {
                    $ledger->selectRaw('1')
                        ->from('inventory_ledgers')
                        ->whereColumn('inventory_ledgers.source_id', $this->qualifyColumn('id'))
                        ->where('inventory_ledgers.source_type', $this->getMorphClass())
                        ->where('inventory_ledgers.warehouse_id', $warehouseId)
                        ->when($productColumn, fn ($q) => $q->whereColumn('inventory_ledgers.product_id', $productColumn));
                });
        });
    }
}
