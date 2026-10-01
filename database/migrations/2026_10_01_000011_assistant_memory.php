<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('assistant_sessions', fn (Blueprint $t) => $t->json('memory')->nullable());
    }

    public function down(): void
    {
        Schema::table('assistant_sessions', fn (Blueprint $t) => $t->dropColumn('memory'));
    }
};
