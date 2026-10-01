<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $t) {
            $t->string('meta_keywords', 300)->nullable()->after('meta_description');
            $t->string('canonical_url', 500)->nullable()->after('meta_keywords');
            $t->string('robots', 40)->default('index,follow')->after('canonical_url');
            $t->string('og_title', 160)->nullable()->after('robots');
            $t->string('og_description', 320)->nullable()->after('og_title');
            $t->string('og_image', 500)->nullable()->after('og_description');
            $t->string('schema_type', 30)->default('NewsArticle')->after('og_image');
            $t->longText('schema_json')->nullable()->after('schema_type');
        });
    }

    public function down(): void
    {
        Schema::table('articles', fn (Blueprint $t) => $t->dropColumn(['meta_keywords', 'canonical_url', 'robots', 'og_title', 'og_description', 'og_image', 'schema_type', 'schema_json']));
    }
};
