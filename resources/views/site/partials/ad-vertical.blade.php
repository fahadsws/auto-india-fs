{{-- Tall sidebar ads for a page. Expects: $page ('new'|'used'|'news'|'videos'). Renders nothing when no ad is placed there. --}}
@php($ads = \App\Models\HomeSetting::current()->adsFor('vertical', $page))
@foreach ($ads as $ad)
  <a class="ad-slot ad-v" href="{{ $ad['url'] ?: '#' }}" target="_blank" rel="sponsored noopener" aria-label="Advertisement">
    <img src="{{ $ad['image'] }}" alt="{{ $ad['title'] ?: 'Advertisement' }}" loading="lazy">
    <span class="ad-tag">Ad</span>
  </a>
@endforeach
