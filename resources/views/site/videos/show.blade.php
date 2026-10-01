@extends('site.layout')
@section('title', $video->title.' | '.\App\Models\Setting::get('site.name'))
@section('description', \Illuminate\Support\Str::limit(strip_tags((string) $video->description), 160, ''))
@section('image', $video->thumbnail)

@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'VideoObject', 'name' => $video->title, 'description' => $video->description ?: $video->title, 'thumbnailUrl' => $video->thumbnail, 'uploadDate' => ($video->published_at ?? $video->created_at)->toIso8601String(), 'embedUrl' => $video->embed_url], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="w"><div class="article-wrap">
  <div>
    <div class="video-frame"><iframe src="{{ $video->embed_url }}?rel=0" title="{{ $video->title }}" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe></div>
    <h1 class="h" style="margin-top:20px;font-size:clamp(24px,3.2vw,34px)">{{ $video->title }}</h1>
    <p class="m"><i class="ti ti-brand-youtube" style="color:var(--red)"></i> {{ $video->channel }}</p>
    @if ($video->description)<p class="prose" style="font-size:1rem">{{ \Illuminate\Support\Str::limit($video->description, 500) }}</p>@endif
    @if ($articles->isNotEmpty())<h3 style="margin-top:30px">Read more</h3><div class="grid">@foreach ($articles as $a)@include('site.partials.cards', ['type' => 'article', 'item' => $a])@endforeach</div>@endif
  </div>
  <aside><div class="aside-box"><h4>More videos</h4>
    <ul class="li">@foreach ($more as $m)<li><a href="{{ $m->url }}"><img src="{{ $m->thumbnail }}" alt="" loading="lazy"><div><p class="t">{{ \Illuminate\Support\Str::limit($m->title, 60) }}</p><span class="m">{{ $m->channel }}</span></div></a></li>@endforeach</ul>
  </div></aside>
</div></div>
@endsection
