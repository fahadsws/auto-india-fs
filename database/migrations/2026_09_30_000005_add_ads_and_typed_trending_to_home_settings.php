<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Home settings gain ad banners and a trending list per vehicle type (the old single car list moves under "car"). */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('home_settings', function (Blueprint $t) {
            $t->json('ads')->nullable();
            $t->json('trending_ids')->nullable();
        });

        foreach (DB::table('home_settings')->get() as $row) {
            $cars = json_decode($row->trending_car_ids ?: '[]', true) ?: [];
            DB::table('home_settings')->where('id', $row->id)->update(['trending_ids' => json_encode(['car' => $cars, 'bike' => [], 'truck' => []])]);
        }

        Schema::table('home_settings', fn (Blueprint $t) => $t->dropColumn('trending_car_ids'));
    }

    public function down(): void
    {
        Schema::table('home_settings', fn (Blueprint $t) => $t->json('trending_car_ids')->nullable());

        foreach (DB::table('home_settings')->get() as $row) {
            $ids = (json_decode($row->trending_ids ?: '[]', true) ?: [])['car'] ?? [];
            DB::table('home_settings')->where('id', $row->id)->update(['trending_car_ids' => json_encode($ids)]);
        }

        Schema::table('home_settings', fn (Blueprint $t) => $t->dropColumn(['ads', 'trending_ids']));
    }
};
