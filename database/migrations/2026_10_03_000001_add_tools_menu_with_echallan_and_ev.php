<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds a "Tools" dropdown (header) and column (footer) with the calculators, mechanic, E-challan and EV charging finder. Editable in Admin -> Menus. */
return new class extends Migration {
    private const TOOLS = [
        ['Car EMI calculator', '/car-emi-calculator'], ['Cost per km calculator', '/cost-per-km-calculator'], ['Online mechanic', '/online-mechanic'],
        ['E-challan check', '/e-challan'], ['EV charging stations', '/ev-charging-stations'], ['Roast my car', '/roast-my-car'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) return;
        $now = now();

        foreach (['header', 'footer'] as $loc) {
            $parent = DB::table('menu_items')->where('location', $loc)->whereNull('parent_id')->where('title', 'Tools')->first();
            $parentId = $parent?->id ?? DB::table('menu_items')->insertGetId([
                'location' => $loc, 'parent_id' => null, 'title' => 'Tools', 'url' => null,
                'sort_order' => (int) DB::table('menu_items')->where('location', $loc)->whereNull('parent_id')->max('sort_order') + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $sort = (int) DB::table('menu_items')->where('parent_id', $parentId)->max('sort_order');
            foreach (self::TOOLS as [$title, $url]) {
                if (DB::table('menu_items')->where('location', $loc)->where('url', $url)->exists()) continue;   // already linked by the site owner
                DB::table('menu_items')->insert(['location' => $loc, 'parent_id' => $parentId, 'title' => $title, 'url' => $url, 'sort_order' => ++$sort, 'created_at' => $now, 'updated_at' => $now]);
            }
            Cache::forget("menu.$loc");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) return;
        DB::table('menu_items')->whereIn('url', array_column(self::TOOLS, 1))->delete();
        Cache::forget('menu.header'); Cache::forget('menu.footer');
    }
};
