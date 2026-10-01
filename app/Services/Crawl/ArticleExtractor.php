<?php

namespace App\Services\Crawl;

use App\Services\PageFetcher;
use Carbon\Carbon;

/**
 * Turns any news/article page into clean text + metadata.
 * Strategy: structured data first (JSON-LD), then a readability-style scorer over the DOM with boilerplate
 * (nav, share bars, related stories, ads, comments) stripped. Pure function of the HTML, so it is unit-testable.
 */
class ArticleExtractor
{
    private const NOISE_TAGS = ['script', 'style', 'noscript', 'nav', 'aside', 'footer', 'form', 'iframe', 'svg', 'button', 'select', 'template', 'dialog'];
    private const NOISE_CLASS = '/(^|[\s_\-])(related|share|sharing|social|comments?|promo|advert\w*|ads?|sponsor\w*|newsletter|sidebar|widget|breadcrumbs?|subscribe|outbrain|taboola|recommend\w*|trending|popular|more-stories|read-?more|author-box|tags?|cookie|modal|popup|toc|most-read|also-read|story-?links?|paywall|login)([\s_\-]|$)/i';
    private const BOILERPLATE_LINE = '/^(also read|read more|read also|also see|follow us|subscribe|click here|advertisement|story continues|image source|photo credit|source:|join our|download the|sign up|copyright|all rights reserved|share this|for more updates|stay tuned|follow .* on)/i';
    private const ARTICLE_TYPES = ['NewsArticle', 'Article', 'BlogPosting', 'ReportageNewsArticle', 'AnalysisNewsArticle', 'Report'];

