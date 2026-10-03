<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider-agnostic client for any OpenAI-compatible /chat/completions API
 * (OpenRouter, Groq, Together, Gemini's OpenAI endpoint, OpenAI, local Ollama...).
 * Configure base URL, key and models in Admin → Settings.
 */
class AiClient
{
    /** Why the last call failed (shown in automation logs so a broken provider is obvious). */
    private static ?string $lastError = null;
    /** Token usage reported by the provider for the last successful call: ['in'=>int,'out'=>int]. */
    private static array $lastUsage = ['in' => 0, 'out' => 0];

    public static function lastUsage(): array
    {
        return self::$lastUsage;
    }

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    public static function configured(): bool
    {
        return (bool) Setting::get('ai.api_key') || Setting::get('ai.base_url') && str_contains((string) Setting::get('ai.base_url'), 'localhost');
    }

    /** The configured endpoint, with well-known mistakes repaired (e.g. Gemini's native ".../v1beta/models" URL). */
    public static function baseUrl(): string
    {
        $base = rtrim(trim((string) Setting::get('ai.base_url', '')) ?: 'https://openrouter.ai/api/v1', '/');
        $base = preg_replace('#/chat/completions$#', '', $base);
        if (str_contains($base, 'generativelanguage.googleapis.com') && ! str_ends_with($base, '/openai')) {
            return 'https://generativelanguage.googleapis.com/v1beta/openai';
        }
        return $base;
    }

    /** Recursively make every string valid UTF-8 (invalid bytes dropped, control characters removed). */
    public static function utf8(mixed $v): mixed
    {
        if (is_array($v)) { $o = []; foreach ($v as $k => $x) $o[is_string($k) ? self::utf8($k) : $k] = self::utf8($x); return $o; }   // keys too
        if (! is_string($v)) return $v;
        if (! mb_check_encoding($v, 'UTF-8')) {
            $fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $v);
            $v = $fixed !== false ? $fixed : mb_convert_encoding($v, 'UTF-8', 'UTF-8');
        }
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v) ?? '';
    }

    /** Returns the assistant text, or null on failure / not configured (see lastError()). */
    public static function chat(array $messages, array $opts = []): ?string
    {
        self::$lastError = null;
        self::$lastUsage = ['in' => 0, 'out' => 0];
        if (! self::configured()) { self::$lastError = 'AI provider is not configured'; return null; }

        $messages = self::utf8($messages);   // page text can carry broken bytes; json_encode would refuse the whole request
        $base = self::baseUrl();
        $models = array_values(array_unique(array_filter([
            $opts['model'] ?? Setting::get('ai.model', 'meta-llama/llama-3.3-70b-instruct:free'),
            Setting::get('ai.fallback_model'),
        ])));

        foreach ($models as $model) {
            try {
                $res = Http::withToken((string) Setting::get('ai.api_key', 'none'))
                    ->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => config('app.name')])
                    ->timeout($opts['timeout'] ?? 90)->retry(2, 1500, fn ($e) => $e instanceof \Illuminate\Http\Client\ConnectionException || ($e instanceof \Illuminate\Http\Client\RequestException && in_array($e->response->status(), [408, 429, 500, 502, 503, 504], true)), throw: false)
                    ->post("$base/chat/completions", array_filter([
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => $opts['temperature'] ?? 0.7,
                        'max_tokens' => $opts['max_tokens'] ?? 1800,
                    ], fn ($v) => $v !== null));

                if ($res->successful()) {
                    $text = data_get($res->json(), 'choices.0.message.content');
                    if (is_string($text) && trim($text) !== '') {
                        self::$lastUsage = ['in' => (int) data_get($res->json(), 'usage.prompt_tokens', 0), 'out' => (int) data_get($res->json(), 'usage.completion_tokens', 0)];
                        return trim($text);
                    }
                    self::$lastError = "{$model}: empty response".(($fr = data_get($res->json(), 'choices.0.finish_reason')) ? " (finish_reason: $fr)" : '');
                } else {
                    self::$lastError = "{$model}: HTTP ".$res->status().' '.substr(preg_replace('/\s+/', ' ', $res->body()), 0, 200);
                }
                Log::warning('AI call failed on '.self::$lastError);
            } catch (\Throwable $e) {
                self::$lastError = "{$model}: ".substr($e->getMessage(), 0, 200);
                Log::warning('AI call error on '.self::$lastError);
            }
        }
        return null;
    }

    /** Ask for JSON and decode it leniently (models often wrap it in prose or ``` fences). */
    public static function json(array $messages, array $opts = []): ?array
    {
        $text = self::chat($messages, $opts);
        if (! $text) return null;
        $text = preg_replace('/^```(?:json)?|```$/m', '', $text);
        $start = strpos($text, '{'); $end = strrpos($text, '}');
        if ($start === false || $end === false) { self::$lastError = 'Model reply contained no JSON object'; return null; }
        $raw = substr($text, $start, $end - $start + 1);
        $data = json_decode($raw, true) ?? json_decode(preg_replace('/,\s*([}\]])/', '$1', $raw), true);   // tolerate trailing commas
        if (! is_array($data)) { self::$lastError = 'Model reply was not valid JSON (possibly cut off - raise max_tokens)'; return null; }
        return $data;
    }

    /** Quick connectivity check used by `php artisan ai:test` and the settings page. */
    public static function test(): array
    {
        $t0 = microtime(true);
        $text = self::chat([['role' => 'user', 'content' => 'Reply with the single word: pong']], ['max_tokens' => 20, 'temperature' => 0, 'timeout' => 30]);
        return ['ok' => $text !== null, 'reply' => $text, 'error' => self::$lastError, 'endpoint' => self::baseUrl().'/chat/completions', 'ms' => (int) ((microtime(true) - $t0) * 1000)];
    }
}
