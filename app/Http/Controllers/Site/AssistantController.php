<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Assistant;
use App\Services\AssistantGuard;
use App\Services\ElevenLabs;
use Illuminate\Http\Request;

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

    /**
     * ElevenLabs text-to-speech proxy (keeps the API key on the server) in the voice saved in Settings.
     * 204 only when no key is saved (browser voice). A failure is returned as JSON - never a silent voice swap.
     */
    public function tts(Request $r)
    {
        $data = $r->validate(['text' => 'required|string|max:600']);
        if (! ElevenLabs::configured()) return response()->noContent();

        $text = ElevenLabs::clean($data['text']);
        if ($text === '') return response()->noContent();

        // Cached clips are free; only new synthesis counts against the visitor's daily character quota.
        $session = $r->attributes->get('assistant_session');
        $fresh = ! ElevenLabs::isCached($text);
        if ($fresh && ! AssistantGuard::admitTts($session, $text)) {
            return response()->json(['error' => 'voice_limit', 'message' => "You've reached today's voice limit - I'll keep replying in text."], 429);
        }

        $res = ElevenLabs::speak($text);
        if (! $res['ok']) {
            if ($fresh) $session->decrement('tts_chars_today', mb_strlen($text));
            return response()->json(['error' => $res['code'] ?? 'tts_failed', 'message' => 'Voice is unavailable right now.'], 502);
        }

        return response($res['body'], 200, ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'private, max-age=86400']);
    }
}
