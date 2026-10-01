<?php

namespace App\Services\Crawl;

use App\Services\PageFetcher;

/** Finds the links worth following on a listing/index page, and feeds advertised on it. */
class LinkFinder
{
    private const FILE_EXT = '/\.(jpe?g|png|gif|webp|svg|pdf|zip|mp4|mp3|css|js|xml|ico)(\?|$)/i';
    private const INDEX_PATH = '#/(tag|tags|category|categories|author|authors|topic|topics|search|login|signup|register|contact|about|privacy|terms|advertise|feed|rss|page/\d+|amp)(/|$)#i';
    private const TRACKING = '/^(utm_\w+|fbclid|gclid|ref|ref_src|cmpid|source|from|spm)$/i';

    /**
     * Article-like links on a page, in page order. A link qualifies when it is on the same site, looks like a story URL
     * (long hyphenated slug, or matches $pattern) and is not a tag/category/pagination/social link.
     *
     * @return array<int,array{title:string,link:string}>
     */
    public static function articleLinks(string $html, string $base, ?string $pattern = null, int $limit = 60): array
    {
        $dom = PageFetcher::dom($html);
        $host = self::host($base);
        $seen = []; $out = [];

        foreach ($dom->getElementsByTagName('a') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href === '' || preg_match('/^(#|javascript:|mailto:|tel:|whatsapp:)/i', $href)) continue;
            $url = self::normalize(PageFetcher::absolute($href, $base));
            if (! $url || isset($seen[$url]) || self::host($url) !== $host) continue;
            if ($pattern ? ! str_contains($url, $pattern) : ! self::looksLikeStory($url)) continue;
            if (preg_match(self::FILE_EXT, $url) || preg_match(self::INDEX_PATH, (string) parse_url($url, PHP_URL_PATH))) continue;
            if (rtrim($url, '/') === rtrim(self::normalize($base), '/')) continue;

            $seen[$url] = true;
            $out[] = ['title' => self::anchorTitle($a), 'link' => $url];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Story slugs: last path segment with 3+ hyphenated words, or a long path carrying a numeric id. */
    public static function looksLikeStory(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') return false;
        $last = preg_replace('/\.(html?|php|aspx?|cms)$/i', '', basename($path));
        return substr_count($last, '-') >= 3 || (strlen($path) >= 35 && preg_match('/\d{4,}/', $path) === 1);
    }

    /** Strip fragment and tracking parameters so the same story never has two identities. */
    public static function normalize(string $url): string
    {
        if ($url === '') return '';
        $url = strtok($url, '#');
        $p = parse_url($url);
        if (empty($p['host'])) return '';
        $q = '';
        if (! empty($p['query'])) {
            parse_str($p['query'], $params);
            $params = array_filter($params, fn ($k) => ! preg_match(self::TRACKING, (string) $k), ARRAY_FILTER_USE_KEY);
            $q = $params ? '?'.http_build_query($params) : '';
        }
        return strtolower($p['scheme'] ?? 'https').'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '').($p['path'] ?? '/').$q;
    }

    public static function host(string $url): string
    {
        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    /** Headline-ish text for a link: heading inside/around it, title attribute, image alt, or its own text. */
    private static function anchorTitle(\DOMElement $a): string
    {
        foreach (['h1', 'h2', 'h3', 'h4'] as $h) {
            $n = $a->getElementsByTagName($h)->item(0);
            if ($n && mb_strlen(trim($n->textContent)) > 15) return self::clean($n->textContent);
        }
        $t = self::clean($a->textContent);
        if (mb_strlen($t) >= 20) return $t;
        foreach ([$a->getAttribute('title'), $a->getAttribute('aria-label')] as $c) if (mb_strlen(trim($c)) >= 20) return self::clean($c);
        $img = $a->getElementsByTagName('img')->item(0);
        if ($img && mb_strlen(trim($img->getAttribute('alt'))) >= 20) return self::clean($img->getAttribute('alt'));
        $p = $a->parentNode;
        if ($p instanceof \DOMElement && preg_match('/^h[1-4]$/', $p->nodeName)) return self::clean($p->textContent);
        return '';
    }

    private static function clean(string $t): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** RSS/Atom feed URLs advertised in <link rel="alternate">. */
    public static function feedLinks(string $html, string $base): array
    {
        $dom = PageFetcher::dom($html); $out = [];
        foreach ($dom->getElementsByTagName('link') as $l) {
            if (strtolower($l->getAttribute('rel')) === 'alternate' && preg_match('#application/(rss|atom)\+xml#i', $l->getAttribute('type')) && $l->getAttribute('href')) {
                $out[] = PageFetcher::absolute($l->getAttribute('href'), $base);
            }
        }
        return array_values(array_unique($out));
    }
}
