<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('title', 200);
            $t->string('slug', 160)->unique();
            $t->string('template', 20)->default('default');
            $t->string('status', 20)->default('draft')->index();
            $t->text('excerpt')->nullable();
            $t->longText('body')->nullable();
            $t->string('featured_image', 500)->nullable();
            $t->string('meta_title', 120)->nullable();
            $t->string('meta_description', 320)->nullable();
            $t->string('meta_keywords', 300)->nullable();
            $t->string('canonical_url', 500)->nullable();
            $t->string('robots', 40)->default('index,follow');
            $t->string('og_title', 160)->nullable();
            $t->string('og_description', 320)->nullable();
            $t->string('og_image', 500)->nullable();
            $t->string('schema_type', 30)->default('WebPage');
            $t->longText('schema_json')->nullable();
            $t->json('faq')->nullable();
            $t->boolean('show_lead')->default(true);
            $t->boolean('show_ads')->default(true);
            $t->boolean('show_news')->default(true);
            $t->timestamp('published_at')->nullable();
            $t->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
