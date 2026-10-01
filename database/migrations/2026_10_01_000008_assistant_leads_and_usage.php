<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $t) {
            $t->string('city', 80)->nullable();
            $t->string('source', 30)->nullable();
            $t->timestamp('email_verified_at')->nullable();
        });

        Schema::create('assistant_sessions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $t->string('token', 64)->unique();
            $t->string('ip', 45)->nullable()->index();
            $t->string('user_agent', 255)->nullable();
            $t->date('usage_date')->nullable();
            $t->unsignedInteger('messages_today')->default(0);
            $t->unsignedInteger('tokens_today')->default(0);
            $t->unsignedInteger('tts_chars_today')->default(0);
            $t->unsignedBigInteger('messages_total')->default(0);
            $t->unsignedBigInteger('tokens_total')->default(0);
            $t->unsignedInteger('strikes')->default(0);
            $t->timestamp('blocked_until')->nullable();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
        });

        Schema::create('assistant_otps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $t->string('email')->index();
            $t->string('code_hash');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->timestamp('expires_at');
            $t->string('ip', 45)->nullable();
            $t->timestamps();
        });

        Schema::create('assistant_feedback', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assistant_session_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedTinyInteger('rating')->nullable();
            $t->string('reason', 40)->nullable();
            $t->text('comment')->nullable();
            $t->timestamps();
        });

        Schema::table('chat_logs', function (Blueprint $t) {
            $t->unsignedBigInteger('assistant_session_id')->nullable()->index();
            $t->unsignedInteger('tokens_in')->default(0);
            $t->unsignedInteger('tokens_out')->default(0);
            $t->boolean('cached')->default(false);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $p = Permission::findOrCreate('assistant.manage', 'web');
        foreach (['Admin'] as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($p);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('chat_logs', fn (Blueprint $t) => $t->dropColumn(['assistant_session_id', 'tokens_in', 'tokens_out', 'cached']));
        Schema::dropIfExists('assistant_feedback');
        Schema::dropIfExists('assistant_otps');
        Schema::dropIfExists('assistant_sessions');
        Schema::table('leads', fn (Blueprint $t) => $t->dropColumn(['city', 'source', 'email_verified_at']));
        Permission::where('name', 'assistant.manage')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
