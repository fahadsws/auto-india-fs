<?php

namespace App\Http\Middleware;

use App\Models\AssistantSession;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Visitors may use the AI straight away. After a few free messages (Settings -> Assistant) they must share their details once
 * (protected by reCAPTCHA, no email code); the gate then opens for good. Usage is counted per visitor in assistant_sessions.messages_total.
 *
 * Usage: `assistant.session` (assistant, roast) or `assistant.session:mechanic` (mechanic gets a slightly longer free run,
 * because a diagnosis needs several questions).
 */
class EnsureAssistantSession
{
    /** Anonymous visitor sessions one IP may open per day (stops cookie-clearing to farm free messages). */
    private const MAX_ANON_PER_IP = 12;

    public function handle(Request $request, Closure $next, string $feature = 'assistant')
    {
        abort_unless(Setting::bool('assistant.enabled', true), 404);

        $token = (string) ($request->header('X-Assistant-Token') ?: $request->session()->get('assistant.token', ''));
        $s = $token !== '' ? AssistantSession::where('token', $token)->with('lead')->first() : null;
        $verified = self::verified($s);

        if (! $verified && Setting::bool('assistant.require_lead', true)) {
            $free = self::freeLimit($feature);
            if ($s && $s->messages_total >= $free) return self::gate($free);

            if (! $s) {
                if ($free <= 0) return self::gate(0);
                $ipKey = 'asst:anon:'.$request->ip().':'.today()->toDateString();
                Cache::add($ipKey, 0, now()->addDay());
                if (Cache::increment($ipKey) > self::MAX_ANON_PER_IP) return self::gate($free);
                $s = self::anonymous($request);
            }
        } elseif (! $s) {
            $s = self::anonymous($request);
        }

        $request->attributes->set('assistant_session', $s);
        $request->attributes->set('assistant_verified', $verified);
        return $next($request);
    }

    public static function verified(?AssistantSession $s): bool
    {
        return (bool) ($s && $s->lead_id);
    }

    /** Free messages before the details form. 0 = ask straight away. */
    public static function freeLimit(string $feature = 'assistant'): int
    {
        return $feature === 'mechanic'
            ? max(0, (int) Setting::get('assistant.free_messages_mechanic', 4))
            : max(0, (int) Setting::get('assistant.free_messages', 3));
    }

    private static function anonymous(Request $request): AssistantSession
    {
        $s = AssistantSession::create(['token' => Str::random(40), 'ip' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 250, '')]);
        $request->session()->put('assistant.token', $s->token);
        return $s;
    }

    private static function gate(int $free)
    {
        return response()->json([
            'gate' => true,
            'free' => $free,
            'message' => $free > 0
                ? "You've used your free messages. Please share your details once to continue - it takes under a minute."
                : 'Please share your details to start chatting.',
        ], 401);
    }
}
