<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'purchase_orders';

    protected $fillable = [
        'po_number',
        'order_date',
        'status',
        'supplier_id'
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'purchase_order_id');
    }
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'purchase_order_id');
    }

    /** Automatic number: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'po_number', 'prefix' => NumberingService::TYPE_PURCHASE_ORDER, 'date' => 'order_date'];
    }
}
