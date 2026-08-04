<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalMutationDetail extends Model
{
    use HasFactory;

    protected $table = 'internal_mutation_details';

    protected $fillable = [
        'qty',
        'notes',
        'internal_mutation_id',
        'product_id'
    ];

    public function internalMutation()
    {
        return $this->belongsTo(InternalMutation::class, 'internal_mutation_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
