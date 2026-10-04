<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    use HasFactory;

    protected $table = 'sale_details';

    protected $fillable = [
        'qty',
        'price',
        'sale_id',
        'product_id'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * For queries that already join `sales`: lines of sales that took this product from a
     * warehouse. A sale names its warehouse, or (cashier terminals) the inventory ledger
     * says where this product came from. A line split over several warehouses counts in full
     * for each of them.
     */
    public function scopeFromWarehouseOfLine($query, $warehouseId)
    {
        return $query->where(function ($q) use ($warehouseId) {
            $q->where('sales.warehouse_id', $warehouseId)
                ->orWhereExists(function ($ledger) use ($warehouseId) {
                    $ledger->selectRaw('1')
                        ->from('inventory_ledgers')
                        ->whereColumn('inventory_ledgers.source_id', 'sale_details.sale_id')
                        ->whereColumn('inventory_ledgers.product_id', 'sale_details.product_id')
                        ->where('inventory_ledgers.source_type', (new Sale)->getMorphClass())
                        ->where('inventory_ledgers.warehouse_id', $warehouseId);
                });
        });
    }
}
