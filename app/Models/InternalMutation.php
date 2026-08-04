<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalMutation extends Model
{
    use HasFactory;

    protected $table = 'internal_mutations';

    protected $fillable = [
        'mutation_number',
        'type',
        'mutation_date',
        'status',
        'from_warehouse_id',
        'to_warehouse_id',
        'requested_by'
    ];

    public function fromWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }
    public function toWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }
    public function requestedBy()
    {
        return $this->belongsTo(Employee::class, 'requested_by');
    }
    public function internalMutationDetails()
    {
        return $this->hasMany(InternalMutationDetail::class, 'internal_mutation_id');
    }
}
