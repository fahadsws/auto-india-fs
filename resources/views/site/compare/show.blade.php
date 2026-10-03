@extends('site.layout')
@php
  $heading = $meta?->heading ?: $a->full_name.' vs '.$b->full_name;
  $winnerSlug = $meta?->winner?->slug;
@endphp
@section('title', ($meta?->meta_title ?: $heading.': Price, Specs & Comparison').' | '.\App\Models\Setting::get('site.name'))
@section('description', $meta?->meta_description ?: 'Compare '.$a->full_name.' and '.$b->full_name.' side by side on price, fuel, body type and specifications.')
@section('image', $a->hero_url)

@section('content')
@include('site.partials.crumb', ['title' => $heading, 'trail' => [['Compare cars', route('compare.index')]]])

<div class="w">
  <div class="cmp-heads">
    @foreach ([$a, $b] as $c)
      <div class="cmp-head {{ $winnerSlug === $c->slug ? 'win' : '' }}">
        @if ($winnerSlug === $c->slug)<span class="cmp-pick">Our pick</span>@endif
        <a href="{{ $c->url }}"><img src="{{ $c->hero_url }}" alt="{{ $c->full_name }}"></a>
        <a href="{{ $c->url }}" class="cmp-name">{{ $c->full_name }}</a>
        <div class="price">{{ $c->price_label }}</div>
      </div>
    @endforeach
    <span class="cmp-vs cmp-vs-mid" aria-hidden="true">VS</span>
  </div>

  <div class="cmp-box">
    <p class="cmp-label">Change cars <a href="{{ route('compare.show', $b->slug.'-vs-'.$a->slug) }}" class="cmp-swap"><i class="ti ti-arrows-exchange"></i> Swap</a></p>
    @include('site.compare._picker', ['cars' => $cars, 'a' => $a->slug, 'b' => $b->slug])
  </div>

  @foreach ($rows as [$section, $list])
    <h2 class="cmp-sec">{{ $section }}</h2>
    <table class="cmp-table">
      <thead><tr><th></th><th>{{ $a->full_name }}</th><th>{{ $b->full_name }}</th></tr></thead>
      <tbody>
        @foreach ($list as [$label, $va, $vb, $best])
          <tr><th scope="row">{{ $label }}</th><td class="{{ $best === 'a' ? 'best' : '' }}">{{ $va }}</td><td class="{{ $best === 'b' ? 'best' : '' }}">{{ $vb }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endforeach

  @if ($a->highlights || $b->highlights)
    <h2 class="cmp-sec">Highlights</h2>
    <div class="cmp-cols">
      @foreach ([$a, $b] as $c)
        <div class="aside-box"><h4>{{ $c->full_name }}</h4><ul class="prose">@forelse ($c->highlights ?? [] as $h)<li>{{ $h }}</li>@empty<li>—</li>@endforelse</ul></div>
      @endforeach
    </div>
  @endif

  @if ($meta?->verdict)
    <h2 class="cmp-sec">Our verdict</h2>
    <div class="aside-box"><div class="prose">{!! nl2br(e($meta->verdict)) !!}</div></div>
  @endif

  <p class="m" style="margin:18px 0 0">Details are compiled from published reports and may change. Confirm prices and specs with a dealer.</p>

  @if ($others->isNotEmpty())
    <div class="sh" style="margin-top:44px"><h2 class="h">More comparisons</h2><a href="{{ route('compare.index') }}">Compare other cars →</a></div>
    @include('site.compare._suggested', ['suggested' => $others])
  @endif
</div>
@endsection
