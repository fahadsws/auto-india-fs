<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use HandlesBulk;

    private const LOCKED = ['Super Admin'];

    public function index() { return view('admin.roles.index'); }

    public function data(Request $r)
    {
        $q = Role::withCount(['users', 'permissions'])
            ->when($r->usage === 'used', fn ($x) => $x->has('users'))
            ->when($r->usage === 'unused', fn ($x) => $x->doesntHave('users'));

        return \App\Support\DataTable::make($q, $r, ['name', null, null, null], ['name'],
            fn (Role $role) => [
                '<span class="fw-medium">'.e($role->name).'</span>', $role->users_count,
                $role->name === 'Super Admin' ? '<span class="text-muted">Everything (locked)</span>' : \App\Support\Ui::badge($role->permissions_count.' permission(s)', 'secondary'),
                $role->name === 'Super Admin' ? '' : \App\Support\Ui::actions(['edit' => route('admin.roles.edit', $role), 'delete' => $role->users_count === 0 ? route('admin.roles.destroy', $role) : null, 'delete_msg' => 'Delete this role?']),
            ], ['id', 'asc']);
    }
    public function create() { return view('admin.roles.form', ['role' => new Role(), 'groups' => DatabaseSeeder::PERMISSIONS, 'granted' => []]); }

    public function edit(Role $role)
    {
        abort_if(in_array($role->name, self::LOCKED), 403, 'The Super Admin role always has every permission.');
        return view('admin.roles.form', ['role' => $role, 'groups' => DatabaseSeeder::PERMISSIONS, 'granted' => $role->permissions->pluck('name')->all()]);
    }

    public function store(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:50|unique:roles,name', 'permissions' => 'nullable|array']);
        Role::create(['name' => $d['name'], 'guard_name' => 'web'])->syncPermissions($d['permissions'] ?? []);
        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function update(Request $r, Role $role)
    {
        abort_if(in_array($role->name, self::LOCKED), 403);
        $d = $r->validate(['name' => 'required|string|max:50|unique:roles,name,'.$role->id, 'permissions' => 'nullable|array']);
        $role->update(['name' => $d['name']]);
        $role->syncPermissions($d['permissions'] ?? []);
        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        abort_if(in_array($role->name, self::LOCKED) || $role->users()->exists(), 403, 'Role is protected or still assigned to users.');
        $role->delete();
        return back()->with('success', 'Role deleted.');
    }
    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder
    {
        return Role::query()->whereNotIn('name', self::LOCKED)->doesntHave('users'); // protected roles and roles in use are skipped
    }

    protected function bulkActions(Request $r): array
    {
        return ['delete' => ['label' => 'Delete unused roles', 'danger' => true, 'confirm' => 'Roles assigned to users and the Super Admin role are skipped.', 'do' => fn (Role $m) => (bool) $m->delete()]];
    }
}
