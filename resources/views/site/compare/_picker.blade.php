{{-- $cars (published CarModels w/ brandMaster), $a / $b = selected slugs --}}
@php $groups = $cars->groupBy(fn ($c) => $c->brandMaster?->name ?? 'Other'); @endphp
<form method="GET" action="{{ route('compare.index') }}" class="cmp-picker">
  @foreach (['a' => 'First car', 'b' => 'Second car'] as $k => $label)
    <label class="cmp-field"><span>{{ $label }}</span>
      <select name="{{ $k }}" required>
        <option value="">Choose a car</option>
        @foreach ($groups as $brand => $list)
          <optgroup label="{{ $brand }}">@foreach ($list as $c)<option value="{{ $c->slug }}" @selected(($k === 'a' ? $a : $b) === $c->slug)>{{ $c->name }}</option>@endforeach</optgroup>
        @endforeach
      </select>
    </label>
    @if ($k === 'a')<span class="cmp-vs" aria-hidden="true">VS</span>@endif
  @endforeach
  <button class="go" type="submit"><i class="ti ti-arrows-diff"></i> Compare</button>
</form>
