{{-- Lead form + filter panel. Expects: $filters, $clear (reset url). Inputs of the form may live elsewhere via form="flt". --}}
<aside class="fx-side">
    @include('site.partials.lead-form')

    <form class="fx-filters" id="flt" method="GET" action="{{ $clear }}">
        <div class="fx-head"><h3>Filters</h3>@if(request()->hasAny(array_merge(['q'], collect($filters)->pluck('key')->all())))<a href="{{ $clear }}">Clear all</a>@endif</div>
        @foreach($filters as $g)
            @php($sel = (array) request($g['key'], []))
            <details class="fx-group" @if($loop->index < 4 || $sel) open @endif>
                <summary>{{ $g['title'] }}</summary>
                <div class="fx-opts {{ count($g['options']) > 8 ? 'scroll' : '' }}">
                    @forelse($g['options'] as $o)
                        <label>
                            <input type="{{ ($g['radio'] ?? false) ? 'radio' : 'checkbox' }}" name="{{ $g['key'] }}{{ ($g['radio'] ?? false) ? '' : '[]' }}"
                                value="{{ $o['value'] }}" @checked(in_array((string) $o['value'], array_map('strval', $sel), true))>
                            <span>{{ $o['label'] }}</span>
                            @if(isset($o['count']))<em>{{ $o['count'] }}</em>@endif
                        </label>
                    @empty
                        <p class="fx-none">Nothing to filter yet.</p>
                    @endforelse
                </div>
            </details>
        @endforeach
        <noscript><button type="submit" class="fx-apply">Apply filters</button></noscript>
    </form>
    @isset($adPage)@include('site.partials.ad-vertical', ['page' => $adPage])@endisset
</aside>
<script>
    (() => {
        const f = document.getElementById('flt');
        if (!f || f.dataset.bound) return;
        f.dataset.bound = 1;
        if (innerWidth < 900) f.querySelectorAll('details').forEach(d => { if (!d.querySelector('input:checked')) d.open = false; });
        document.querySelectorAll('[form="flt"][data-auto]').forEach(e => e.addEventListener('change', () => f.requestSubmit()));
        f.addEventListener('change', () => f.requestSubmit());
        // drop empty fields so URLs stay clean
        f.addEventListener('formdata', e => { for (const [k, v] of [...e.formData]) if (v === '') e.formData.delete(k); });
    })();
</script>
