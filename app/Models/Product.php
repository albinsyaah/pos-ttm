<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'code',
        'name',
        'brand_id',
        'item_type_id',
        'product_group_id'
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }
    public function itemType()
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }
    public function productGroup()
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }
    public function priceSetups()
    {
        return $this->hasMany(PriceSetup::class, 'product_id');
    }
    public function purchaseOrderDetails()
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'product_id');
    }
    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class, 'product_id');
    }
    public function purchaseReturnDetails()
    {
        return $this->hasMany(PurchaseReturnDetail::class, 'product_id');
    }
    public function salesOrderDetails()
    {
        return $this->hasMany(SalesOrderDetail::class, 'product_id');
    }
    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class, 'product_id');
    }
    public function salesReturnDetails()
    {
        return $this->hasMany(SalesReturnDetail::class, 'product_id');
    }
    public function internalMutationDetails()
    {
        return $this->hasMany(InternalMutationDetail::class, 'product_id');
    }
    public function inventoryLedgers()
    {
        return $this->hasMany(InventoryLedger::class, 'product_id');
    }
}
