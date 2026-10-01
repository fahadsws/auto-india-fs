@extends('site.layout')
@section('title', ($q ? 'Search: '.$q : 'Search').' | '.\App\Models\Setting::get('site.name'))

@section('content')
<div class="phead"><div class="w"><h1 class="h">Search</h1>
  <form action="{{ route('search') }}" style="display:flex;gap:10px;max-width:560px;margin-top:16px"><input name="q" value="{{ $q }}" placeholder="Search cars, news, videos…" autofocus style="flex:1;min-width:0;height:54px;border:0;border-radius:8px;padding:0 18px;background:#fff"><button class="go">Search</button></form></div></div>
<div class="w" style="padding-top:28px">
  @if ($q && $articles->isEmpty() && $listings->isEmpty() && $videos->isEmpty() && $vehicleModels->isEmpty())
    <div class="empty"><h3>Nothing found for “{{ $q }}”</h3><p>Ask our AI instead — it can find things even if the words don't match exactly.</p><button class="go" data-ask="{{ $q }}"><i class="ti ti-sparkles"></i> Ask AI</button></div>
  @endif
  @if ($listings->isNotEmpty())<div class="sh" style="margin-top:30px"><h2 class="h">Cars</h2></div><div class="grid">@foreach ($listings as $l)@include('site.partials.cards', ['type' => 'car', 'item' => $l])@endforeach</div>@endif
  @if ($vehicleModels->isNotEmpty())<div class="sh" style="margin-top:30px"><h2 class="h">New cars</h2></div><div class="grid">@foreach ($vehicleModels as $car)<a class="card" href="{{ $car->url }}"><img src="{{ $car->hero_url }}" alt="{{ $car->full_name }}" loading="lazy"><b>{{ $car->full_name }}</b><span class="m">{{ $car->price_label }}</span></a>@endforeach</div>@endif
  @if ($articles->isNotEmpty())<div class="sh" style="margin-top:40px"><h2 class="h">News</h2></div><div class="grid">@foreach ($articles as $a)@include('site.partials.cards', ['type' => 'article', 'item' => $a])@endforeach</div>@endif
  @if ($videos->isNotEmpty())<div class="sh" style="margin-top:40px"><h2 class="h">Videos</h2></div><div class="grid">@foreach ($videos as $v)@include('site.partials.cards', ['type' => 'video', 'item' => $v])@endforeach</div>@endif
</div>
@endsection
