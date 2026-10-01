{{-- Wide ad strip. Expects: $ads (Collection of ad arrays). Shows the first active one; rotates client-side when several exist. --}}
@if ($ads->isNotEmpty())
  <div class="w ad-h" aria-label="Advertisement">
    @foreach ($ads as $ad)
      <a class="ad-slot" href="{{ $ad['url'] ?: '#' }}" target="_blank" rel="sponsored noopener" @if (! $loop->first) hidden @endif>
        <img src="{{ $ad['image'] }}" alt="{{ $ad['title'] ?: 'Advertisement' }}" loading="lazy">
        <span class="ad-tag">Ad</span>
      </a>
    @endforeach
  </div>
  @if ($ads->count() > 1)
    <script>
      (() => {
        const s = [...document.currentScript.previousElementSibling.querySelectorAll('.ad-slot')];
        let i = 0;
        setInterval(() => { s[i].hidden = true; i = (i + 1) % s.length; s[i].hidden = false; }, 6000);
      })();
    </script>
  @endif
@endif
