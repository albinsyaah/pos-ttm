<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $table = 'payment_methods';

    protected $fillable = [
        'code',
        'name',
        'is_cash',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Methods a user may pick for a new payment. Inactive ones stay on old records. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * The method used when a cash sale arrives without one (older pages, API
     * clients): the first active method that is flagged as cash (Tunai).
     */
    public static function defaultCash(): ?self
    {
        return static::active()->where('is_cash', true)->orderBy('id')->first();
    }

    /** True once any sale or payment points at this method (then it may not be deleted). */
    public function isInUse(): bool
    {
        return $this->sales()->exists()
            || $this->apPayments()->exists()
            || $this->arPayments()->exists();
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'payment_method_id');
    }

    public function apPayments()
    {
        return $this->hasMany(ApPayment::class, 'payment_method_id');
    }

    public function arPayments()
    {
        return $this->hasMany(ArPayment::class, 'payment_method_id');
    }
}
