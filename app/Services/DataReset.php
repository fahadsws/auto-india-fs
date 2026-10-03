<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes the site's DATA (articles, cars, listings, leads, chats, logs, pages, menus ...) while keeping the things needed to log in and
 * run the site: users, settings, roles & permissions, sessions and the migrations table. Every other table in the database is
 * truncated (auto-increment ids restart), with foreign keys switched off for the duration, so new tables added later are covered
 * automatically.
 */
class DataReset
{
    /** Never truncated. */
    public const KEEP = [
        'users', 'settings', 'migrations',
        'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions',   // access control (users keep their roles)
        'sessions', 'password_reset_tokens',                                                          // the admin running the reset stays logged in
    ];

    /** Optional extra tables the admin may choose to keep as well (site structure rather than content). */
    public const OPTIONAL_KEEP = [
        'menu_items' => 'Header & footer menus',
        'home_settings' => 'Home page settings (banners, ads, trending)',
        'seo_entries' => 'SEO entries',
        'pages' => 'Custom pages',
    ];

    /** @return array<int,string> every table that would be cleared */
    public static function tables(array $alsoKeep = []): array
    {
        $keep = array_merge(self::KEEP, array_intersect($alsoKeep, array_keys(self::OPTIONAL_KEEP)));
        $tables = array_map(fn ($t) => is_array($t) ? ($t['name'] ?? '') : (string) $t, Schema::getTableListing());
        // Schema::getTableListing() can return "schema.table" names on some drivers.
        $tables = array_map(fn ($t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t, $tables);

        return array_values(array_diff(array_unique(array_filter($tables)), $keep));
    }

    /** @return array<string,int> table => rows that will be deleted */
    public static function counts(array $alsoKeep = []): array
    {
        $out = [];
        foreach (self::tables($alsoKeep) as $t) $out[$t] = (int) DB::table($t)->count();
        return $out;
    }

    /** @return array<string,int> table => rows removed */
    public static function run(array $alsoKeep = []): array
    {
        $removed = self::counts($alsoKeep);

        Schema::disableForeignKeyConstraints();
        try {
            foreach (array_keys($removed) as $t) DB::table($t)->truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // Cached menus, settings, search indexes and rate limits describe data that no longer exists.
        try { Cache::flush(); } catch (\Throwable $e) { /* a cache store that cannot be flushed is not a reason to fail */ }

        return $removed;
    }
}
