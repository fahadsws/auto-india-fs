{{-- "Latest" news box. Expects: $articles (Collection of Article). --}}
@if ($articles->isNotEmpty())
<div class="aside-box ln-box"><h4>Latest</h4>
  <ul class="ln">@foreach ($articles as $a)
    <li><a href="{{ $a->url }}"><img src="{{ $a->image_url }}" alt="" loading="lazy"><div><p class="t">{{ \Illuminate\Support\Str::limit($a->title, 70) }}</p><span class="m">{{ $a->published_at?->diffForHumans() }}</span></div></a></li>
  @endforeach</ul>
  <a href="{{ route('news.index') }}" class="ln-more">All news <i class="ti ti-arrow-right"></i></a>
</div>
@endif
