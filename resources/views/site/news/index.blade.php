@extends('site.layout')
@section('title', ($category ? $category->name.' News' : 'Latest Car News & Reviews').' | '.\App\Models\Setting::get('site.name'))
@section('description', 'Fresh car news, launches, reviews and buying guides from India’s automotive world.')

@section('content')
<div class="phead"><div class="w"><h1 class="h">{{ $category ? $category->name : 'Car News' }}</h1><p>{{ $q ? 'Results for “'.$q.'”' : 'Launches, reviews, buying guides and industry updates.' }}</p></div></div>
<div class="w fx-layout">
  @include('site.partials.sidebar', ['filters' => $filters, 'clear' => $category ? route('news.category', $category) : route('news.index'), 'adPage' => 'news'])
  <div class="fx-main">
    @include('site.partials.toolbar', ['filters' => $filters, 'clear' => $category ? route('news.category', $category) : route('news.index'), 'placeholder' => 'Search news'])
    <div class="cat-pills">
      <a href="{{ route('news.index') }}" class="pill {{ ! $category ? 'on' : '' }}">All</a>
      @foreach ($navCategories as $c)<a href="{{ route('news.category', $c) }}" class="pill {{ $category?->id === $c->id ? 'on' : '' }}">{{ $c->name }}</a>@endforeach
    </div>
    @if ($articles->isEmpty())<div class="empty"><h3>No articles found</h3><p>Try removing a filter — new stories are published throughout the day.</p></div>
    @else<div class="grid">@foreach ($articles as $a)@include('site.partials.cards', ['type' => 'article', 'item' => $a])@endforeach</div>@endif
    {{ $articles->links('vendor.pagination.site') }}
  </div>
</div>
@endsection
