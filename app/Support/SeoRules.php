<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Validation + normalisation shared by every form that has the SEO & Schema / FAQ tabs (Pages, SEO entries). */
class SeoRules
{
    public const ROBOTS = ['index,follow' => 'Index, follow', 'noindex,follow' => 'No index, follow', 'noindex,nofollow' => 'No index, no follow'];
    public const SCHEMA_TYPES = ['None' => 'None', 'WebPage' => 'WebPage', 'Article' => 'Article', 'Service' => 'Service', 'FAQPage' => 'FAQPage',
        'WebApplication' => 'WebApplication', 'CollectionPage' => 'CollectionPage', 'AboutPage' => 'AboutPage', 'ContactPage' => 'ContactPage'];

    public const ARTICLE_SCHEMA_TYPES = ['NewsArticle' => 'NewsArticle', 'Article' => 'Article', 'BlogPosting' => 'BlogPosting', 'Review' => 'Review', 'None' => 'None'];

    /** @param array<string,string>|null $schemaTypes allowed schema types (defaults to the page list) */
    public static function rules(?array $schemaTypes = null): array
    {
        return [
            'meta_title' => 'nullable|string|max:120',
            'meta_description' => 'nullable|string|max:320',
            'meta_keywords' => 'nullable|string|max:300',
            'canonical_url' => 'nullable|url|max:500',
            'robots' => ['required', Rule::in(array_keys(self::ROBOTS))],
            'og_title' => 'nullable|string|max:160',
            'og_description' => 'nullable|string|max:320',
            'og_image' => 'nullable|string|max:500',
            'schema_type' => ['required', Rule::in(array_keys($schemaTypes ?? self::SCHEMA_TYPES))],
            'schema_json' => 'nullable|string|max:20000',
            'faq' => 'nullable|array|max:50',
            'faq.*.q' => 'nullable|string|max:300',
            'faq.*.a' => 'nullable|string|max:3000',
        ];
    }

    /** Error message when the custom JSON-LD is not valid JSON, otherwise null. */
    public static function schemaError(array $d): ?string
    {
        if (! filled($d['schema_json'] ?? null)) return null;
        json_decode($d['schema_json']);

        return json_last_error() === JSON_ERROR_NONE ? null : 'Custom schema is not valid JSON: '.json_last_error_msg();
    }

    /** The SEO columns ready to fill() onto a model, with empty FAQ rows dropped. */
    public static function attributes(array $d): array
    {
        $faq = collect($d['faq'] ?? [])->filter(fn ($f) => filled($f['q'] ?? null) && filled($f['a'] ?? null))
            ->map(fn ($f) => ['q' => trim($f['q']), 'a' => trim($f['a'])])->values()->all();

        return [
            'meta_title' => $d['meta_title'] ?? null, 'meta_description' => $d['meta_description'] ?? null, 'meta_keywords' => $d['meta_keywords'] ?? null,
            'canonical_url' => $d['canonical_url'] ?? null, 'robots' => $d['robots'],
            'og_title' => $d['og_title'] ?? null, 'og_description' => $d['og_description'] ?? null, 'og_image' => $d['og_image'] ?? null,
            'schema_type' => $d['schema_type'], 'schema_json' => $d['schema_json'] ?? null, 'faq' => $faq,
        ];
    }
}
