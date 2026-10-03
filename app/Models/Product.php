<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'products';

    protected $fillable = [
        'code',
        'name',
        'unit_name',
        'pack_name',
        'pack_qty',
        'box_name',
        'box_qty',
        'brand_id',
        'item_type_id',
        'product_group_id'
    ];

    protected function casts(): array
    {
        return [
            'pack_qty' => 'integer',
            'box_qty' => 'integer',
        ];
    }

    public function hasPack(): bool
    {
        return filled($this->pack_name) && (int) $this->pack_qty > 1;
    }

    public function hasBox(): bool
    {
        return filled($this->box_name) && (int) $this->box_qty > 1;
    }

    /**
     * Break a quantity (in satuan) into box / pack / satuan.
     *
     * Returns ['box' => int, 'pack' => int, 'unit' => int]. Boxes are taken
     * first, then packs from what is left; the remainder stays in satuan.
     * Packagings the product does not define are skipped.
     */
    public function breakdownQuantity(int $qty): array
    {
        $qty = max(0, $qty);
        $box = 0;
        $pack = 0;

        if ($this->hasBox()) {
            $box = intdiv($qty, (int) $this->box_qty);
            $qty -= $box * (int) $this->box_qty;
        }

        if ($this->hasPack()) {
            $pack = intdiv($qty, (int) $this->pack_qty);
            $qty -= $pack * (int) $this->pack_qty;
        }

        return ['box' => $box, 'pack' => $pack, 'unit' => $qty];
    }

    /**
     * Human readable quantity, e.g. "2 box 3 pack 5 pcs".
     * Zero parts are omitted; zero stock is shown as "0 pcs". A negative
     * quantity keeps its sign in front ("-2 box 3 pcs" style, sign once).
     */
    public function formatQuantity(int $qty): string
    {
        $sign = $qty < 0 ? '-' : '';
        $parts = $this->breakdownQuantity(abs($qty));
        $unit = $this->unit_name ?: 'pcs';

        $out = [];
        if ($parts['box'] > 0) {
            $out[] = $parts['box'].' '.$this->box_name;
        }
        if ($parts['pack'] > 0) {
            $out[] = $parts['pack'].' '.$this->pack_name;
        }
        if ($parts['unit'] > 0 || $out === []) {
            $out[] = $parts['unit'].' '.$unit;
        }

        return $sign.implode(' ', $out);
    }

    /**
     * Convert box / pack / satuan counts into a total in satuan.
     */
    public function toBaseQuantity(int $box = 0, int $pack = 0, int $unit = 0): int
    {
        return $box * (int) $this->box_qty + $pack * (int) $this->pack_qty + $unit;
    }

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

    /** Automatic code: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'code', 'series' => 'product', 'width' => 6];
    }
}
