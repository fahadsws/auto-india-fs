@extends('site.layout')
@php
  $siteName = \App\Models\Setting::get('site.name');
  $title = $page->meta_title ?: $page->title;
  $desc = $page->meta_description ?: ($page->excerpt ?: \Illuminate\Support\Str::limit(trim(strip_tags((string) $page->body)), 160, ''));
  $image = $page->og_image ?: $page->image_url;
  $faqItems = $page->faqItems();
  $robots = $preview ? 'noindex,nofollow' : $page->robots;
@endphp
@section('title', $title.' | '.$siteName)
@section('description', $desc)
@if ($image)@section('image', $image)@endif
@section('og_type', $page->schema_type === 'Article' ? 'article' : 'website')
@section('canonical', $page->canonical_url ?: $page->url)
@if ($robots !== 'index,follow')@section('robots', $robots)@endif
@if ($page->og_title)@section('og_title', $page->og_title)@endif
@if ($page->og_description)@section('og_description', $page->og_description)@endif

@push('head')
@include('site.partials.seo-jsonld', ['m' => $page, 'name' => $page->title, 'url' => $page->url, 'desc' => $desc, 'image' => $image, 'crumb' => $page->title])
@endpush

@section('content')
@if ($preview)<div class="alert alert-ok" style="margin:0;border-radius:0;text-align:center">Preview — this page is {{ $page->is_live ? 'live' : 'not published yet' }}.</div>@endif
<div class="phead"><div class="w"><h1 class="h">{{ $page->title }}</h1>@if ($page->excerpt)<p>{{ $page->excerpt }}</p>@endif</div></div>
@if ($page->show_ads && $homeSettings)@include('site.partials.ad-horizontal', ['ads' => $homeSettings->adsFor('horizontal')])@endif

@php
  $faqHtml = collect($faqItems)->map(fn ($f) => $f)->all();
  $sideOn = $hasSidebar && $page->template !== 'full' && ($page->show_lead || $page->show_ads || $latest->isNotEmpty());
@endphp
<div class="w">
  <div class="{{ $sideOn ? 'article-wrap' : '' }}" @unless ($sideOn) style="padding:36px 0" @endunless>
    <article class="article">
      @if ($page->image_url)<img class="cover" src="{{ $page->image_url }}" alt="{{ $page->title }}" style="width:100%;border-radius:16px;margin-bottom:22px">@endif
      <div class="prose">{!! $page->body !!}</div>
      @if ($faqHtml)
        <h2 style="margin-top:34px">Frequently asked questions</h2>
        @foreach ($faqHtml as $f)<details class="aside-box" style="padding:14px 18px;margin-bottom:10px"><summary style="font-weight:700;cursor:pointer">{{ $f['q'] }}</summary><p class="m" style="margin:10px 0 0">{!! nl2br(e($f['a'])) !!}</p></details>@endforeach
      @endif
      @if (! $sideOn && $hasSidebar)
        <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px;margin-top:34px;align-items:start">
          @if ($page->show_lead)<div>@include('site.partials.lead-form')</div>@endif
          @if ($page->show_news)<div>@include('site.partials.latest-news', ['articles' => $latest])</div>@endif
        </div>
      @endif
    </article>
    @if ($sideOn)
    <aside>
      @if ($page->show_lead)<div class="emi-lead">@include('site.partials.lead-form')</div>@endif
      @if ($page->show_ads)@include('site.partials.ad-vertical', ['page' => 'page'])@endif
      @if ($page->show_news)@include('site.partials.latest-news', ['articles' => $latest])@endif
    </aside>
    @endif
  </div>
</div>
@endsection