    /**
     * @return array{title:string,description:string,author:?string,published_at:?Carbon,text:string,words:int,images:array,image_alts:array,canonical:?string,site:?string}
     */
    public static function extract(string $html, string $url): array
    {
        $dom = PageFetcher::dom($html);
        $meta = PageFetcher::meta($dom);
        $ld = self::ldArticle(PageFetcher::jsonLd($dom));

        $title = self::cleanTitle((string) ($ld['headline'] ?? $meta['og:title'] ?? $meta['twitter:title'] ?? self::firstText($dom, 'h1') ?? self::firstText($dom, 'title') ?? ''), $meta['og:site_name'] ?? null);
        $published = self::date($ld['datePublished'] ?? $meta['article:published_time'] ?? $meta['og:published_time'] ?? $meta['pubdate'] ?? $meta['date'] ?? self::timeTag($dom) ?? self::dateFromUrl($url));
        $author = self::authorName($ld['author'] ?? null) ?? ($meta['author'] ?? null) ?: null;

        self::strip($dom);
        $root = self::contentRoot($dom);
        $paragraphs = $root ? self::paragraphs($root) : [];
        $text = implode("\n\n", $paragraphs);

        // JSON-LD articleBody is a reliable second opinion on sites whose markup defeats the scorer.
        $ldBody = isset($ld['articleBody']) && is_string($ld['articleBody']) ? self::normalize(strip_tags($ld['articleBody'])) : '';
        if (mb_strlen($ldBody) >= 500 && mb_strlen($ldBody) > mb_strlen($text) * 1.3) $text = $ldBody;

        $images = self::images($root, $meta, $ld, $url);

        return [
            'title' => $title,
            'description' => trim(html_entity_decode((string) ($meta['og:description'] ?? $meta['description'] ?? $ld['description'] ?? ''))),
            'author' => $author,
            'published_at' => $published,
            'text' => $text,
            'words' => count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY)),
            'images' => $images['urls'],
            'image_alts' => $images['alts'],
            'canonical' => self::canonical($dom, $meta, $url),
            'site' => ($meta['og:site_name'] ?? null) ?: null,
        ];
    }

    /* ------------------------------ metadata ------------------------------ */

    private static function ldArticle(array $nodes): array
    {
        foreach ($nodes as $n) {
            if (array_intersect((array) ($n['@type'] ?? []), self::ARTICLE_TYPES)) return $n;
        }
        return [];
    }

    private static function authorName(mixed $a): ?string
    {
        if (is_string($a)) return trim($a) ?: null;
        if (is_array($a)) {
            if (isset($a['name'])) return is_string($a['name']) ? trim($a['name']) : null;
            return self::authorName($a[0] ?? null);
        }
        return null;
    }

    /** Strip a trailing " | Site Name" / " - Site Name" from a page title. */
    public static function cleanTitle(string $t, ?string $site = null): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($site && str_ends_with(mb_strtolower($t), mb_strtolower($site))) $t = rtrim(mb_substr($t, 0, mb_strlen($t) - mb_strlen($site)), " |-–—:");
        if (preg_match('/^(.{25,}?)\s+[|–—]\s+[^|–—]{2,35}$/u', $t, $m)) return trim($m[1]);
        if (preg_match('/^(.{25,}?)\s+-\s+[^-]{2,30}$/u', $t, $m) && ! str_contains($m[1], ' - ')) return trim($m[1]);
        return $t;
    }

    public static function date(mixed $v): ?Carbon
    {
        if (! $v || ! is_string($v)) return null;
        try {
            $d = Carbon::parse($v);
            return ($d->year >= 2000 && $d->lte(now()->addDay())) ? $d : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function timeTag(\DOMDocument $dom): ?string
    {
        $xp = new \DOMXPath($dom);
        foreach (['//*[@itemprop="datePublished"]', '//time[@datetime]'] as $q) {
            $n = $xp->query($q)->item(0);
            if ($n) return $n->getAttribute('content') ?: $n->getAttribute('datetime') ?: trim($n->textContent);
        }
        return null;
    }

    public static function dateFromUrl(string $url): ?string
    {
        return preg_match('#/(20\d{2})/(0[1-9]|1[0-2])/(0[1-9]|[12]\d|3[01])(?:/|$)#', $url, $m) ? "$m[1]-$m[2]-$m[3]" : null;
    }

    private static function canonical(\DOMDocument $dom, array $meta, string $url): ?string
    {
        foreach ($dom->getElementsByTagName('link') as $l) {
            if (strtolower($l->getAttribute('rel')) === 'canonical' && $l->getAttribute('href')) return PageFetcher::absolute($l->getAttribute('href'), $url);
        }
        return ! empty($meta['og:url']) ? PageFetcher::absolute($meta['og:url'], $url) : null;
    }

    private static function firstText(\DOMDocument $dom, string $tag): ?string
    {
        $n = $dom->getElementsByTagName($tag)->item(0);
        return $n ? trim($n->textContent) : null;
    }

    /* ------------------------------ content ------------------------------- */

    /** Remove scripts, navigation, share bars, related-story blocks, ads and comment sections in place. */
    private static function strip(\DOMDocument $dom): void
    {
        $kill = [];
        foreach (self::NOISE_TAGS as $tag) foreach ($dom->getElementsByTagName($tag) as $n) $kill[] = $n;
        $xp = new \DOMXPath($dom);
        foreach ($xp->query('//*[@class or @id or @role]') as $n) {
            if (in_array($n->nodeName, ['html', 'body', 'article', 'main'], true)) continue;
            $sig = $n->getAttribute('class').' '.$n->getAttribute('id');
            if (preg_match(self::NOISE_CLASS, $sig) || in_array($n->getAttribute('role'), ['navigation', 'complementary', 'banner', 'contentinfo'], true)) $kill[] = $n;
        }
        foreach ($kill as $n) if ($n->parentNode) $n->parentNode->removeChild($n);
    }

    /** Pick the element holding the story: every substantial paragraph votes for its parent (and, half, its grandparent). */
    private static function contentRoot(\DOMDocument $dom): ?\DOMElement
    {
        $xp = new \DOMXPath($dom);
        $scores = []; $nodes = [];
        foreach ($xp->query('//p') as $p) {
            $len = mb_strlen(trim(preg_replace('/\s+/u', ' ', $p->textContent)));
            if ($len < 40) continue;
            $link = 0;
            foreach ($p->getElementsByTagName('a') as $a) $link += mb_strlen($a->textContent);
            $w = $len * (($link / max(1, $len)) > 0.5 ? 0.2 : 1);
            $parent = $p->parentNode;
            for ($lvl = 0; $lvl < 2 && $parent instanceof \DOMElement; $lvl++, $parent = $parent->parentNode) {
                $id = spl_object_id($parent);
                $nodes[$id] = $parent;
                $scores[$id] = ($scores[$id] ?? 0) + $w / ($lvl + 1);
            }
        }
        if (! $scores) return null;
        foreach ($scores as $id => $s) {
            $n = $nodes[$id];
            $sig = strtolower($n->getAttribute('class').' '.$n->getAttribute('id').' '.$n->getAttribute('itemprop'));
            if ($n->nodeName === 'article' || str_contains($sig, 'articlebody') || preg_match('/(article|story|post|entry)[\s_\-]*(body|content|text)|(^|[\s_-])content([\s_-]|$)/', $sig)) $scores[$id] = $s * 1.4;
            if (in_array($n->nodeName, ['body', 'html'], true)) $scores[$id] = $s * 0.6;
        }
        arsort($scores);
        $best = $nodes[array_key_first($scores)];
        // Step up when the winner is only a fragment of an <article>/articleBody that holds more of the story.
        for ($up = $best; $up instanceof \DOMElement; $up = $up->parentNode) {
            if ($up !== $best && ($up->nodeName === 'article' || str_contains(strtolower($up->getAttribute('itemprop')), 'articlebody'))) { $best = $up; break; }
            if (in_array($up->nodeName, ['body', 'html'], true)) break;
        }
        return $best instanceof \DOMElement ? $best : null;
    }

    /** @return array<int,string> */
    private static function paragraphs(\DOMElement $root): array
    {
        $out = []; $last = '';
        $xp = new \DOMXPath($root->ownerDocument);
        foreach ($xp->query('.//p | .//h2 | .//h3 | .//li | .//blockquote', $root) as $n) {
            if ($n->nodeName === 'li' && self::insideNestedPara($n)) continue;
            if ($n->nodeName === 'blockquote' && $xp->query('.//p', $n)->length) continue;     // its paragraphs are visited on their own
            $t = self::normalize($n->textContent);
            $isHead = in_array($n->nodeName, ['h2', 'h3'], true);
            if ($t === '' || $t === $last) continue;
            if (mb_strlen($t) < ($isHead ? 4 : ($n->nodeName === 'li' ? 20 : 30))) continue;
            if (preg_match(self::BOILERPLATE_LINE, $t)) continue;
            if ($n->nodeName === 'li') $t = '• '.$t;
            $out[] = $isHead ? '## '.$t : $t;
            $last = $t;
        }
        return $out;
    }

    private static function insideNestedPara(\DOMNode $n): bool
    {
        for ($p = $n->parentNode; $p; $p = $p->parentNode) if ($p->nodeName === 'p') return true;
        return false;
    }

    public static function normalize(string $t): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /* ------------------------------- images -------------------------------- */

    /** @return array{urls:array<int,string>,alts:array<string,string>} */
    private static function images(?\DOMElement $root, array $meta, array $ld, string $url): array
    {
        $urls = []; $alts = [];
        $push = function (?string $src, string $alt = '') use (&$urls, &$alts, $url) {
            if (! $src) return;
            $abs = PageFetcher::absolute($src, $url);
            if (! $abs || preg_match('/logo|icon|avatar|sprite|pixel|placeholder|blank|spacer|\.svg|\.gif|gravatar|emoji|badge|banner-ad/i', $abs)) return;
            $urls[] = $abs;
            if ($alt !== '') $alts[$abs] = $alt;
        };
        $push($meta['og:image'] ?? $meta['twitter:image'] ?? null);
        foreach ((array) ($ld['image'] ?? []) as $i) $push(is_array($i) ? ($i['url'] ?? null) : (is_string($i) ? $i : null));

        foreach ($root ? $root->getElementsByTagName('img') : [] as $img) {
            $w = (int) $img->getAttribute('width'); $h = (int) $img->getAttribute('height');
            if (($w && $w < 300) || ($h && $h < 150)) continue;
            $push(self::bestSrc($img), trim($img->getAttribute('alt').' '.$img->getAttribute('title')));
        }
        return ['urls' => array_slice(array_values(array_unique($urls)), 0, 12), 'alts' => $alts];
    }

    /** Lazy-load attributes first, then the widest candidate from srcset. */
    private static function bestSrc(\DOMElement $img): ?string
    {
        foreach (['data-src', 'data-lazy-src', 'data-original'] as $a) if ($img->getAttribute($a)) return $img->getAttribute($a);
        $set = $img->getAttribute('srcset') ?: $img->getAttribute('data-srcset');
        if ($set) {
            $best = null; $bw = 0;
            foreach (explode(',', $set) as $c) {
                $p = preg_split('/\s+/', trim($c));
                $w = (int) rtrim($p[1] ?? '0', 'wx');
                if ($p[0] && $w >= $bw) { $best = $p[0]; $bw = $w; }
            }
            if ($best) return $best;
        }
        return $img->getAttribute('src') ?: null;
    }
}
