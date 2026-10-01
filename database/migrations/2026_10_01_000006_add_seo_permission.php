<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $p = Permission::findOrCreate('seo.manage', 'web');
        foreach (['Admin', 'Editor'] as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'seo.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
