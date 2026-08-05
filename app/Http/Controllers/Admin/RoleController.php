<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Super Admin only (see routes/web.php: role:Super Admin). This is where
 * the Super Admin decides which modules each role — and therefore each
 * user assigned to it — can access.
 */
class RoleController extends Controller
{
    protected const PROTECTED_ROLE = 'Super Admin';

    public function index()
    {
        $roles = Role::orderBy('name')->get()->map(function (Role $role) {
            $role->users_count = User::role($role->name)->count();

            return $role;
        });

        return view('admin.roles.index', [
            'roles' => $roles,
            'modules' => AccessControl::modules(),
            'totalPermissions' => count(AccessControl::allPermissions()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRole($request);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions']);

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $this->validateRole($request, $role->id);

        if ($role->name === self::PROTECTED_ROLE) {
            // Super Admin always keeps every permission — it's also
            // granted a hard bypass in AppServiceProvider regardless.
            $data['permissions'] = array_keys(AccessControl::allPermissions());
        } else {
            $role->name = $data['name'];
        }

        $role->save();
        $role->syncPermissions($data['permissions']);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->name === self::PROTECTED_ROLE) {
            return back()->with('error', 'The Super Admin role can\'t be deleted.');
        }

        if (User::role($role->name)->exists()) {
            return back()->with('error', 'Reassign the users on this role before deleting it.');
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }

    protected function validateRole(Request $request, ?int $ignoreId = null): array
    {
        $allSlugs = array_keys(AccessControl::allPermissions());

        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->ignore($ignoreId),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in($allSlugs)],
        ]);
    }
}
