@extends('site.layout')
@section('title', ($car->meta_title ?: $car->full_name.' — Price, Specs & Latest News').' | '.\App\Models\Setting::get('site.name'))
@section('description', $car->meta_description ?: $car->tagline ?: $car->full_name.' price, specs and latest updates.')
@section('image', $car->hero_url)

@php $gallery = $car->galleryUrls(); @endphp
@push('head')
<style>
  .car-photo button,.car-gallery button{display:block;width:100%;padding:0;border:0;background:transparent;cursor:zoom-in}
  .car-photo img,.car-gallery img{display:block}
  .car-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:12px}
  .car-gallery button:focus-visible,.car-photo button:focus-visible{outline:3px solid var(--red);outline-offset:3px;border-radius:10px}
  .image-viewer[hidden]{display:none}
  .image-viewer{position:fixed;z-index:1000;inset:0;background:rgba(8,10,16,.94);display:flex;align-items:center;justify-content:center;padding:32px}
  .image-viewer__image{max-width:min(1100px,90vw);max-height:82vh;object-fit:contain;border-radius:8px}
  .image-viewer__close,.image-viewer__nav{position:absolute;border:0;color:#fff;background:rgba(255,255,255,.14);cursor:pointer;border-radius:50%;display:grid;place-items:center}
  .image-viewer__close{top:18px;right:22px;width:44px;height:44px;font-size:28px}.image-viewer__nav{top:50%;transform:translateY(-50%);width:48px;height:48px;font-size:32px}.image-viewer__prev{left:22px}.image-viewer__next{right:22px}
  .image-viewer__count{position:absolute;bottom:18px;left:50%;transform:translateX(-50%);color:#fff;font-size:14px}
  @media(max-width:600px){.image-viewer{padding:18px}.image-viewer__image{max-width:96vw;max-height:78vh}.image-viewer__nav{width:40px;height:40px}.image-viewer__prev{left:8px}.image-viewer__next{right:8px}}
</style>
<script type="application/ld+json">{!! json_encode(array_filter(['@context' => 'https://schema.org', '@type' => config("vehicles.{$car->vehicle_type}.schema", 'Car'), 'name' => $car->full_name, 'brand' => ['@type' => 'Brand', 'name' => $car->brand], 'model' => $car->name, 'bodyType' => $car->body_type, 'image' => $gallery ?: [$car->hero_url], 'description' => $car->tagline, 'dateModified' => $car->updated_at->toIso8601String(), 'offers' => $car->price_min ? ['@type' => 'AggregateOffer', 'priceCurrency' => 'INR', 'lowPrice' => $car->price_min, 'highPrice' => $car->price_max ?: $car->price_min] : null]), JSON_UNESCAPED_SLASHES) !!}</script>
@if ($car->faq)<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($car->faq)->map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()], JSON_UNESCAPED_SLASHES) !!}</script>@endif
@endpush

@section('content')
<div class="phead"><div class="w">
  <span class="tag">{{ $car->status_label }}@if($car->latest_event_at) · updated {{ $car->latest_event_at->diffForHumans() }}@endif</span>
  <h1 class="h" style="margin-top:12px">{{ $car->full_name }}</h1><p>{{ $car->tagline ?: $car->price_label }}</p>
</div></div>

<div class="w"><div class="car-wrap">
  <div>
    <div class="car-photo"><button type="button" class="car-image-trigger" data-image-index="0" aria-label="Open {{ $car->full_name }} photo"><img src="{{ $car->hero_url }}" alt="{{ $car->full_name }}"></button></div>
    @if (count($gallery) > 1)
      <div class="car-gallery">
        @foreach (array_slice($gallery, 1, 8) as $i => $g)<button type="button" class="car-image-trigger" data-image-index="{{ $i + 1 }}" aria-label="Open {{ $car->full_name }} photo {{ $i + 2 }}"><img src="{{ $g }}" alt="{{ $car->full_name }} photo" loading="lazy" style="border-radius:10px;aspect-ratio:4/3;object-fit:cover;width:100%"></button>@endforeach
      </div>
    @endif

    @if ($car->highlights)<h2 style="margin-top:34px">Highlights</h2><ul class="prose">@foreach ($car->highlights as $h)<li>{{ $h }}</li>@endforeach</ul>@endif
    @if ($car->overview)<div class="prose" style="margin-top:20px">{!! $car->overview !!}</div>@endif

    @if ($car->faq)
      <h2 style="margin-top:34px">Frequently asked questions</h2>
      @foreach ($car->faq as $f)<details class="aside-box" style="padding:14px 18px;margin-bottom:10px"><summary style="font-weight:700;cursor:pointer">{{ $f['q'] }}</summary><p class="m" style="margin:10px 0 0">{{ $f['a'] }}</p></details>@endforeach
    @endif

    @if ($news->isNotEmpty())
      <div class="sh" style="margin-top:40px"><h2 class="h">Latest {{ $car->name }} news</h2></div>
      <div class="grid">@foreach ($news as $a)@include('site.partials.cards', ['type' => 'article', 'item' => $a])@endforeach</div>
    @endif
  </div>

  <div>
    <div class="aside-box sticky-form">
      <div class="price" style="font-size:1.5rem">{{ $car->price_label }}</div>
      <table class="spec-table">
        @foreach (array_filter(['Status' => $car->status_label, 'Body type' => $car->body_type, 'Fuel' => $car->fuel_types ? implode(' / ', $car->fuel_types) : null, 'Launch date' => $car->launch_date?->format('d M Y')]) as $k => $v)<tr><td>{{ $k }}</td><td>{{ $v }}</td></tr>@endforeach
        @foreach ($car->specs ?? [] as $k => $v)<tr><td>{{ $k }}</td><td>{{ $v }}</td></tr>@endforeach
      </table>
      <button class="go btn-block" data-ask="Tell me about the {{ $car->full_name }}: price, variants and who it rivals"><i class="ti ti-sparkles"></i> Ask AI about this {{ strtolower(config("vehicles.{$car->vehicle_type}.label", 'car')) }}</button>
      <a href="{{ route('contact') }}" class="btn-ghost btn-block" style="margin-top:10px">Get a quote</a>
      @if (empty($preview) && $car->vehicle_type === 'car')<a href="{{ route('compare.index', ['a' => $car->slug]) }}" class="btn-ghost btn-block" style="margin-top:10px"><i class="ti ti-arrows-diff"></i> Compare with another car</a>@endif
      <p class="m" style="margin:14px 0 0">Details are compiled from published reports and may change. Confirm prices and specs with a dealer.</p>
    </div>
  </div>
