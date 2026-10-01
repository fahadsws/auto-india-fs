<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_logs', fn (Blueprint $t) => $t->json('sources')->nullable());
        // knowledge_chunks.type must fit 'webpage' (custom pages)
        if (DB::getDriverName() === 'mysql') DB::statement('ALTER TABLE knowledge_chunks MODIFY type VARCHAR(20)');
    }

    public function down(): void
    {
        Schema::table('chat_logs', fn (Blueprint $t) => $t->dropColumn('sources'));
    }
};
