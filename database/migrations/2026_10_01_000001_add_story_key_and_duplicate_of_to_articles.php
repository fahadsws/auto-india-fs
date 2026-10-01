<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            $t->string('story_key', 120)->nullable()->index();           // "bmw-3-series:launch" - the same story from any outlet gets the same key
            $t->unsignedBigInteger('duplicate_of')->nullable()->index(); // set when merged into another article (301 to it, out of lists + sitemap)
        });
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $t) => $t->dropColumn(['story_key', 'duplicate_of']));
    }
};
