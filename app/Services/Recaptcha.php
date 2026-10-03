<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google reCAPTCHA v3 (invisible). Keys live in Admin -> Settings -> Assistant. With no keys saved the check is skipped
 * (the honeypot, per-IP limits and rate limits still apply), so the site keeps working until the keys are added.
 */
class Recaptcha
{
    public static function siteKey(): string
    {
        return trim((string) Setting::get('recaptcha.site_key', ''));
    }

    public static function enabled(): bool
    {
        return self::siteKey() !== '' && trim((string) Setting::get('recaptcha.secret_key', '')) !== '';
    }

    /** @return array{ok:bool,message?:string} */
    public static function verify(?string $token, string $action, string $ip): array
    {
        if (! self::enabled()) return ['ok' => true];
        if (! $token) return ['ok' => false, 'message' => 'Please try again. The security check could not load (check your connection or ad blocker).'];

        try {
            $res = Http::asForm()->timeout(8)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => trim((string) Setting::get('recaptcha.secret_key')), 'response' => $token, 'remoteip' => $ip,
            ]);
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA request failed: '.$e->getMessage());
            return ['ok' => false, 'message' => 'The security check is unavailable right now. Please try again in a moment.'];
        }

        $d = $res->json() ?: [];
        $min = (float) Setting::get('recaptcha.min_score', 0.5);
        $ok = ! empty($d['success']) && (($d['action'] ?? $action) === $action) && ((float) ($d['score'] ?? 1)) >= $min;
        if (! $ok) Log::info('reCAPTCHA rejected', ['codes' => $d['error-codes'] ?? null, 'score' => $d['score'] ?? null]);
        return $ok ? ['ok' => true] : ['ok' => false, 'message' => 'We could not verify that you are human. Please refresh the page and try again.'];
    }
}