</div>

@if ($car->vehicle_type === 'car' && ! empty($rivals) && $rivals->isNotEmpty())
  <div class="sh"><h2 class="h">Compare {{ $car->name }} with similar cars</h2><a href="{{ route('compare.index', ['a' => $car->slug]) }}">Compare any car →</a></div>
  <div class="vs">
    @foreach ($rivals as $rv)
      <a href="{{ route('compare.show', $car->slug.'-vs-'.$rv->slug) }}">
        <div><img src="{{ $car->hero_url }}" alt="{{ $car->full_name }}" loading="lazy"><b>{{ $car->full_name }}</b><span class="m">{{ $car->price_label }}</span></div><i>VS</i>
        <div><img src="{{ $rv->hero_url }}" alt="{{ $rv->full_name }}" loading="lazy"><b>{{ $rv->full_name }}</b><span class="m">{{ $rv->price_label }}</span></div><span class="cta">Compare now</span>
      </a>
    @endforeach
  </div>
@endif

@if ($used->isNotEmpty())
  <div class="sh"><h2 class="h">Used {{ $car->name }} for sale</h2><a href="{{ route('cars.index', ['q' => $car->name]) }}">See all →</a></div>
  <div class="grid">@foreach ($used as $l)@include('site.partials.cards', ['type' => 'car', 'item' => $l])@endforeach</div>
@endif
@if ($videos->isNotEmpty())
  <div class="sh" style="margin-top:40px"><h2 class="h">Videos</h2></div>
  <div class="grid">@foreach ($videos as $v)@include('site.partials.cards', ['type' => 'video', 'item' => $v])@endforeach</div>
@endif
</div>
<div class="image-viewer" id="image-viewer" hidden role="dialog" aria-modal="true" aria-label="Photo viewer">
  <button type="button" class="image-viewer__close" aria-label="Close photo viewer">&times;</button>
  <button type="button" class="image-viewer__nav image-viewer__prev" aria-label="Previous photo">&#8249;</button>
  <img class="image-viewer__image" alt="">
  <button type="button" class="image-viewer__nav image-viewer__next" aria-label="Next photo">&#8250;</button>
  <span class="image-viewer__count" aria-live="polite"></span>
</div>
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const viewer = document.querySelector('#image-viewer');
  const triggers = [...document.querySelectorAll('.car-image-trigger')];
  if (!viewer || !triggers.length) return;
  const image = viewer.querySelector('.image-viewer__image'), count = viewer.querySelector('.image-viewer__count');
  const urls = triggers.map(button => button.querySelector('img').currentSrc || button.querySelector('img').src);
  let index = 0, previousFocus;
  const render = () => { image.src = urls[index]; image.alt = `${@json($car->full_name)} photo ${index + 1}`; count.textContent = `${index + 1} / ${urls.length}`; };
  const close = () => { viewer.hidden = true; document.body.style.overflow = ''; previousFocus?.focus(); };
  triggers.forEach((button, i) => button.addEventListener('click', () => { index = i; previousFocus = button; render(); viewer.hidden = false; document.body.style.overflow = 'hidden'; viewer.querySelector('.image-viewer__close').focus(); }));
  viewer.querySelector('.image-viewer__close').addEventListener('click', close);
  viewer.addEventListener('click', event => { if (event.target === viewer) close(); });
  viewer.querySelector('.image-viewer__prev').addEventListener('click', () => { index = (index - 1 + urls.length) % urls.length; render(); });
  viewer.querySelector('.image-viewer__next').addEventListener('click', () => { index = (index + 1) % urls.length; render(); });
  document.addEventListener('keydown', event => { if (viewer.hidden) return; if (event.key === 'Escape') close(); if (event.key === 'ArrowLeft') viewer.querySelector('.image-viewer__prev').click(); if (event.key === 'ArrowRight') viewer.querySelector('.image-viewer__next').click(); });
});
</script>
@endpush
