<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AssistantFeedback;
use App\Models\AssistantSession;
use App\Models\Lead;
use App\Models\Setting;
use App\Http\Middleware\EnsureAssistantSession;
use App\Services\AssistantGuard;
use App\Services\Recaptcha;
use App\Services\LeadNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Lead capture (protected by reCAPTCHA, no email OTP) that unlocks the AI chat after the free messages. */
class AssistantLeadController extends Controller
{
    /** Who is this visitor? Lets returning visitors skip the form. */
    public function me(Request $r)
    {
        $s = $this->resolve($r);
        $verified = EnsureAssistantSession::verified($s);
        $feature = $r->query('feature') === 'mechanic' ? 'mechanic' : 'assistant';
        $free = EnsureAssistantSession::freeLimit($feature);
        $gateOn = Setting::bool('assistant.require_lead', true);
        $used = (int) ($s?->messages_total ?? 0);
        // Not verified yet: the visitor may still chat until the free messages are used up; only then is the details form shown.
        $gate = $gateOn && ! $verified && $used >= $free;
        return response()->json([
            'gate' => $gate,
            'verified' => $verified,
            'name' => $verified ? $s?->lead?->name : null,
            'free_left' => $gateOn && ! $verified ? max(0, $free - $used) : null,
            'left' => $s ? max(0, AssistantGuard::limit('msgs_day') - ($s->usage_date?->isToday() ? $s->messages_today : 0)) : AssistantGuard::limit('msgs_day'),
            'token' => $verified ? $s?->token : null,
        ]);
    }

    public function submit(Request $r)
    {
        abort_unless(Setting::bool('assistant.enabled', true), 404);
        if ($r->filled('website')) return response()->json(['message' => 'Something went wrong. Please try again.'], 422); // honeypot

        $d = $r->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL\s\.\'\-]+$/u'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['required', 'email:rfc', 'max:120'],
            'city' => 'nullable|string|max:80',
            'interest' => 'nullable|string|max:120',
            'recaptcha' => 'nullable|string|max:4000',
        ], ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.', 'name.regex' => 'Please enter your real name.']);

        $captcha = Recaptcha::verify($d['recaptcha'] ?? null, 'assistant_lead', (string) $r->ip());
        if (! $captcha['ok']) return response()->json(['message' => $captcha['message']], 422);

        // Cap how many distinct leads one IP can create per day (stops form spam).
        $ipKey = 'asst:leads:'.$r->ip().':'.today()->toDateString();
        Cache::add($ipKey, 0, now()->addDay());
        if (Cache::increment($ipKey) > 8) {
            return response()->json(['message' => 'Too many attempts from your network today. Please try again tomorrow.'], 429);
        }

        $email = Str::lower($d['email']);
        $lead = Lead::where('type', 'chatbot')->where('email', $email)->first() ?? new Lead(['type' => 'chatbot', 'status' => 'new']);
        $isNew = ! $lead->exists;
        $lead->fill([
            'name' => $d['name'], 'phone' => $d['phone'], 'email' => $email, 'city' => $d['city'] ?? $lead->city,
            'source' => 'chatbot', 'ip' => $r->ip(),
            'message' => 'Started a chat with the AI assistant'.(! empty($d['interest']) ? '. Interested in: '.$d['interest'] : '').'.',
        ])->save();
        $r->session()->put('assistant.lead_id', $lead->id);
        if ($isNew) LeadNotifier::notify($lead);

        return $this->grant($r, $lead);
    }

    public function feedback(Request $r)
    {
        $d = $r->validate(['rating' => 'nullable|integer|between:1,5', 'reason' => 'nullable|in:irrelevant,partly_correct,other', 'comment' => 'nullable|string|max:500']);
        if (empty($d['rating']) && empty($d['reason']) && empty($d['comment'])) return response()->json(['ok' => true]);
        $s = $this->resolve($r);
        AssistantFeedback::create($d + ['assistant_session_id' => $s?->id]);
        return response()->json(['ok' => true]);
    }

    private function grant(Request $r, Lead $lead)
    {
        // Without an email code, an existing session is never handed to someone who only typed that email: this browser's own
        // anonymous session (kept with its conversation and limits) becomes the lead's session, or a fresh one is created.
        $s = $this->resolve($r);
        if (! $s || ($s->lead_id && $s->lead_id !== $lead->id)) $s = new AssistantSession(['token' => Str::random(40)]);
        $s->lead_id = $lead->id;
        $s->fill(['ip' => $r->ip(), 'user_agent' => Str::limit((string) $r->userAgent(), 250, '')])->save();
        $r->session()->put('assistant.token', $s->token);

        return response()->json(['ok' => true, 'verified' => true, 'token' => $s->token, 'name' => $lead->name]);
    }

    private function resolve(Request $r): ?AssistantSession
    {
        $t = (string) ($r->header('X-Assistant-Token') ?: $r->session()->get('assistant.token', ''));
        return $t !== '' ? AssistantSession::where('token', $t)->with('lead')->first() : null;
    }

}
