@extends('site.layout')
@section('title', 'Car Videos & Reviews | '.\App\Models\Setting::get('site.name'))
@section('description', 'Watch the latest car reviews, launches and comparisons — streamed from YouTube.')

@section('content')
@include('site.partials.crumb', ['title' => 'Videos', 'trail' => []])
@php($hasAd = \App\Models\HomeSetting::current()->adsFor('vertical', 'videos')->isNotEmpty())
<div class="w {{ $hasAd ? 'ad-layout' : '' }}" style="padding-top:34px">
  <div>
    @if ($videos->isEmpty())<div class="empty"><h3>No videos yet</h3></div>
    @else<div class="grid">@foreach ($videos as $v)@include('site.partials.cards', ['type' => 'video', 'item' => $v])@endforeach</div>@endif
    {{ $videos->links('vendor.pagination.site') }}
  </div>
  @if ($hasAd)<aside>@include('site.partials.ad-vertical', ['page' => 'videos'])</aside>@endif
</div>
@endsection
