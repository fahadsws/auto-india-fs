@extends('site.layout')
@section('title', 'Compare Cars in India — Side-by-Side Price & Specs | '.\App\Models\Setting::get('site.name'))
@section('description', 'Compare any two cars side by side: price, fuel, body type and full specifications, plus popular comparisons picked by our editors.')

@section('content')
@include('site.partials.crumb', ['title' => 'Compare cars', 'trail' => []])
<div class="w">
  <div class="cmp-box">
    @if ($error)<p class="cmp-err">{{ $error }}</p>@endif
    @include('site.compare._picker', ['cars' => $cars, 'a' => $selectedA, 'b' => $selectedB])
  </div>

  @if ($suggested->isNotEmpty())
    <div class="sh"><h2 class="h">Suggested comparisons</h2></div>
    @include('site.compare._suggested', ['suggested' => $suggested])
  @endif
</div>
@endsection
