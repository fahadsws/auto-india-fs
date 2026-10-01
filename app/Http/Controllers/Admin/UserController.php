<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesBulk;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use HandlesBulk;

    /** Only Super Admins may hand out or touch the Super Admin role. */
    private function roles()
    {
        return Role::orderBy('name')->get()->reject(fn ($r) => $r->name === 'Super Admin' && ! auth()->user()->hasRole('Super Admin'));
    }

    public function index() { return view('admin.users.index', ['roles' => $this->roles()]); }

    public function data(Request $r)
    {
        $q = User::with('roles')->withCount('articles')
            ->when($r->role, fn ($x, $v) => $x->whereHas('roles', fn ($w) => $w->where('name', $v)))
            ->when($r->status !== null && $r->status !== '', fn ($x) => $x->where('is_active', (bool) $r->status));
        \App\Support\DataTable::dateRange($q, $r->range);

        return \App\Support\DataTable::make($q, $r, ['name', null, null, 'created_at', null, null], ['name', 'email'],
            fn (User $u) => [
                '<div class="d-flex align-items-center"><img src="'.e($u->avatar_url).'" class="rounded-circle me-3" width="38" height="38" alt=""><div><span class="fw-medium">'.e($u->name).'</span><br><small class="text-muted">'.e($u->email).'</small></div></div>',
                $u->roles->map(fn ($x) => \App\Support\Ui::badge($x->name, $x->name === 'Super Admin' ? 'danger' : 'primary'))->implode(' '),
                $u->articles_count, \App\Support\Ui::date($u->created_at, 'd M Y'), \App\Support\Ui::badge($u->is_active ? 'Active' : 'Disabled', $u->is_active ? 'success' : 'secondary'),
                \App\Support\Ui::actions(['edit' => route('admin.users.edit', $u), 'delete' => $u->id !== auth()->id() ? route('admin.users.destroy', $u) : null, 'delete_msg' => 'Delete '.$u->name.'? Their articles stay but lose the author.']),
            ], ['name', 'asc']);
    }
    public function create() { return view('admin.users.form', ['user' => new User(['is_active' => true]), 'roles' => $this->roles()]); }

    public function edit(User $user)
    {
        abort_if($user->hasRole('Super Admin') && ! auth()->user()->hasRole('Super Admin'), 403);
        return view('admin.users.form', ['user' => $user, 'roles' => $this->roles()]);
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:100', 'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8', 'bio' => 'nullable|string|max:500', 'roles' => 'nullable|array',
        ]);
        $user = User::create(['name' => $d['name'], 'email' => $d['email'], 'password' => $d['password'], 'bio' => $d['bio'] ?? null, 'is_active' => $r->boolean('is_active')]);
        $user->syncRoles($this->allowedRoles($d['roles'] ?? []));
        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function update(Request $r, User $user)
    {
        abort_if($user->hasRole('Super Admin') && ! auth()->user()->hasRole('Super Admin'), 403);
        $d = $r->validate([
            'name' => 'required|string|max:100', 'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8', 'bio' => 'nullable|string|max:500', 'roles' => 'nullable|array',
        ]);
        $user->fill(['name' => $d['name'], 'email' => $d['email'], 'bio' => $d['bio'] ?? null]);
        if (! empty($d['password'])) $user->password = $d['password'];
        if ($user->id !== auth()->id()) $user->is_active = $r->boolean('is_active'); // can't lock yourself out
        $user->save();
        if ($user->id !== auth()->id()) $user->syncRoles($this->allowedRoles($d['roles'] ?? []));
        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot delete your own account.');
        abort_if($user->hasRole('Super Admin') && ! auth()->user()->hasRole('Super Admin'), 403);
        $user->delete();
        return back()->with('success', 'User deleted.');
    }

    private function allowedRoles(array $names): array
    {
        return $this->roles()->pluck('name')->intersect($names)->values()->all();
    }
    protected function bulkBase(Request $r): \Illuminate\Database\Eloquent\Builder
    {
        // Never touch yourself, and only Super Admins may touch Super Admins.
        return User::query()->where('id', '!=', $r->user()->id)
            ->when(! $r->user()->hasRole('Super Admin'), fn ($q) => $q->whereDoesntHave('roles', fn ($w) => $w->where('name', 'Super Admin')));
    }

    protected function bulkActions(Request $r): array
    {
        $roles = $this->roles()->map(fn ($x) => [$x->name, $x->name])->values()->all();
        return [
            'enable' => ['label' => 'Enable accounts', 'do' => fn (User $m) => $m->update(['is_active' => true]) || true],
            'disable' => ['label' => 'Disable accounts', 'do' => fn (User $m) => $m->update(['is_active' => false]) || true],
            'role' => ['label' => 'Set role', 'options' => $roles, 'confirm' => 'Replaces each selected user\'s roles with the chosen one.',
                'do' => fn (User $m, Request $r) => in_array($r->value, array_column($roles, 0), true) && (bool) $m->syncRoles([$r->value])],
            'delete' => ['label' => 'Delete permanently', 'danger' => true, 'confirm' => 'Their articles stay but lose the author.', 'do' => fn (User $m) => (bool) $m->delete()],
        ];
    }
}
