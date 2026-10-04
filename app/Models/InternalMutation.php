<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use App\Services\NumberingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalMutation extends Model
{
    use HasFactory;
    use HasAutoNumber;

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

    /** Automatic number: see NumberingService. The type digit follows the kind of mutation. */
    public function autoNumberConfig(): array
    {
        $prefix = match ($this->type) {
            'Transfer Antar Gudang' => NumberingService::TYPE_TRANSFER,
            'Internal Receipt' => NumberingService::TYPE_INTERNAL_RECEIPT,
            'Internal Expenditure' => NumberingService::TYPE_INTERNAL_EXPENDITURE,
            'Deviation' => NumberingService::TYPE_DEVIATION,
            'Item Request' => NumberingService::TYPE_ITEM_REQUEST,
            default => '49',
        };

        return ['column' => 'mutation_number', 'prefix' => $prefix, 'date' => 'mutation_date'];
    }
}
