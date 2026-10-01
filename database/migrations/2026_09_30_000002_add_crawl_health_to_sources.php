<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['news_sources', 'listing_sources'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedSmallInteger('fail_count')->default(0);       // consecutive failed runs (drives the cool-down)
                $t->timestamp('last_success_at')->nullable();
                $t->unsignedSmallInteger('last_found')->default(0);       // items discovered on the last successful run
            });
        }
    }

    public function down(): void
    {
        foreach (['news_sources', 'listing_sources'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['fail_count', 'last_success_at', 'last_found']));
        }
    }
};
