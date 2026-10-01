<?php

namespace App\Services\Crawl;

use App\Services\Robots;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The single door every crawler goes through: identifies itself, throttles per host, retries transient failures
 * (timeouts, 429, 5xx) with backoff, refuses non-text payloads and oversized bodies, and normalises the charset.
 * It never tries to defeat bot protection: a 401/403 is reported as "blocked" so the source can be reviewed.
 */
class Fetcher
{
    private const RETRY_STATUS = [408, 425, 429, 500, 502, 503, 504, 520, 521, 522, 523, 524];
    private const MAX_BYTES = 6 * 1024 * 1024;

    /**
     * @param  array{attempts?:int,timeout?:int,host_delay_ms?:int,accept?:string}  $o
     * @return array{ok:bool,status:int,body:string,url:string,type:string,error:?string,attempts:int}
     */
    public static function get(string $url, array $o = []): array
    {
        $attempts = max(1, $o['attempts'] ?? 3);
        $res = ['ok' => false, 'status' => 0, 'body' => '', 'url' => $url, 'type' => '', 'error' => null, 'attempts' => 0];

        if (! preg_match('#^https?://[^/\s]+#i', $url)) return ['error' => 'Not a valid http(s) URL'] + $res;

        for ($i = 1; $i <= $attempts; $i++) {
            $res['attempts'] = $i;
            self::throttle($url, $o['host_delay_ms'] ?? 800);
            $retryAfter = null;
            try {
                $r = Http::timeout($o['timeout'] ?? 25)->connectTimeout(10)
                    ->withHeaders([
                        'User-Agent' => self::userAgent(),
                        'Accept' => $o['accept'] ?? 'text/html,application/xhtml+xml,application/xml;q=0.9,application/rss+xml,application/json;q=0.8,*/*;q=0.5',
                        'Accept-Language' => 'en-IN,en;q=0.9',
                    ])
                    ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
                    ->get($url);

                $res['status'] = $r->status();
                $res['url'] = (string) ($r->effectiveUri() ?? $url);
                $res['type'] = strtolower(trim(explode(';', (string) $r->header('Content-Type'))[0]));

                if ($r->successful()) {
                    if ($res['type'] !== '' && ! preg_match('#^(text/|application/(xhtml|xml|rss|atom|json|ld\+json|rdf))#', $res['type'])) {
                        return ['error' => "Unsupported content type {$res['type']}"] + $res;
                    }
                    $body = substr($r->body(), 0, self::MAX_BYTES);
                    return ['ok' => true, 'body' => self::toUtf8($body, $res['type'], $r->header('Content-Type')), 'error' => null] + $res;
                }

                $res['error'] = in_array($res['status'], [401, 403], true) ? "Blocked by site (HTTP {$res['status']})"
                    : ($res['status'] >= 300 && $res['status'] < 400 ? "Site answered with a redirect challenge (HTTP {$res['status']}) - bot protection" : 'HTTP '.$res['status']);
                if (! in_array($res['status'], self::RETRY_STATUS, true)) return $res;     // 404, 403 ... will not improve
                $retryAfter = (int) $r->header('Retry-After') ?: null;
            } catch (\Throwable $e) {
                $res['error'] = self::shortError($e->getMessage());
            }
            if ($i < $attempts) usleep(self::backoffMs($i, $retryAfter) * 1000);
        }
        return $res;
    }

    public static function userAgent(): string
    {
        return 'Mozilla/5.0 (compatible; '.Robots::AGENT.'/1.0; +'.config('app.url').')';
    }

    /** Enforce a minimum gap between two requests to the same host, even across separate jobs. */
    private static function throttle(string $url, int $gapMs): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $gapMs <= 0) return;
        $key = 'crawl.last.'.$host;
        $last = (float) Cache::get($key, 0);
        $wait = $last + $gapMs / 1000 - microtime(true);
        if ($wait > 0) usleep((int) (min($wait, 10) * 1_000_000));
        Cache::put($key, microtime(true), 60);
    }

    public static function backoffMs(int $attempt, ?int $retryAfter = null): int
    {
        if ($retryAfter) return min($retryAfter, 20) * 1000;
        return (int) (min(1000 * (2 ** ($attempt - 1)), 8000) + random_int(0, 400));
    }

    private static function shortError(string $m): string
    {
        if (preg_match('/cURL error (\d+)/', $m, $x)) {
            return match ((int) $x[1]) { 28 => 'Timed out', 6 => 'DNS lookup failed', 7 => 'Connection refused', 35, 60 => 'SSL error', default => 'Network error (cURL '.$x[1].')' };
        }
        return mb_substr($m, 0, 120);
    }

    /** Convert a response body to UTF-8 using the HTTP header, a <meta charset>, or a BOM. */
    public static function toUtf8(string $body, string $type = '', ?string $contentType = null): string
    {
        if (str_starts_with($body, "\xEF\xBB\xBF")) return substr($body, 3);
        $charset = null;
        if ($contentType && preg_match('/charset=["\']?([\w\-]+)/i', $contentType, $m)) $charset = $m[1];
        if (! $charset && preg_match('/<meta[^>]+charset=["\']?([\w\-]+)/i', substr($body, 0, 4096), $m)) $charset = $m[1];
        if (! $charset && preg_match('/^<\?xml[^>]+encoding=["\']([\w\-]+)/i', $body, $m)) $charset = $m[1];
        if ($charset && ! in_array(strtolower($charset), ['utf-8', 'utf8'], true) && in_array(strtoupper($charset), array_map('strtoupper', mb_list_encodings()), true)) {
            $conv = @mb_convert_encoding($body, 'UTF-8', $charset);
            if (is_string($conv)) return $conv;
        }
        return mb_check_encoding($body, 'UTF-8') ? $body : (string) @mb_convert_encoding($body, 'UTF-8', 'Windows-1252');
    }
}
