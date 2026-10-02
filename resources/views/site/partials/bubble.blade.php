{{-- Glass bubble + sparkle. $cls = '' | 'aw-orb-sm' | 'aw-orb-md' --}}
<span class="aw-bubble {{ $cls ?? '' }}" aria-hidden="true">
  <span class="aw-orb">
    <i class="aw-streak"></i>
    <svg class="aw-spark" viewBox="0 0 28 28"><path d="M15 3c.7 5.4 2.8 7.5 8.2 8.2-5.4.7-7.5 2.8-8.2 8.2-.7-5.4-2.8-7.5-8.2-8.2C12.2 10.5 14.3 8.4 15 3z"/><path class="s2" d="M7.4 17.6c.4 2.6 1.4 3.6 4 4-2.6.4-3.6 1.4-4 4-.4-2.6-1.4-3.6-4-4 2.6-.4 3.6-1.4 4-4z"/></svg>
  </span>
  @foreach (['t1', 't2', 't3', 't4', 't5'] as $t)<svg class="aw-twink {{ $t }}" viewBox="0 0 24 24"><path d="M12 0c.8 7 5 11.2 12 12-7 .8-11.2 5-12 12-.8-7-5-11.2-12-12C7 11.2 11.2 7 12 0z"/></svg>@endforeach
</span>