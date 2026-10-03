<?php

namespace App\Services;

use App\Services\Crawl\ArticleExtractor;
use App\Services\Crawl\Fetcher;
use Illuminate\Support\Facades\Http;

/**
 * Thin facade over the crawl layer (App\Services\Crawl\*) plus small HTML helpers shared by the scrapers:
 * metadata, JSON-LD and URL resolution. All network access goes through Crawl\Fetcher (retries, throttling, charset).
 */
class PageFetcher
{
    public static function http()
    {
        return Http::timeout(25)->withHeaders([
            'User-Agent' => Fetcher::userAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml,*/*',
            'Accept-Language' => 'en-IN,en;q=0.9',
        ]);
    }

    /** @return array{status:int, body:string, url:string, error:?string}|null  null only when nothing could be reached at all */
    public static function get(string $url): ?array
    {
        $r = Fetcher::get($url);
        if ($r['status'] === 0) return null;
        return ['status' => $r['status'], 'body' => $r['body'], 'url' => $r['url'], 'error' => $r['error']];
    }

    public static function dom(string $html): \DOMDocument
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        return $dom;
    }

    /** Resolve $href against $base like a browser: handles //host, /path, ../up, ?query-only and ./relative. */
    public static function absolute(string $href, string $base): string
    {
        $href = trim(html_entity_decode($href));
        if ($href === '' || str_starts_with($href, 'data:')) return '';
        if (preg_match('#^https?://#i', $href)) return $href;
        $p = parse_url($base);
        if (empty($p['host'])) return '';
        $scheme = $p['scheme'] ?? 'https';
        $root = $scheme.'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '');
        if (str_starts_with($href, '//')) return $scheme.':'.$href;
        if (str_starts_with($href, '/')) return $root.self::dotSegments($href);
        if (str_starts_with($href, '?')) return $root.($p['path'] ?? '/').$href;

        $dir = rtrim(substr($p['path'] ?? '/', 0, strrpos($p['path'] ?? '/', '/') + 1), '/');
        return $root.self::dotSegments($dir.'/'.$href);
    }

    private static function dotSegments(string $pathAndQuery): string
    {
        $q = '';
        if (($pos = strpos($pathAndQuery, '?')) !== false) { $q = substr($pathAndQuery, $pos); $pathAndQuery = substr($pathAndQuery, 0, $pos); }
        $out = [];
        foreach (explode('/', $pathAndQuery) as $seg) {
            if ($seg === '..') array_pop($out);
            elseif ($seg !== '.') $out[] = $seg;
        }
        $path = implode('/', $out);
        return ($path === '' || $path[0] !== '/' ? '/' : '').$path.$q;
    }

    /** @return array<string,string> og:* / twitter:* / description meta values */
    public static function meta(\DOMDocument $dom): array
    {
        $out = [];
        foreach ($dom->getElementsByTagName('meta') as $m) {
            $k = $m->getAttribute('property') ?: $m->getAttribute('name') ?: $m->getAttribute('itemprop');
            if ($k && ! isset($out[$k])) $out[$k] = $m->getAttribute('content');
        }
        return $out;
    }

    /** @return array<int,array> flattened JSON-LD nodes (handles @graph and arrays) */
    public static function jsonLd(\DOMDocument $dom): array
    {
        $nodes = [];
        foreach ($dom->getElementsByTagName('script') as $s) {
            if (stripos($s->getAttribute('type'), 'ld+json') === false) continue;
            $j = json_decode(trim($s->textContent), true);
            if (! is_array($j)) continue;
            $stack = isset($j[0]) ? $j : [$j];
            while ($stack) {
                $n = array_shift($stack);
                if (! is_array($n)) continue;
                if (isset($n['@graph']) && is_array($n['@graph'])) array_push($stack, ...$n['@graph']);
                $nodes[] = $n;
            }
        }
        return $nodes;
    }

    /**
     * Fetch and extract one article page (title, text, date, author, images). Null when the page cannot be
     * fetched or has no readable story text; see Crawl\ArticleExtractor for how the text is found.
     *
     * @return array{title:string,description:string,author:?string,published_at:?\Carbon\Carbon,text:string,words:int,image:?string,images:array,image_alts:array,canonical:?string,site:?string,url:string,status:int}|null
     */
    public static function article(string $url): ?array
    {
        $r = Fetcher::get($url);
        if (! $r['ok']) return null;
        $a = ArticleExtractor::extract($r['body'], $r['url']);
        return $a + ['image' => $a['images'][0] ?? null, 'url' => $r['url'], 'status' => $r['status'], 'specs' => SpecFiller::extract($r['body'])];
    }
}
