@extends('site.layout')

@push('head')
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => \App\Models\Setting::get('site.name'), 'url' => url('/'), 'potentialAction' => ['@type' => 'SearchAction', 'target' => route('search').'?q={q}', 'query-input' => 'required name=q']], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<section class="hero"><div class="container">
  <div>
    <span class="tag" style="background:rgba(255,255,255,.12);color:#fff">AI-powered · Updated every few hours</span>
    <h1 style="margin-top:14px">Find your next car with <span>India's smartest</span> auto guide.</h1>
    <p class="lead">Fresh car news, honest reviews, videos and a marketplace of used cars — and an AI assistant you can simply talk to.</p>
    <form class="ask-box" action="{{ route('assistant') }}" method="GET">
      <input name="ask" placeholder="Ask anything… “Best automatic SUV under ₹15 lakh?”" aria-label="Ask the AI assistant" required>
      <button class="btn btn-red"><i class="ti ti-sparkles"></i> Ask AI</button>
    </form>
    <div class="chips">
      <span class="chip" data-ask="Suggest a family car under 10 lakh">Family car under ₹10L</span>
      <span class="chip" data-ask="Which electric cars are best for city driving?">Best EVs for city</span>
      <span class="chip" data-ask="What should I check before buying a used car?">Used car checklist</span>
      <span class="chip" data-ask="Show me the latest car launches">Latest launches</span>
    </div>
  </div>
  @if ($hero)
  <a href="{{ $hero->url }}" class="hero-card">
    <img src="{{ $hero->image_url }}" alt="{{ $hero->title }}">
    <div class="in"><span class="tag" style="background:var(--red);color:#fff">Top story</span><h3 style="margin-top:10px">{{ \Illuminate\Support\Str::limit($hero->title, 100) }}</h3>
      <div class="small" style="color:#c9c9d6">{{ $hero->published_at?->diffForHumans() }}</div></div>
  </a>
  @endif
</div></section>

@if ($side->isNotEmpty())
<section class="block"><div class="container">
  <div class="sec-head"><h2>Latest news</h2><a href="{{ route('news.index') }}" class="link-more">All news →</a></div>
  <div class="feature">
    <div class="grid g2" style="grid-template-columns:1fr 1fr;grid-column:1/-1">
      @foreach ($side as $a)@include('site.partials.cards', ['type' => 'article', 'item' => $a])@endforeach
    </div>
  </div>
</div></section>
@endif

@if ($newCars->isNotEmpty())
<section class="block"><div class="container">
  <div class="sec-head"><h2>New launches & facelifts</h2><a href="{{ route('newcars.index') }}" class="link-more">All new cars →</a></div>
  <div class="grid g4">@foreach ($newCars as $c)@include('site.partials.cards', ['type' => 'newcar', 'item' => $c])@endforeach</div>
</div></section>
@endif

<section class="block"><div class="container">
  <div class="sec-head"><h2>Used cars</h2><a href="{{ route('cars.index') }}" class="link-more">Browse all cars →</a></div>
  @if ($listings->isEmpty())<div class="empty">Listings are coming soon.</div>@else
  <div class="grid g3">@foreach ($listings as $l)@include('site.partials.cards', ['type' => 'car', 'item' => $l])@endforeach</div>@endif
</div></section>

@if ($videos->isNotEmpty())
<section class="block"><div class="container">
  <div class="sec-head"><h2>Watch</h2><a href="{{ route('videos.index') }}" class="link-more">All videos →</a></div>
  <div class="grid g4">@foreach ($videos as $v)@include('site.partials.cards', ['type' => 'video', 'item' => $v])@endforeach</div>
</div></section>
@endif

@if ($more->isNotEmpty())
<section class="block"><div class="container">
  <div class="sec-head"><h2>More stories</h2></div>
  <div class="grid g4">@foreach ($more as $a)@include('site.partials.cards', ['type' => 'article', 'item' => $a])@endforeach</div>
</div></section>
@endif

<div class="container"><div class="cta">
  <div><h2>Want to sell your car?</h2><p>Tell us about it once — our team calls you back with the best offer.</p></div>
  <a href="{{ route('sell') }}" class="btn">Get an offer <i class="ti ti-arrow-right"></i></a>
</div></div>
@endsection
