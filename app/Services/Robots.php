<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Minimal robots.txt evaluator (longest-match, wildcard and $ support). Used so scrapers stay polite. */
class Robots
{
    public const AGENT = 'AutomobilIndiaBot';

    public static function allowed(string $url): bool
    {
        $p = parse_url($url);
        if (empty($p['host'])) return false;
        $key = 'robots.'.$p['host'];
        $rules = Cache::get($key);
        if ($rules === null) {
            [$rules, $reliable] = self::load(($p['scheme'] ?? 'https').'://'.$p['host']);
            Cache::put($key, $rules, $reliable ? now()->addHours(12) : now()->addMinutes(10));     // a timeout must not block a site for half a day
        }
        $path = ($p['path'] ?? '/').(isset($p['query']) ? '?'.$p['query'] : '');

        $best = null; $bestLen = -1;
        foreach ($rules as [$type, $pattern]) {
            if ($pattern === '') continue;
            if (self::matches($pattern, $path) && strlen($pattern) >= $bestLen) {
                if (strlen($pattern) > $bestLen || $type === 'allow') { $best = $type; $bestLen = strlen($pattern); }
            }
        }
        return $best !== 'disallow';
    }

    private static function matches(string $pattern, string $path): bool
    {
        $re = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '#')).'#';
        return (bool) preg_match($re, $path);
    }

    /** @return array{0:array<int, array{0:string,1:string}>,1:bool}  rules, and whether the answer is reliable enough to cache long */
    private static function load(string $origin): array
    {
        try {
            $res = Http::timeout(10)->withHeaders(['User-Agent' => self::AGENT])->get($origin.'/robots.txt');
            if ($res->status() >= 400 && $res->status() < 500) return [[], true];      // no robots.txt => allowed
            if (! $res->successful()) return [[['disallow', '/']], false];              // server trouble => be conservative, briefly
        } catch (\Throwable) {
            return [[['disallow', '/']], false];
        }

        $groups = []; $agents = []; $collecting = false; $current = [];
        foreach (preg_split('/\R/', $res->body()) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if (! str_contains($line, ':')) continue;
            [$k, $v] = array_map('trim', explode(':', $line, 2)); $k = strtolower($k);
            if ($k === 'user-agent') {
                if ($collecting) { $groups[] = [$agents, $current]; $agents = []; $current = []; $collecting = false; }
                $agents[] = strtolower($v);
            } elseif (in_array($k, ['allow', 'disallow'], true)) {
                $collecting = true; $current[] = [$k, $v];
            }
        }
        if ($agents) $groups[] = [$agents, $current];

        $mine = strtolower(self::AGENT);
        foreach ($groups as [$ag, $rules]) if (in_array($mine, $ag, true)) return [$rules, true];
        foreach ($groups as [$ag, $rules]) if (in_array('*', $ag, true)) return [$rules, true];
        return [[], true];
    }
}
