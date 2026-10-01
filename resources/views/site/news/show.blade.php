@extends('site.layout')
@section('title', ($article->meta_title ?: $article->title).' | '.\App\Models\Setting::get('site.name'))
@section('description', $article->meta_description ?: $article->excerpt)
@section('image', $article->image_url)
@section('og_type', 'article')

@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'NewsArticle', 'headline' => $article->title, 'image' => [$article->image_url], 'datePublished' => $article->published_at?->toIso8601String(), 'dateModified' => $article->updated_at->toIso8601String(), 'author' => ['@type' => 'Organization', 'name' => $article->author?->name ?? \App\Models\Setting::get('site.name')], 'publisher' => ['@type' => 'Organization', 'name' => \App\Models\Setting::get('site.name')], 'mainEntityOfPage' => $article->url, 'keywords' => $article->tags ? implode(', ', $article->tags) : null, 'isAccessibleForFree' => true], JSON_UNESCAPED_SLASHES) !!}</script>
@if ($article->faq)<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($article->faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()], JSON_UNESCAPED_SLASHES) !!}</script>@endif
@endpush

@section('content')
<div class="w"><div class="article-wrap">
  <article class="article">
    @if ($article->category)<a href="{{ route('news.category', $article->category) }}" class="tag">{{ $article->category->name }}</a>@endif
    <h1 class="h" style="margin-top:12px;font-size:clamp(28px,4vw,42px)">{{ $article->title }}</h1>
    <div class="m"><i class="ti ti-clock"></i> {{ $article->published_at?->format('d M Y, h:i A') }} · <i class="ti ti-user"></i> {{ $article->author?->name ?? 'Automobil India Desk' }} · <i class="ti ti-eye"></i> {{ number_format($article->views) }} views</div>
    @if ($article->image_path)<img class="cover" src="{{ $article->image_url }}" alt="{{ $article->title }}">@endif
    @if ($article->tldr)<div class="aside-box" style="border-left:5px solid var(--red)"><strong style="text-transform:uppercase;font-size:.75rem;letter-spacing:.06em;color:var(--red)">The short version</strong><ul style="margin:8px 0 0;padding-left:1.2em">@foreach ($article->tldr as $t)<li>{{ $t }}</li>@endforeach</ul></div>
    @elseif ($article->excerpt)<p style="font-size:1.2rem;font-weight:600;color:var(--ink2)">{{ $article->excerpt }}</p>@endif
    <div class="prose">{!! $article->body !!}</div>

    @foreach ($article->vehicleModels as $cm)<div style="margin-top:26px"><a href="{{ $cm->url }}" class="aside-box" style="display:flex;gap:14px;align-items:center;padding:12px"><img src="{{ $cm->hero_url }}" alt="" style="width:110px;height:76px;object-fit:cover;border-radius:10px"><div><span class="tag">{{ $cm->status_label }}</span><h4 style="margin:6px 0 2px">{{ $cm->full_name }}</h4><span class="m">{{ $cm->price_label }} · full details, specs &amp; images →</span></div></a></div>@endforeach

    @if ($article->faq)<h2 style="margin-top:34px">Frequently asked questions</h2>@foreach ($article->faq as $f)<details class="aside-box" style="padding:14px 18px;margin-bottom:10px"><summary style="font-weight:700;cursor:pointer">{{ $f['q'] }}</summary><p class="m" style="margin:10px 0 0">{{ $f['a'] }}</p></details>@endforeach @endif

    @foreach ($article->videos as $v)
      <div style="margin-top:30px"><h3><i class="ti ti-brand-youtube" style="color:var(--red)"></i> Watch: {{ \Illuminate\Support\Str::limit($v->title, 80) }}</h3>
        <div class="video-frame"><iframe src="{{ $v->embed_url }}" title="{{ $v->title }}" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div></div>
    @endforeach

    @if ($article->source_links)<p class="m" style="margin-top:26px">Reporting compiled from: @foreach ($article->source_links as $sl)<a href="{{ $sl['url'] }}" target="_blank" rel="noopener nofollow" style="color:var(--red)">{{ $sl['outlet'] }}</a>{{ ! $loop->last ? ', ' : '' }}@endforeach. Written by the {{ \App\Models\Setting::get('site.name') }} desk with AI assistance and reviewed for accuracy.</p>
    @elseif ($article->source_url)<p class="m" style="margin-top:26px">Originally reported by <a href="{{ $article->source_url }}" target="_blank" rel="noopener nofollow" style="color:var(--red)">{{ preg_replace('/^www\./', '', parse_url($article->source_url, PHP_URL_HOST)) }}</a>. This article was written with AI assistance.</p>@endif

    <div class="share">
      <a class="btn-ghost btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text={{ urlencode($article->title.' '.$article->url) }}"><i class="ti ti-brand-whatsapp"></i> WhatsApp</a>
      <a class="btn-ghost btn-sm" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url={{ urlencode($article->url) }}&text={{ urlencode($article->title) }}"><i class="ti ti-brand-x"></i> Post</a>
      <a class="btn-ghost btn-sm" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($article->url) }}"><i class="ti ti-brand-facebook"></i> Share</a>
    </div>
  </article>

  <aside>
    <div class="aside-box" style="background:linear-gradient(135deg,var(--red),#8f0d18);color:#fff;border:0">
      <h4>Have a question about this?</h4><p style="margin:0 0 14px;opacity:.92">Ask our AI assistant — it knows our cars, news and videos.</p>
      <button class="btn-block" style="background:#fff;color:var(--red2);height:50px;border:0;border-radius:8px;font-weight:700;cursor:pointer" data-ask="Tell me more about: {{ $article->title }}"><i class="ti ti-sparkles"></i> Ask AI</button>
    </div>
    @if ($related->isNotEmpty())
    <div class="aside-box"><h4>Related stories</h4>
      <ul class="li">@foreach ($related as $r)<li><a href="{{ $r->url }}"><img src="{{ $r->image_url }}" alt="" loading="lazy"><div><p class="t">{{ \Illuminate\Support\Str::limit($r->title, 70) }}</p><span class="m">{{ $r->published_at?->diffForHumans() }}</span></div></a></li>@endforeach</ul>
    </div>
    @endif
  </aside>
</div></div>
@endsection
