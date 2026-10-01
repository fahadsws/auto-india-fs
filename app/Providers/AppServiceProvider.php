<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Super Admin bypasses every permission check.
        Gate::before(fn ($user) => $user->hasRole('Super Admin') ? true : null);

        // Bulk-action config for admin DataTables comes from the current controller (see HandlesBulk).
        View::composer('admin.partials.dt', function ($view) {
            $c = request()->route()?->getController();
            if ($c && in_array(\App\Http\Controllers\Admin\Concerns\HandlesBulk::class, class_uses_recursive($c), true)) {
                $view->with($c->bulkViewData(request()));
            }
        });

        // Share site-wide data with the public layout.
        View::composer('site.*', function ($view) {
            static $cats = null;
            $cats ??= Schema::hasTable('categories') ? Category::where('is_active', true)->orderBy('name')->get() : collect();
            $view->with('navCategories', $cats);

            static $menus = null;
            $menus ??= Schema::hasTable('menu_items')
                ? ['header' => \App\Models\MenuItem::tree('header'), 'footer' => \App\Models\MenuItem::tree('footer')]
                : ['header' => collect(), 'footer' => collect()];
            $view->with('headerMenu', $menus['header'])->with('footerMenu', $menus['footer']);
        });
    }
}
