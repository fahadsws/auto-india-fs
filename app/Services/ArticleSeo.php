<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Str;

/** AI-written SEO fields (meta, keywords, takeaways, tags, FAQ) for an article, from its own text. */
class ArticleSeo
{
    /** @param array{title?:string,excerpt?:string,body?:string} $a  @return array<string,mixed>|null null when the AI call fails */
    public static function suggest(array $a): ?array
    {
        $text = Str::limit(TextTools::plain((string) ($a['body'] ?? '')), 6000, '');
        if (trim($text) === '') return null;

        $d = AiClient::json([
            ['role' => 'system', 'content' => 'You are an SEO editor for an Indian car news site. Using ONLY facts in the article given, reply with ONE JSON object: {"meta_title":"max 60 chars","meta_description":"max 155 chars, includes the key fact","meta_keywords":"6-10 comma separated keywords","tldr":["3 short takeaways"],"tags":["4-6 lowercase tags"],"faq":[{"q":"","a":""}]} with exactly 3 faq items answered only from the article.'],
            ['role' => 'user', 'content' => 'Title: '.($a['title'] ?? '')."\nSummary: ".($a['excerpt'] ?? '')."\n\n".$text],
        ], ['temperature' => 0.3, 'max_tokens' => 1200]);
        if (! $d) return null;

        $faq = array_slice(array_values(array_filter((array) ($d['faq'] ?? []), fn ($f) => is_array($f) && ! empty($f['q']) && ! empty($f['a']))), 0, 5);

        return [
            'meta_title' => Str::limit((string) ($d['meta_title'] ?? ''), 70, '') ?: null,
            'meta_description' => Str::limit((string) ($d['meta_description'] ?? ''), 160, '') ?: null,
            'meta_keywords' => Str::limit((string) ($d['meta_keywords'] ?? ''), 300, '') ?: null,
            'tldr' => array_slice(array_map('strval', (array) ($d['tldr'] ?? [])), 0, 3) ?: null,
            'tags' => array_slice(array_map(fn ($t) => Str::lower(trim((string) $t)), (array) ($d['tags'] ?? [])), 0, 6) ?: null,
            'faq' => array_map(fn ($f) => ['q' => trim((string) $f['q']), 'a' => trim((string) $f['a'])], $faq) ?: null,
        ];
    }

    /** Fill ONLY the fields that are still empty on the article (never overwrite what an editor wrote). Returns false if nothing was done. */
    public static function fillMissing(Article $article): bool
    {
        $empty = array_filter(['meta_title', 'meta_description', 'meta_keywords', 'tldr', 'tags', 'faq'], fn ($f) => blank($article->{$f}));
        if (! $empty || ! AiClient::configured()) return false;

        $s = self::suggest(['title' => $article->title, 'excerpt' => $article->excerpt, 'body' => $article->body]);
        if (! $s) return false;

        $fill = array_filter(array_intersect_key($s, array_flip($empty)), fn ($v) => filled($v));
        if (! $fill) return false;
        $article->forceFill($fill)->save();

        return true;
    }
}
