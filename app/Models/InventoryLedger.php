<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryLedger extends Model
{
    use HasFactory;

    public const TYPE_IN = 'IN';
    public const TYPE_OUT = 'OUT';
    public const TYPE_TRANSFER_IN = 'TRANSFER_IN';
    public const TYPE_TRANSFER_OUT = 'TRANSFER_OUT';

    protected $table = 'inventory_ledgers';

    protected $fillable = [
        'transaction_date',
        'reference_number',
        'type',
        'qty',
        'balance',
        'product_id',
        'warehouse_id',
        'source_type',
        'source_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * The transaction (purchase, sale, return, internal mutation, ...) that
     * produced this row. Null for rows written by the seeders.
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
