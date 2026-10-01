<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $t) {
            $t->string('scope', 10)->default('india')->after('type'); // india | global (global is keyword-filtered for India relevance)
        });

        Schema::table('articles', function (Blueprint $t) {
            $t->json('tags')->nullable();
            $t->json('tldr')->nullable();
            $t->json('faq')->nullable();
            $t->json('source_links')->nullable(); // every outlet used as inspiration, for attribution
            $t->unsignedTinyInteger('similarity')->nullable(); // % overlap with sources, from the quality gate
        });

        Schema::create('car_models', function (Blueprint $t) {
            $t->id();
            $t->string('brand')->index();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('body_type', 40)->nullable();
            $t->string('status', 20)->default('launched'); // upcoming | launched | facelift | discontinued
            $t->json('fuel_types')->nullable();
            $t->unsignedBigInteger('price_min')->nullable();
            $t->unsignedBigInteger('price_max')->nullable();
            $t->date('launch_date')->nullable();
            $t->string('latest_event', 30)->nullable();     // launch | facelift | price_update | spec_update ...
            $t->timestamp('latest_event_at')->nullable();
            $t->string('tagline', 300)->nullable();
            $t->longText('overview')->nullable();
            $t->json('highlights')->nullable();
            $t->json('specs')->nullable();                  // label => value
            $t->json('faq')->nullable();
            $t->string('hero_image')->nullable();
            $t->json('gallery')->nullable();                // current-generation images
            $t->json('archive_gallery')->nullable();        // pre-facelift images kept for reference
            $t->json('image_hashes')->nullable();           // md5s, to avoid storing the same picture twice
            $t->string('meta_title')->nullable();
            $t->string('meta_description', 320)->nullable();
            $t->json('locked')->nullable();                 // fields an editor has pinned; automation never overwrites these
            $t->boolean('needs_refresh')->default(false);
            $t->timestamp('refreshed_at')->nullable();
            $t->boolean('is_published')->default(true);
            $t->timestamps();
        });

        Schema::create('article_car_model', function (Blueprint $t) {
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $t->string('event', 30)->nullable();
            $t->primary(['article_id', 'car_model_id']);
        });

        Schema::table('listing_sources', function (Blueprint $t) {
            $t->string('mode', 10)->default('feed')->after('format');   // feed | scrape
            $t->text('list_urls')->nullable();                          // scrape: one listing-page URL per line
            $t->string('detail_pattern')->nullable();                   // scrape: link must contain this
            $t->unsignedSmallInteger('max_per_run')->default(20);
            $t->unsignedSmallInteger('delay_ms')->default(1500);
            $t->boolean('respect_robots')->default(true);
        });

        Schema::table('listings', function (Blueprint $t) {
            $t->foreignId('listing_source_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('car_model_id')->nullable()->constrained()->nullOnDelete();
            $t->json('images')->nullable();
            $t->timestamp('last_seen_at')->nullable()->index();
            $t->timestamp('sold_at')->nullable();
            $t->string('status_reason')->nullable();
        });

        DB::statement('ALTER TABLE knowledge_chunks MODIFY type VARCHAR(20)');
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('listing_source_id');
            $t->dropConstrainedForeignId('car_model_id');
            $t->dropColumn(['images', 'last_seen_at', 'sold_at', 'status_reason']);
        });
        Schema::table('listing_sources', fn (Blueprint $t) => $t->dropColumn(['mode', 'list_urls', 'detail_pattern', 'max_per_run', 'delay_ms', 'respect_robots']));
        Schema::dropIfExists('article_car_model');
        Schema::dropIfExists('car_models');
        Schema::table('articles', fn (Blueprint $t) => $t->dropColumn(['tags', 'tldr', 'faq', 'source_links', 'similarity']));
        Schema::table('news_sources', fn (Blueprint $t) => $t->dropColumn('scope'));
    }
};
