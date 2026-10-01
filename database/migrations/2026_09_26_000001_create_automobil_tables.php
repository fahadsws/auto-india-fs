<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('bio', 500)->nullable()->after('email');
            $t->boolean('is_active')->default(true)->after('bio');
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->longText('value')->nullable();
            $t->boolean('is_secret')->default(false);
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });

        Schema::create('news_sources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('type', 10)->default('rss'); // rss|page (page = listing page, links harvested by pattern)
            $t->string('feed_url', 500);
            $t->string('link_pattern')->nullable();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_fetched_at')->nullable();
            $t->string('last_status')->nullable();
            $t->timestamps();
        });

        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('news_source_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('excerpt', 600)->nullable();
            $t->longText('body')->nullable();
            $t->string('image_path')->nullable();
            $t->string('meta_title')->nullable();
            $t->string('meta_description', 320)->nullable();
            $t->string('status', 20)->default('draft')->index(); // draft|scheduled|published
            $t->timestamp('published_at')->nullable()->index();
            $t->string('source_url', 700)->nullable();
            $t->string('source_title', 300)->nullable();
            $t->string('source_hash', 64)->nullable()->unique();
            $t->boolean('is_ai_generated')->default(false);
            $t->unsignedBigInteger('views')->default(0);
            $t->timestamps();
        });

        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('brand')->nullable()->index();
            $t->string('model')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->unsignedBigInteger('price')->nullable();
            $t->unsignedInteger('km_driven')->nullable();
            $t->string('fuel', 30)->nullable();
            $t->string('transmission', 30)->nullable();
            $t->string('owner', 30)->nullable();
            $t->string('city')->nullable();
            $t->text('description')->nullable();
            $t->string('image_path', 700)->nullable();
            $t->string('source_name')->nullable();
            $t->string('source_url', 700)->nullable();
            $t->string('external_id')->nullable()->unique();
            $t->string('status', 20)->default('active')->index(); // active|sold|hidden
            $t->timestamps();
        });

        Schema::create('listing_sources', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('url', 700);
            $t->string('format', 10)->default('json'); // json|csv
            $t->string('items_path')->nullable();      // dot path to the array inside JSON
            $t->json('mapping')->nullable();           // our field => their field
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_run_at')->nullable();
            $t->string('last_status')->nullable();
            $t->timestamps();
        });

        Schema::create('leads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type', 20)->default('enquiry'); // enquiry|contact|sell
            $t->string('name');
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->text('message')->nullable();
            $t->string('status', 20)->default('new')->index(); // new|contacted|won|lost
            $t->text('notes')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });

        Schema::create('videos', function (Blueprint $t) {
            $t->id();
            $t->string('youtube_id', 20)->unique();
            $t->string('title');
            $t->string('channel')->nullable();
            $t->string('thumbnail', 500)->nullable();
            $t->text('description')->nullable();
            $t->string('keyword')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('article_video', function (Blueprint $t) {
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->foreignId('video_id')->constrained()->cascadeOnDelete();
            $t->primary(['article_id', 'video_id']);
        });

        Schema::create('knowledge_chunks', function (Blueprint $t) {
            $t->id();
            $t->string('type', 20);
            $t->unsignedBigInteger('ref_id');
            $t->string('title');
            $t->string('url', 700)->nullable();
            $t->string('image', 700)->nullable();
            $t->text('content');
            $t->timestamps();
            $t->unique(['type', 'ref_id']);
        });
        DB::statement('ALTER TABLE knowledge_chunks ADD FULLTEXT ft_kb (title, content)');

        Schema::create('chat_logs', function (Blueprint $t) {
            $t->id();
            $t->string('session_id', 60)->index();
            $t->text('question');
            $t->text('answer');
            $t->string('source', 10); // kb|web|none
            $t->boolean('voice')->default(false);
            $t->timestamps();
        });

        Schema::create('automation_logs', function (Blueprint $t) {
            $t->id();
            $t->string('task', 40)->index();
            $t->string('status', 10); // ok|error|skipped
            $t->text('message')->nullable();
            $t->unsignedInteger('duration_ms')->default(0);
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['automation_logs', 'chat_logs', 'knowledge_chunks', 'article_video', 'videos', 'leads', 'listing_sources', 'listings', 'articles', 'news_sources', 'categories', 'settings'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['bio', 'is_active']));
    }
};
