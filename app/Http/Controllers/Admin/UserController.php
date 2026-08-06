<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Super Admin only (see routes/web.php: role:Super Admin). Lets the Super
 * Admin create accounts, assign roles, and enable/disable access per user.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $users = User::query()
            ->with(['employee', 'roles'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('username', 'like', "%{$search}%");
            })
            ->orderBy('username')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
            'roles' => Role::orderBy('name')->get(),
            'employees' => Employee::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateUser($request);

        $roles = Role::whereIn('id', $data['roles'])->get();

        $user = User::create([
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'employee_id' => $data['employee_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'role' => $roles->pluck('name')->first(),
        ]);

        // Pass Role model instances (not raw id strings) — spatie/laravel-permission
        // only treats *actual integers* as ids; a numeric string like "2" is looked
        // up as a role *name* instead and throws RoleDoesNotExist.
        $user->syncRoles($roles);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validateUser($request, $user->id);

        $isSelf = $user->id === Auth::id();

        $payload = [
            'username' => $data['username'],
            'employee_id' => $data['employee_id'] ?? null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        // A Super Admin can't lock themselves out by deactivating their own
        // account or stripping their own Super Admin role.
        if (! $isSelf) {
            $payload['is_active'] = $request->boolean('is_active');
        }

        $roles = Role::whereIn('id', $data['roles'])->get();

        $payload['role'] = $roles->pluck('name')->first();

        $user->update($payload);

        // Pass Role model instances (not raw id strings) — spatie/laravel-permission
        // only treats *actual integers* as ids; a numeric string like "2" is looked
        // up as a role *name* instead and throws RoleDoesNotExist.
        if (! $isSelf) {
            $user->syncRoles($roles);
        } else {
            $isCurrentlySuperAdmin = $user->roles()->where('name', 'Super Admin')->exists();

            if ($isCurrentlySuperAdmin && ! $roles->contains('name', 'Super Admin')) {
                $roles->push(Role::where('name', 'Super Admin')->where('guard_name', 'web')->first());
            }

            $user->syncRoles($roles->filter());
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', "You can't disable your own account.");
        }

        if ($user->isSuperAdmin() && $user->is_active && $this->activeSuperAdminCount() <= 1) {
            return back()->with('error', 'At least one active Super Admin must remain.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'User re-enabled.' : 'User disabled.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', "You can't delete your own account.");
        }

        if ($user->isSuperAdmin() && $this->activeSuperAdminCount() <= 1) {
            return back()->with('error', 'At least one Super Admin must remain.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    protected function activeSuperAdminCount(): int
    {
        return User::role('Super Admin')->where('is_active', true)->count();
    }

    protected function validateUser(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'username' => [
                'required', 'string', 'max:100',
                Rule::unique('users', 'username')->ignore($ignoreId),
            ],
            'password' => [$ignoreId ? 'nullable' : 'required', 'string', 'min:8'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ], [
            'roles.required' => 'Select at least one role for this user.',
            'roles.min' => 'Select at least one role for this user.',
        ]);

        return $data;
    }
}
