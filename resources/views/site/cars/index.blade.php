@extends('site.layout')
@section('title', 'Used Cars for Sale in India | '.\App\Models\Setting::get('site.name'))
@section('description', 'Browse verified used cars by brand, fuel, budget and city, and send an enquiry in one tap.')

@section('content')
<div class="phead"><div class="w"><h1 class="h">Used Cars</h1><p>{{ $listings->total() }} car(s) available. Enquire and our team gets back to you fast.</p></div></div>
<div class="w fx-layout">
  @include('site.partials.sidebar', ['filters' => $filters, 'clear' => route('cars.index'), 'adPage' => 'used'])
  <div class="fx-main">
    @include('site.partials.toolbar', ['filters' => $filters, 'clear' => route('cars.index'), 'placeholder' => 'Search brand, model or city', 'sorts' => ['price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'year_desc' => 'Newest model', 'km_asc' => 'Lowest km']])
    @if ($listings->isEmpty())<div class="empty"><h3>No cars match your filters</h3><p>Try widening your search — or <a href="#" class="red" data-ask="Help me find a used car">ask our AI</a>.</p></div>
    @else<div class="grid">@foreach ($listings as $l)@include('site.partials.cards', ['type' => 'car', 'item' => $l])@endforeach</div>@endif
    {{ $listings->links('vendor.pagination.site') }}
  </div>
</div>
@endsection
