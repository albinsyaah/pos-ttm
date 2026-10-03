<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'suppliers';

    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'address'
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'supplier_id');
    }
    public function apPayments()
    {
        return $this->hasMany(ApPayment::class, 'supplier_id');
    }

    /** Automatic code: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'code', 'series' => 'supplier', 'width' => 4];
    }
}
