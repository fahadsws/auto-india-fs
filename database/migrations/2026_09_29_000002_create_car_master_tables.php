<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { foreach (['car_brands'=>'brand','car_body_types'=>'body type','car_fuels'=>'fuel'] as $table=>$label) Schema::create($table, function(Blueprint $t) use($label){$t->id();$t->string('name')->unique();$t->string('slug')->unique();$t->boolean('is_active')->default(true);$t->unsignedInteger('sort_order')->default(0);$t->timestamps();}); } public function down(): void { Schema::dropIfExists('car_fuels');Schema::dropIfExists('car_body_types');Schema::dropIfExists('car_brands'); } };
