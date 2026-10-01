<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/** The ElevenLabs key is now stored as plain text. Convert an old encrypted value when it can still be read. */
return new class extends Migration {
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'elevenlabs.api_key')->first();
        if ($row && $row->is_secret && $row->value !== null && $row->value !== '') {
            try {
                DB::table('settings')->where('key', 'elevenlabs.api_key')->update(['value' => trim(Crypt::decryptString($row->value)), 'is_secret' => false]);
            } catch (\Throwable) {
                // Undecryptable (APP_KEY changed): leave it; the admin pastes the key again and it is saved as plain text.
            }
        }
        Cache::forget('settings.all');
    }

    public function down(): void
    {
    }
};
