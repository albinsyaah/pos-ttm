<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';

    protected $fillable = [
        'code',
        'name',
        'location'
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'warehouse_id');
    }
    public function sales()
    {
        return $this->hasMany(Sale::class, 'warehouse_id');
    }
    public function fromWarehouseInternalMutations()
    {
        return $this->hasMany(InternalMutation::class, 'from_warehouse_id');
    }
    public function toWarehouseInternalMutations()
    {
        return $this->hasMany(InternalMutation::class, 'to_warehouse_id');
    }
    public function inventoryLedgers()
    {
        return $this->hasMany(InventoryLedger::class, 'warehouse_id');
    }
}
