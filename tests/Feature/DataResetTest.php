<?php

namespace Tests\Feature;

use App\Models\AssistantSession;
use App\Models\Category;
use App\Models\ChatLog;
use App\Models\Lead;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\DataReset;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DataResetTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::findOrCreate('Super Admin', 'web');
        $u = User::factory()->create(['password' => 'Secret@123']);
        $u->assignRole('Super Admin');
        return $u;
    }

    private function data(): void
    {
        Setting::put('site.name', 'Keep me');
        $lead = Lead::create(['type' => 'chatbot', 'name' => 'A', 'phone' => '9876543210', 'status' => 'new']);
        $s = AssistantSession::create(['lead_id' => $lead->id, 'token' => str_repeat('a', 40)]);   // has a foreign key to leads
        ChatLog::create(['session_id' => 'x', 'assistant_session_id' => $s->id, 'question' => 'q', 'answer' => 'a', 'source' => 'none']);
        Category::create(['name' => 'News', 'slug' => 'news']);
    }

    public function test_service_clears_data_tables_but_keeps_users_settings_and_access_control(): void
    {
        $this->data();
        $admin = $this->superAdmin();
        Permission::findOrCreate('articles.view', 'web');

        $removed = DataReset::run();

        $this->assertArrayHasKey('leads', $removed);
        $this->assertSame(1, $removed['leads']);
        foreach (['leads', 'assistant_sessions', 'chat_logs', 'categories', 'menu_items'] as $t) $this->assertSame(0, DB::table($t)->count(), $t);
        $this->assertSame('Keep me', Setting::get('site.name'));
        $this->assertTrue(User::whereKey($admin->id)->exists());
        $this->assertTrue($admin->fresh()->hasRole('Super Admin'));
        $this->assertTrue(Permission::where('name', 'articles.view')->exists());
        $this->assertSame(1, Lead::create(['type' => 'chatbot', 'name' => 'B', 'phone' => '9876543211', 'status' => 'new'])->id);   // ids restart
        foreach (DataReset::KEEP as $kept) $this->assertArrayNotHasKey($kept, $removed);
    }

    public function test_optional_keep_protects_menus(): void
    {
        $menus = MenuItem::count();   // seeded by migrations
        $this->assertGreaterThan(0, $menus);
        $this->data();
        DataReset::run(['menu_items', 'not-a-table']);
        $this->assertSame($menus, MenuItem::count());
        $this->assertSame(0, Lead::count());
    }

    public function test_route_needs_the_permission_the_word_reset_and_the_password(): void
    {
        $this->data();
        $plain = User::factory()->create();
        Role::findOrCreate('Admin', 'web')->syncPermissions([Permission::findOrCreate('admin.access', 'web')]);
        $plain->assignRole('Admin');
        $this->actingAs($plain)->post('/admin/data-reset', ['confirm' => 'RESET', 'password' => 'x'])->assertForbidden();
        $this->assertSame(1, Lead::count());

        $admin = $this->superAdmin();
        $this->actingAs($admin)->post('/admin/data-reset', ['confirm' => 'reset', 'password' => 'Secret@123'])->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post('/admin/data-reset', ['confirm' => 'RESET', 'password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertSame(1, Lead::count());

        $this->actingAs($admin)->post('/admin/data-reset', ['confirm' => 'RESET', 'password' => 'Secret@123'])->assertRedirect('/admin/data-reset');
        $this->assertSame(0, Lead::count());
        $this->assertSame('Keep me', Setting::get('site.name'));
    }

    public function test_artisan_command_refuses_production_without_force_and_runs_with_it(): void
    {
        $this->data();
        $this->app['env'] = 'production';
        $this->artisan('data:reset')->assertFailed();
        $this->assertSame(1, Lead::count());
        $this->artisan('data:reset', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, Lead::count());
    }

    public function test_every_permission_used_by_the_sidebar_and_routes_is_defined_and_editable(): void
    {
        $defined = collect(DatabaseSeeder::PERMISSIONS)->flatten()->all();
        $layout = file_get_contents(resource_path('views/admin/layout.blade.php'));
        preg_match_all("/@can(?:any)?\\(\\[?([^)]*?)\\]?\\)/", $layout, $m);
        $used = collect($m[1])->flatMap(fn ($s) => preg_match_all("/'([a-z_.]+)'/", $s, $x) ? $x[1] : [])
            ->merge((function () { preg_match_all("/permission:([a-z_.]+)/", file_get_contents(base_path('routes/web.php')), $r); return $r[1]; })())
            ->unique()->values()->all();
        $this->assertEmpty(array_diff($used, $defined), 'Used but not in DatabaseSeeder::PERMISSIONS (so not editable in Roles): '.implode(', ', array_diff($used, $defined)));
    }

    public function test_migration_gives_existing_roles_the_split_permissions_without_widening_access(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $editor = Role::findOrCreate('Editor', 'web')->givePermissionTo(Permission::findOrCreate('cars.manage', 'web'));
        $sales = Role::findOrCreate('Sales', 'web')->givePermissionTo(Permission::findOrCreate('leads.view', 'web'));
        $ops = Role::findOrCreate('Ops', 'web')->givePermissionTo(Permission::findOrCreate('settings.manage', 'web'));
        Role::findOrCreate('Admin', 'web');

        (require database_path('migrations/2026_10_03_000002_add_missing_admin_permissions.php'))->up();

        $this->assertTrue($editor->fresh()->hasPermissionTo('comparisons.manage') && $editor->fresh()->hasPermissionTo('car_masters.manage'));
        $this->assertFalse($editor->fresh()->hasPermissionTo('menus.manage'));
        $this->assertTrue($ops->fresh()->hasPermissionTo('home.manage') && $ops->fresh()->hasPermissionTo('menus.manage'));
        $this->assertFalse($sales->fresh()->hasPermissionTo('comparisons.manage'));
        $this->assertTrue(Role::findByName('Admin', 'web')->hasPermissionTo('assistant.manage'));
        $this->assertFalse(Role::findByName('Admin', 'web')->hasPermissionTo('data.reset'));
    }
}
