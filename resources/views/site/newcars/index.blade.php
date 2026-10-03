@extends('site.layout')
@php $pl = $v['plural']; $lo = strtolower($pl); $idx = $v['route'].'.index'; @endphp
@section('title', "New $pl in India — Launches, Prices & Updates | ".\App\Models\Setting::get('site.name'))
@section('description', "Every new $v[label] Launch in India with prices, specs, images and the latest news — updated automatically as stories break.")
@section('content')
@include('site.partials.crumb', ['title' => 'New '.$lo, 'trail' => []])
<div class="w fx-layout">
  @include('site.partials.sidebar', ['filters' => $filters, 'clear' => route($idx), 'adPage' => 'new'])
  <div class="fx-main">
    @include('site.partials.toolbar', ['filters' => $filters, 'clear' => route($idx), 'placeholder' => 'Search brand or model'])
    <div class="cat-pills">
      <a href="{{ route($idx) }}" class="pill {{ !request('status')?'on':'' }}">All</a>
      @foreach(['upcoming'=>'Upcoming','launched'=>'Just launched','facelift'=>'Facelifts'] as $k=>$l)<a href="{{ request()->fullUrlWithQuery(['status'=>$k,'page'=>null]) }}" class="pill {{ request('status')===$k?'on':'' }}">{{ $l }}</a>@endforeach
    </div>
    @if($cars->isEmpty())<div class="empty"><h3>No models match</h3><p>Try removing a filter, or check back as new launches are published.</p></div>
    @else<div class="grid">@foreach($cars as $c)@include('site.partials.cards',['type'=>'newcar','item'=>$c])@endforeach</div>@endif
    {{ $cars->links('vendor.pagination.site') }}
  </div>
</div>
@endsection
