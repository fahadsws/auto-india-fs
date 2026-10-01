<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editor-curated "suggested comparisons". Any two catalog cars can be compared on the public site;
 * this table only stores the pairs an editor wants to promote (with optional verdict / SEO text).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_comparisons', function (Blueprint $t) {
            $t->id();
            $t->foreignId('car_a_id')->constrained('car_models')->cascadeOnDelete();
            $t->foreignId('car_b_id')->constrained('car_models')->cascadeOnDelete();
            $t->foreignId('winner_id')->nullable()->constrained('car_models')->nullOnDelete();
            $t->string('title', 160)->nullable();
            $t->text('intro')->nullable();
            $t->text('verdict')->nullable();
            $t->string('meta_title', 70)->nullable();
            $t->string('meta_description', 320)->nullable();
            $t->boolean('is_active')->default(true);
            $t->boolean('is_featured')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();

            $t->unique(['car_a_id', 'car_b_id']);
            $t->index(['is_active', 'is_featured', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_comparisons');
    }
};
