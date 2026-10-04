<?php

namespace App\Models;

use App\Models\Concerns\HasAutoNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;
    use HasAutoNumber;

    protected $table = 'employees';

    protected $fillable = [
        'code',
        'name',
        'position',
        'phone'
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'employee_id');
    }
    public function sales()
    {
        return $this->hasMany(Sale::class, 'salesman_id');
    }
    public function internalMutations()
    {
        return $this->hasMany(InternalMutation::class, 'requested_by');
    }

    /** Automatic code: see NumberingService. */
    public function autoNumberConfig(): array
    {
        return ['column' => 'code', 'series' => 'employee', 'width' => 4];
    }
}
