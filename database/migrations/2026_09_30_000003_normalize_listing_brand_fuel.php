<?php

use App\Services\CarMasters;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Listings keep brand and fuel as ids of car_brands / car_fuels instead of free text. Missing masters are created. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $t) {
            $t->foreignId('brand_id')->nullable()->after('slug')->constrained('car_brands')->nullOnDelete();
            $t->foreignId('fuel_id')->nullable()->after('km_driven')->constrained('car_fuels')->nullOnDelete();
        });

        foreach (DB::table('listings')->whereNotNull('brand')->distinct()->pluck('brand') as $brand) {
            if ($id = CarMasters::brandId($brand)) DB::table('listings')->where('brand', $brand)->update(['brand_id' => $id]);
        }
        foreach (DB::table('listings')->whereNotNull('fuel')->distinct()->pluck('fuel') as $fuel) {
            if ($id = CarMasters::fuelId($fuel)) DB::table('listings')->where('fuel', $fuel)->update(['fuel_id' => $id]);
        }

        Schema::table('listings', fn (Blueprint $t) => $t->dropColumn(['brand', 'fuel']));
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $t) {
            $t->string('brand', 80)->nullable()->after('slug');
            $t->string('fuel', 30)->nullable()->after('km_driven');
        });
        DB::statement('UPDATE listings SET brand = (SELECT name FROM car_brands WHERE car_brands.id = listings.brand_id), fuel = (SELECT name FROM car_fuels WHERE car_fuels.id = listings.fuel_id)');
        Schema::table('listings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('brand_id');
            $t->dropConstrainedForeignId('fuel_id');
        });
    }
};
