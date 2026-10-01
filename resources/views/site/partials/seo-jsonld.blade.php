{{-- JSON-LD from an SEO source ($m: Page or SeoEntry). Expects: $m, $name, $url, $desc, $image (nullable), $crumb (nullable, adds BreadcrumbList). --}}
@php($flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
@php($siteName = \App\Models\Setting::get('site.name'))
@if (($m->schema_type ?? 'None') !== 'None')
<script type="application/ld+json">{!! json_encode(array_filter(['@context' => 'https://schema.org', '@type' => $m->schema_type === 'FAQPage' ? 'WebPage' : $m->schema_type,
  ($m->schema_type === 'Article' ? 'headline' : 'name') => $name, 'description' => $desc, 'url' => $url, 'image' => $image,
  'datePublished' => $m->schema_type === 'Article' ? $m->published_at?->toIso8601String() : null, 'dateModified' => $m->schema_type === 'Article' ? $m->updated_at?->toIso8601String() : null,
  'author' => $m->schema_type === 'Article' ? ['@type' => 'Organization', 'name' => $siteName] : null,
  'provider' => $m->schema_type === 'Service' ? ['@type' => 'Organization', 'name' => $siteName] : null]), $flags) !!}</script>
@endif
@if ($faqItems = $m->faqItems())
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faqItems)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->all()], $flags) !!}</script>
@endif
@if (! empty($crumb))
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')], ['@type' => 'ListItem', 'position' => 2, 'name' => $crumb, 'item' => $url]]], $flags) !!}</script>
@endif
@if (filled($m->schema_json) && ($custom = json_decode($m->schema_json, true)) !== null)
<script type="application/ld+json">{!! json_encode($custom, $flags) !!}</script>
@endif
