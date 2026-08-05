<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
    use Notifiable;

    /**
     * Permissions/roles on this model are checked against the 'web' guard.
     */
    protected string $guard_name = 'web';

    protected $table = 'users';

    protected $fillable = [
        'username',
        'password',
        'role',
        'employee_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * True if this account has been disabled by a Super Admin.
     */
    public function isDisabled(): bool
    {
        return ! $this->is_active;
    }

    /**
     * True if this account holds the built-in Super Admin role, which
     * always passes every authorization check (see AppServiceProvider).
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }

    /**
     * A friendly display name: employee name, falling back to username.
     */
    public function displayName(): string
    {
        return $this->employee->name ?? $this->username;
    }
}
