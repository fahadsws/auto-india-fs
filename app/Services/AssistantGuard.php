<?php

namespace App\Services;

use App\Models\AssistantSession;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Token-waste protection for the assistant. Every chat request is admitted (or refused) here BEFORE
 * any LLM call, in layers: block list -> min gap -> per-minute -> daily messages -> daily tokens
 * -> lifetime tokens -> per-IP daily tokens -> global daily tokens (degrades to site-data-only answers).
 * All limits are editable in Admin -> Settings -> Assistant limits.
 */
class AssistantGuard
{
    public const DEFAULTS = [
        'per_min' => 8, 'msgs_day' => 40, 'tokens_day' => 40000, 'tokens_total' => 200000,
        'ip_tokens_day' => 80000, 'global_tokens_day' => 500000, 'min_gap' => 2, 'tts_chars_day' => 6000,
        'max_input' => 300, 'max_output' => 480, 'max_output_voice' => 340,
    ];

    public static function limit(string $k): int
    {
        return max(0, (int) Setting::get("assistant.limit_$k", self::DEFAULTS[$k]));
    }

    /** Rough token estimate (~4 chars/token) for pre-flight checks. */
    public static function estimate(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    private static function fresh(AssistantSession $s): void
    {
        if (! $s->usage_date || ! $s->usage_date->isToday()) {
            $s->forceFill(['usage_date' => today(), 'messages_today' => 0, 'tokens_today' => 0, 'tts_chars_today' => 0])->save();
        }
    }

    private static function deny(string $reason, string $msg, int $retry = 0): array
    {
        return ['allow' => false, 'degraded' => false, 'reason' => $reason, 'message' => $msg, 'retry_after' => $retry];
    }

    /** @return array{allow:bool,degraded:bool,reason?:string,message?:string,retry_after?:int} */
    public static function admit(AssistantSession $s, string $message, string $ip): array
    {
        self::fresh($s);

        if ($s->blocked_until && $s->blocked_until->isFuture()) {
            return self::deny('blocked', 'You have been paused for a while because of too many requests. Please try again later.', max(1, now()->diffInSeconds($s->blocked_until)));
        }

        $gap = self::limit('min_gap');
        if ($gap && $s->last_message_at && $s->last_message_at->diffInSeconds(now()) < $gap) {
            return self::strike($s, 'too_fast', 'Please wait a moment before sending another message.', $gap);
        }

        $key = 'asst:min:'.$s->id;
        if (RateLimiter::tooManyAttempts($key, max(1, self::limit('per_min')))) {
            return self::strike($s, 'per_minute', 'You are sending messages too fast. Give me a minute.', RateLimiter::availableIn($key));
        }

        $est = self::estimate($message) + 500; // question + prompt overhead (output is capped separately)
        if ($s->messages_today >= self::limit('msgs_day')) {
            return self::deny('daily_messages', "You've reached today's chat limit. Please come back tomorrow or call our team.", self::secondsToMidnight());
        }
        if ($s->tokens_today + $est > self::limit('tokens_day')) {
            return self::deny('daily_tokens', "You've used today's free AI quota. Please come back tomorrow or call our team.", self::secondsToMidnight());
        }
        if ($s->tokens_total + $est > self::limit('tokens_total')) {
            return self::deny('lifetime_tokens', 'Your free AI chat quota is finished. Our team will be happy to help you directly.', 0);
        }
        if ((int) Cache::get(self::ipKey($ip), 0) + $est > self::limit('ip_tokens_day')) {
            return self::deny('ip_tokens', 'Too much activity from your network today. Please try again tomorrow.', self::secondsToMidnight());
        }

        // Global kill-switch: no LLM spend, but the visitor still gets answers from our own data.
        $degraded = (int) Cache::get(self::globalKey(), 0) + $est > self::limit('global_tokens_day');

        RateLimiter::hit($key, 60);
        return ['allow' => true, 'degraded' => $degraded];
    }

    /** Record actual spend after the reply (cached/canned replies spend 0). */
    public static function record(AssistantSession $s, int $in, int $out, string $ip): void
    {
        $total = $in + $out;
        $s->forceFill([
            'messages_today' => $s->messages_today + 1, 'messages_total' => $s->messages_total + 1,
            'tokens_today' => $s->tokens_today + $total, 'tokens_total' => $s->tokens_total + $total,
            'last_message_at' => now(), 'strikes' => 0,
        ])->save();
        if ($total > 0) {
            self::bump(self::ipKey($ip), $total);
            self::bump(self::globalKey(), $total);
        }
    }

    /** Count LLM tokens spent outside the chat (e.g. the roast page) against the same site-wide and per-IP daily budgets. */
    public static function spend(int $tokens, string $ip): void
    {
        if ($tokens <= 0) return;
        self::bump(self::ipKey($ip), $tokens);
        self::bump(self::globalKey(), $tokens);
    }

    public static function admitTts(AssistantSession $s, string $text): bool
    {
        self::fresh($s);
        $len = mb_strlen($text);
        if ($s->tts_chars_today + $len > self::limit('tts_chars_day')) return false;
        $s->increment('tts_chars_today', $len);
        return true;
    }

    /** Serialise a visitor's requests so parallel calls can't bypass the limits. */
    public static function lock(AssistantSession $s): ?\Illuminate\Contracts\Cache\Lock
    {
        $lock = Cache::lock('asst:lock:'.$s->id, 40);
        return $lock->get() ? $lock : null;
    }

    public static function todayGlobalTokens(): int
    {
        return (int) Cache::get(self::globalKey(), 0);
    }

    private static function strike(AssistantSession $s, string $reason, string $msg, int $retry): array
    {
        $s->increment('strikes');
        if ($s->strikes >= 6) {
            $s->forceFill(['blocked_until' => now()->addMinutes(10), 'strikes' => 0])->save();
        }
        return self::deny($reason, $msg, $retry);
    }

    private static function bump(string $key, int $n): void
    {
        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key, $n);
    }

    private static function ipKey(string $ip): string { return 'asst:ip:'.$ip.':'.today()->toDateString(); }
    private static function globalKey(): string { return 'asst:global:'.today()->toDateString(); }
    private static function secondsToMidnight(): int { return (int) now()->diffInSeconds(now()->endOfDay()); }
}
