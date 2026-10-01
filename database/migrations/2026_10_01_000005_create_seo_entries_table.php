<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('seo_entries', function (Blueprint $t) {
            $t->id();
            $t->string('route_key', 40)->unique();
            $t->string('meta_title', 120)->nullable();
            $t->string('meta_description', 320)->nullable();
            $t->string('meta_keywords', 300)->nullable();
            $t->string('canonical_url', 500)->nullable();
            $t->string('robots', 40)->default('index,follow');
            $t->string('og_title', 160)->nullable();
            $t->string('og_description', 320)->nullable();
            $t->string('og_image', 500)->nullable();
            $t->string('schema_type', 30)->default('None');
            $t->longText('schema_json')->nullable();
            $t->json('faq')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_entries');
    }
};
