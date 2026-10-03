<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gives every admin sidebar item its own permission. Previously Comparisons and Car masters rode on `cars.manage`, Home settings and
 * Header & Footer menus rode on `settings.manage`, and `assistant.manage` was missing from the role editor.
 * Existing roles keep exactly the access they had: whoever had the parent permission gets the new one.
 * `data.reset` (Reset site data) is granted to nobody - Super Admin has everything automatically.
 */
return new class extends Migration {
    /** new permission => the permission whose holders should receive it */
    private const INHERIT = [
        'comparisons.manage' => 'cars.manage',
        'car_masters.manage' => 'cars.manage',
        'home.manage' => 'settings.manage',
        'menus.manage' => 'settings.manage',
        'assistant.manage' => null,      // already granted to Admin by an earlier migration; just make sure it exists
        'data.reset' => null,
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::INHERIT as $name => $parent) {
            $perm = Permission::findOrCreate($name, 'web');
            if (! $parent) continue;
            Role::whereHas('permissions', fn ($q) => $q->where('name', $parent)->where('guard_name', 'web'))->get()
                ->each(fn ($role) => $role->givePermissionTo($perm));
        }
        Role::where('name', 'Admin')->where('guard_name', 'web')->first()?->givePermissionTo('assistant.manage');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (['comparisons.manage', 'car_masters.manage', 'home.manage', 'menus.manage', 'data.reset'] as $name) {
            Permission::where('name', $name)->where('guard_name', 'web')->delete();
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
