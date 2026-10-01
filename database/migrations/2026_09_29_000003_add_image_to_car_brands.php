<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('car_brands', function(Blueprint $t){$t->string('image')->nullable()->after('slug');}); } public function down(): void { Schema::table('car_brands', fn(Blueprint $t)=>$t->dropColumn('image')); } };
