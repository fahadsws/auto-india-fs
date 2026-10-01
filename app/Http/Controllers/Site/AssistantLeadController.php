<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AssistantFeedback;
use App\Models\AssistantOtp;
use App\Models\AssistantSession;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\AssistantGuard;
use App\Services\LeadNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Lead capture + free email-OTP verification that unlocks the AI chat. */
class AssistantLeadController extends Controller
{
    /** Who is this visitor? Lets returning visitors skip the form. */
    public function me(Request $r)
    {
        $s = $this->resolve($r);
        $gate = Setting::bool('assistant.require_lead', true);
        $ok = ! $gate || ($s?->lead_id && (! Setting::bool('assistant.otp_required', true) || $s->lead?->email_verified_at));
        return response()->json([
            'gate' => $gate && ! $ok,
            'verified' => (bool) $ok && (bool) $s,
            'name' => $s?->lead?->name,
            'left' => $s ? max(0, AssistantGuard::limit('msgs_day') - ($s->usage_date?->isToday() ? $s->messages_today : 0)) : AssistantGuard::limit('msgs_day'),
            'token' => $ok ? $s?->token : null,
        ]);
    }

    public function submit(Request $r)
    {
        abort_unless(Setting::bool('assistant.enabled', true), 404);
        if ($r->filled('website')) return response()->json(['ok' => true, 'otp' => true]); // honeypot: pretend success

        $d = $r->validate([
            'name' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[\pL\s\.\'\-]+$/u'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['required', 'email:rfc', 'max:120'],
            'city' => 'nullable|string|max:80',
            'interest' => 'nullable|string|max:120',
        ], ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.', 'name.regex' => 'Please enter your real name.']);

        // Cap how many distinct leads one IP can create per day (stops form spam / OTP mail bombing).
        $ipKey = 'asst:leads:'.$r->ip().':'.today()->toDateString();
        Cache::add($ipKey, 0, now()->addDay());
        if (Cache::increment($ipKey) > 8) {
            return response()->json(['message' => 'Too many attempts from your network today. Please try again tomorrow.'], 429);
        }

        $email = Str::lower($d['email']);
        $lead = Lead::where('type', 'chatbot')->where('email', $email)->first() ?? new Lead(['type' => 'chatbot', 'status' => 'new']);
        $lead->fill([
            'name' => $d['name'], 'phone' => $d['phone'], 'email' => $email, 'city' => $d['city'] ?? $lead->city,
            'source' => 'chatbot', 'ip' => $r->ip(),
            'message' => 'Started a chat with the AI assistant'.(! empty($d['interest']) ? '. Interested in: '.$d['interest'] : '').'.',
        ])->save();
        $r->session()->put('assistant.lead_id', $lead->id);

        if (! Setting::bool('assistant.otp_required', true)) {
            return $this->grant($r, $lead);
        }

        // OTP rules: 60s resend cooldown, max 3 codes per email per hour.
        $recent = AssistantOtp::where('email', $email)->where('created_at', '>=', now()->subHour())->orderByDesc('id')->get();
        if ($recent->first() && $recent->first()->created_at->diffInSeconds(now()) < 60) {
            return response()->json(['message' => 'Please wait a minute before requesting another code.', 'retry_after' => 60 - (int) $recent->first()->created_at->diffInSeconds(now())], 429);
        }
        if ($recent->count() >= 3) {
            return response()->json(['message' => 'Too many codes requested. Please try again in an hour.'], 429);
        }

        $code = (string) random_int(100000, 999999);
        AssistantOtp::create(['lead_id' => $lead->id, 'email' => $email, 'code_hash' => $this->hash($code), 'expires_at' => now()->addMinutes(10), 'ip' => $r->ip()]);

        try {
            $site = Setting::get('site.name', config('app.name'));
            Mail::raw("Hi {$lead->name},\n\nYour verification code for the $site assistant is: $code\n\nIt is valid for 10 minutes. If you didn't request it, you can ignore this email.", fn ($m) => $m->to($email)->subject("Your $site verification code: $code"));
        } catch (\Throwable $e) {
            Log::warning('Assistant OTP mail failed: '.$e->getMessage());
            return response()->json(['message' => "We couldn't send the verification email. Please check the address and try again."], 503);
        }

        return response()->json(['ok' => true, 'otp' => true, 'email' => $this->mask($email)]);
    }

    public function verify(Request $r)
    {
        $d = $r->validate(['code' => ['required', 'digits:6']]);
        $lead = Lead::find($r->session()->get('assistant.lead_id'));
        if (! $lead) return response()->json(['message' => 'Your session expired. Please start again.', 'restart' => true], 422);

        $otp = AssistantOtp::where('lead_id', $lead->id)->latest('id')->first();
        if (! $otp || $otp->expires_at->isPast()) return response()->json(['message' => 'That code has expired. Please request a new one.', 'expired' => true], 422);
        if ($otp->attempts >= 5) return response()->json(['message' => 'Too many wrong attempts. Please request a new code.', 'expired' => true], 422);

        if (! hash_equals($otp->code_hash, $this->hash($d['code']))) {
            $otp->increment('attempts');
            return response()->json(['message' => 'Incorrect code. '.max(0, 4 - $otp->attempts).' attempt(s) left.'], 422);
        }

        $first = ! $lead->email_verified_at;
        $lead->forceFill(['email_verified_at' => now()])->save();
        $otp->delete();
        if ($first) LeadNotifier::notify($lead);

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
        $s = AssistantSession::firstOrNew(['lead_id' => $lead->id]);
        if (! $s->exists) $s->token = Str::random(40);   // reuse the same row so limits can't be reset by re-registering
        $s->fill(['ip' => $r->ip(), 'user_agent' => Str::limit((string) $r->userAgent(), 250, '')])->save();
        $r->session()->put('assistant.token', $s->token);

        return response()->json(['ok' => true, 'verified' => true, 'token' => $s->token, 'name' => $lead->name]);
    }

    private function resolve(Request $r): ?AssistantSession
    {
        $t = (string) ($r->header('X-Assistant-Token') ?: $r->session()->get('assistant.token', ''));
        return $t !== '' ? AssistantSession::where('token', $t)->with('lead')->first() : null;
    }

    private function hash(string $code): string { return hash_hmac('sha256', $code, (string) config('app.key')); }

    private function mask(string $email): string
    {
        [$u, $d] = explode('@', $email) + [1 => ''];
        return Str::substr($u, 0, 2).str_repeat('*', max(1, strlen($u) - 2)).'@'.$d;
    }
}
