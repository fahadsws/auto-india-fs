{{-- Usage: @include('site.partials.cards', ['type' => 'article'|'car'|'video'|'newcar', 'item' => $model]) --}}
@if ($type === 'article')
  <a href="{{ $item->url }}">
    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
    @if ($item->category)<span class="tag">{{ $item->category->name }}</span>@endif
    <p class="t">{{ \Illuminate\Support\Str::limit($item->title, 90) }}</p>
    <p class="m">{{ $item->published_at?->diffForHumans() }}</p>
  </a>
@elseif ($type === 'car')
  <a href="{{ $item->url }}">
    <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
    @if ($item->year)<span class="tag">{{ $item->year }}</span>@endif
    <p class="t">{{ $item->title }}</p>
    <p class="price">{{ $item->price_label }}</p>
    <div class="specs">
      @if ($item->km_driven)<span>{{ number_format($item->km_driven) }} km</span>@endif
      @if ($item->fuel)<span>{{ $item->fuel }}</span>@endif
      @if ($item->transmission)<span>{{ $item->transmission }}</span>@endif
      @if ($item->owner)<span>{{ $item->owner }}</span>@endif
    </div>
    <p class="m">{{ $item->city ?: 'India' }}</p>
  </a>
@elseif ($type === 'video')
  <a href="{{ $item->url }}">
    <span class="thumb"><img src="{{ $item->thumbnail }}" alt="{{ $item->title }}" loading="lazy"><span class="play"><i class="ti ti-player-play-filled"></i></span></span>
    <p class="t">{{ \Illuminate\Support\Str::limit($item->title, 80) }}</p>
    <p class="m">{{ $item->channel }}</p>
  </a>
@elseif ($type === 'newcar')
  <a href="{{ $item->url }}">
    <img src="{{ $item->hero_url }}" alt="{{ $item->full_name }}" loading="lazy">
    <span class="tag">{{ $item->status_label }}</span>
    <p class="t">{{ $item->full_name }}</p>
    <p class="price">{{ $item->price_label }}</p>
    <p class="m">Updated {{ ($item->latest_event_at ?? $item->updated_at)->diffForHumans() }}</p>
  </a>
@endif
