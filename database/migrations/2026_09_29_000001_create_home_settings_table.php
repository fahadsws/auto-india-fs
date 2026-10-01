<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('home_settings', function (Blueprint $table) {
            $table->id();
            $table->json('hero_banners')->nullable();
            $table->json('trending_car_ids')->nullable();
            $table->json('collections')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('home_settings'); }
};
