<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** The catalog now holds cars, bikes and trucks: car_* tables become vehicle_*, and models / body types get a vehicle_type. */
return new class extends Migration {
    public function up(): void
    {
        Schema::rename('car_brands', 'vehicle_brands');
        Schema::rename('car_body_types', 'vehicle_body_types');
        Schema::rename('car_fuels', 'vehicle_fuels');
        Schema::rename('car_models', 'vehicle_models');
        Schema::rename('car_model_fuel', 'vehicle_model_fuel');
        Schema::rename('article_car_model', 'article_vehicle_model');

        Schema::table('vehicle_model_fuel', function (Blueprint $t) {
            $t->renameColumn('car_model_id', 'vehicle_model_id');
            $t->renameColumn('car_fuel_id', 'vehicle_fuel_id');
        });
        Schema::table('article_vehicle_model', fn (Blueprint $t) => $t->renameColumn('car_model_id', 'vehicle_model_id'));
        Schema::table('listings', fn (Blueprint $t) => $t->renameColumn('car_model_id', 'vehicle_model_id'));

        foreach (['vehicle_models', 'vehicle_body_types'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('vehicle_type', 10)->default('car')->index());
        }

        $bodies = [
            'bike' => ['Commuter Bike', 'Sports Bike', 'Cruiser Bike', 'Adventure Bike', 'Scooter'],
            'truck' => ['Mini Truck (SCV)', 'Pickup', 'LCV', 'MCV', 'HCV', 'Tipper'],
        ];
        foreach ($bodies as $type => $names) {
            foreach ($names as $i => $name) {
                DB::table('vehicle_body_types')->insertOrIgnore([
                    'name' => $name, 'slug' => Str::slug($name), 'vehicle_type' => $type, 'is_active' => true, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('vehicle_models')->where('vehicle_type', '!=', 'car')->delete();
        DB::table('vehicle_body_types')->where('vehicle_type', '!=', 'car')->delete();
        foreach (['vehicle_models', 'vehicle_body_types'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('vehicle_type'));
        }
        Schema::table('listings', fn (Blueprint $t) => $t->renameColumn('vehicle_model_id', 'car_model_id'));
        Schema::table('article_vehicle_model', fn (Blueprint $t) => $t->renameColumn('vehicle_model_id', 'car_model_id'));
        Schema::table('vehicle_model_fuel', function (Blueprint $t) {
            $t->renameColumn('vehicle_model_id', 'car_model_id');
            $t->renameColumn('vehicle_fuel_id', 'car_fuel_id');
        });
        Schema::rename('article_vehicle_model', 'article_car_model');
        Schema::rename('vehicle_model_fuel', 'car_model_fuel');
        Schema::rename('vehicle_models', 'car_models');
        Schema::rename('vehicle_fuels', 'car_fuels');
        Schema::rename('vehicle_body_types', 'car_body_types');
        Schema::rename('vehicle_brands', 'car_brands');
    }
};
