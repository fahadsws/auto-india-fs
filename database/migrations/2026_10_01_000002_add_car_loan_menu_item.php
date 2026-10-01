<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $order = (int) DB::table('menu_items')->where('location', 'header')->whereNull('parent_id')->max('sort_order') + 1;
        if (! DB::table('menu_items')->where('url', '/car-emi-calculator')->exists()) {
            DB::table('menu_items')->insert(['location' => 'header', 'parent_id' => null, 'title' => 'Car Loan', 'url' => '/car-emi-calculator', 'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now]);
        }
        $col = DB::table('menu_items')->where('location', 'footer')->where('title', 'Explore')->whereNull('parent_id')->value('id');
        if ($col) {
            DB::table('menu_items')->insert(['location' => 'footer', 'parent_id' => $col, 'title' => 'Car EMI calculator', 'url' => '/car-emi-calculator', 'sort_order' => 99, 'created_at' => $now, 'updated_at' => $now]);
        }
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('url', '/car-emi-calculator')->delete();
        \Illuminate\Support\Facades\Cache::flush();
    }
};
