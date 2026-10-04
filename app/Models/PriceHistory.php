<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PriceHistory extends Model
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';

    protected $table = 'price_histories';

    protected $fillable = [
        'price_setup_id',
        'product_id',
        'price_category',
        'action',
        'old_amount',
        'new_amount',
        'old_effective_date',
        'new_effective_date',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'old_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'old_effective_date' => 'date',
            'new_effective_date' => 'date',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Write one log row for a change to a price setup.
     *
     * $before is the row as it was (null when created), $after the row as it
     * is now (null when deleted). The acting user is the logged-in user.
     */
    public static function record(string $action, ?PriceSetup $before, ?PriceSetup $after): self
    {
        $subject = $after ?? $before;

        return static::create([
            'price_setup_id' => $subject->id,
            'product_id' => $subject->product_id,
            'price_category' => $subject->price_category,
            'action' => $action,
            'old_amount' => $before?->amount,
            'new_amount' => $after?->amount,
            'old_effective_date' => $before?->effective_date,
            'new_effective_date' => $after?->effective_date,
            'user_id' => Auth::id(),
        ]);
    }
}
