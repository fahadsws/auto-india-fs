{{-- $suggested: Collection<CarComparison> (carA/carB loaded) --}}
<div class="vs">
  @foreach ($suggested as $s)
    <a href="{{ $s->url }}">
      <div><img src="{{ $s->carA->hero_url }}" alt="{{ $s->carA->full_name }}" loading="lazy"><b>{{ $s->carA->full_name }}</b><span class="m">{{ $s->carA->price_label }}</span></div><i>VS</i>
      <div><img src="{{ $s->carB->hero_url }}" alt="{{ $s->carB->full_name }}" loading="lazy"><b>{{ $s->carB->full_name }}</b><span class="m">{{ $s->carB->price_label }}</span></div><span class="cta">Compare now</span>
    </a>
  @endforeach
</div>
