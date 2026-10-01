<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Assistant;
use App\Services\AssistantGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class AssistantController extends Controller
{
    public function page() { return view('site.assistant'); }

    public function chat(Request $r, Assistant $assistant)
    {
        $d = $r->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array|max:6',
            'history.*.role' => 'in:user,assistant',
            'history.*.content' => 'string|max:1200',
            'voice' => 'nullable|boolean',
        ]);
        $s = $r->attributes->get('assistant_session');

        $lock = AssistantGuard::lock($s); // one in-flight request per visitor
        if (! $lock) return response()->json(['message' => 'Still working on your last question…', 'reason' => 'busy'], 429);

        try {
            $s->refresh();
            $verdict = AssistantGuard::admit($s, $d['message'], (string) $r->ip());
            if (! $verdict['allow']) {
                return response()->json(['message' => $verdict['message'], 'reason' => $verdict['reason'], 'retry_after' => $verdict['retry_after']], 429);
            }
            return response()->json($assistant->reply($d['message'], $d['history'] ?? [], $s, (string) $r->ip(), (bool) ($d['voice'] ?? false), $verdict['degraded']));
        } finally {
            $lock->release();
        }
    }

    /** ElevenLabs text-to-speech proxy (keeps the API key on the server). 204 tells the browser to use its built-in voice. */
    public function tts(Request $r)
    {
        $data = $r->validate(['text' => 'required|string|max:600']);
        $key = Setting::get('elevenlabs.api_key');
        if (! $key) return response()->noContent();

        $text = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/[*_`#]/', '', $data['text']))));
        $voice = Setting::get('elevenlabs.voice_id', '21m00Tcm4TlvDq8ikWAM');
        $model = Setting::get('elevenlabs.model', 'eleven_flash_v2_5');
        $file = 'tts/'.md5($voice.$model.$text).'.mp3';
        // Cached clips are free; only new synthesis counts against the visitor's daily character quota.
        if (! Storage::disk('local')->exists($file) && ! AssistantGuard::admitTts($r->attributes->get('assistant_session'), $text)) {
            return response()->noContent();
        }

        if (! Storage::disk('local')->exists($file)) {
            $res = Http::withHeaders(['xi-api-key' => $key, 'Accept' => 'audio/mpeg'])->timeout(30)
                ->post("https://api.elevenlabs.io/v1/text-to-speech/$voice?output_format=mp3_44100_64", ['text' => $text, 'model_id' => $model]);
            if (! $res->successful()) return response()->noContent();
            Storage::disk('local')->put($file, $res->body());
        }

        return response(Storage::disk('local')->get($file), 200, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=86400']);
    }
}
