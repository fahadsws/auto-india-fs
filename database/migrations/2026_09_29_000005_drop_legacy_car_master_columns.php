<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('car_models',function(Blueprint $t){$t->dropColumn(['brand','body_type','fuel_types']);}); } public function down(): void { Schema::table('car_models',function(Blueprint $t){$t->string('brand')->nullable();$t->string('body_type')->nullable();$t->json('fuel_types')->nullable();}); } };
