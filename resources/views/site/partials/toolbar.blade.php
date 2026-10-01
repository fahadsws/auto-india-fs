{{-- Search box (+ optional sort) and the chips of every applied filter. Expects: $filters, $clear, $placeholder, optional $sorts. --}}
@php
    $chips = [];
    foreach ($filters as $g) {
        $picked = array_map('strval', (array) request($g['key'], []));
        foreach ($g['options'] as $o) {
            if (! in_array((string) $o['value'], $picked, true)) continue;
            $left = array_values(array_diff($picked, [(string) $o['value']]));
            $chips[] = ['label' => $o['label'], 'group' => $g['title'], 'url' => request()->fullUrlWithQuery([$g['key'] => ($g['radio'] ?? false) || ! $left ? null : $left, 'page' => null])];
        }
    }
    if (filled(request('q'))) {
        $chips[] = ['label' => '“'.request('q').'”', 'group' => 'Search', 'url' => request()->fullUrlWithQuery(['q' => null, 'page' => null])];
    }
    $keys = array_merge(['q', 'sort'], collect($filters)->pluck('key')->all());
@endphp
<div class="fx-search">
    <label class="fx-sbox">
        <i class="ti ti-search" aria-hidden="true"></i>
        <input type="search" name="q" form="flt" value="{{ request('q') }}" placeholder="{{ $placeholder }}" aria-label="Search" autocomplete="off">
    </label>
    @isset($sorts)
        <select name="sort" form="flt" data-auto aria-label="Sort by">
            <option value="">Newest</option>
            @foreach($sorts as $k => $l)<option value="{{ $k }}" @selected(request('sort') === $k)>{{ $l }}</option>@endforeach
        </select>
    @endisset
    <button type="submit" form="flt">Search</button>
</div>
@if($chips)
    <div class="fx-chips" aria-label="Applied filters">
        @foreach($chips as $c)
            <a class="fx-chip" href="{{ $c['url'] }}" title="Remove {{ $c['group'] }} filter"><span>{{ $c['label'] }}</span><i class="ti ti-x" aria-hidden="true"></i></a>
        @endforeach
        <a class="fx-clear" href="{{ $clear }}">Clear all filters</a>
    </div>
@endif
