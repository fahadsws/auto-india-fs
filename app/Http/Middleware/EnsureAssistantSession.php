<?php

namespace App\Http\Middleware;

use App\Models\AssistantSession;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Only visitors who completed the lead + email-OTP step may use the AI (unless the gate is switched off in Settings). */
class EnsureAssistantSession
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Setting::bool('assistant.enabled', true), 404);

        $token = (string) ($request->header('X-Assistant-Token') ?: $request->session()->get('assistant.token', ''));
        $s = $token !== '' ? AssistantSession::where('token', $token)->with('lead')->first() : null;

        if (Setting::bool('assistant.require_lead', true)) {
            if (! $s || ! $s->lead_id || (Setting::bool('assistant.otp_required', true) && ! $s->lead?->email_verified_at)) {
                return response()->json(['gate' => true, 'message' => 'Please verify your details to start chatting.'], 401);
            }
        } elseif (! $s) {
            $s = AssistantSession::create(['token' => Str::random(40), 'ip' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 250, '')]);
            $request->session()->put('assistant.token', $s->token);
        }

        $request->attributes->set('assistant_session', $s);
        return $next($request);
    }
}
