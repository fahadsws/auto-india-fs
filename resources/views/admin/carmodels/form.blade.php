@extends('admin.layout')
@section('title', $car->exists ? 'Edit '.$car->full_name : 'Add model')

@php
  $specsText = collect($car->specs ?? [])->map(fn ($v, $k) => "$k: $v")->implode("\n");
  $locked = old('locked', $car->locked ?? []);
@endphp
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">{{ $car->exists ? $car->full_name : 'Add model' }}</h4>
  @if ($car->exists)
  <div class="d-flex gap-2">
    <a href="{{ $car->url }}" target="_blank" class="btn btn-label-secondary"><i class="ti ti-eye me-1"></i>View page</a>
    <form method="POST" action="{{ route('admin.car-models.refresh', $car) }}">@csrf<button class="btn btn-label-primary"><i class="ti ti-sparkles me-1"></i>Regenerate text with AI</button></form>
  </div>
  @endif
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $car->exists ? route('admin.car-models.update', $car) : route('admin.car-models.store') }}">
  @csrf @if ($car->exists) @method('PUT') @endif
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card mb-4"><div class="card-body"><div class="row g-3">
        <div class="col-md-3"><label class="form-label">Vehicle type</label><select class="form-select" name="vehicle_type" id="vehicle-type">@foreach(config("vehicles") as $k=>$vt)<option value="{{ $k }}" @selected(old("vehicle_type", $car->vehicle_type ?? request("type", "car"))===$k)>{{ $vt["label"] }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Brand</label><select class="form-select" name="brand_id" required><option value="">Select brand</option>@foreach($masterBrands as $v)<option value="{{ $v->id }}" @selected((string) old('brand_id',$car->brand_id)===(string) $v->id)>{{ $v->name }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label">Model name</label><input class="form-control" name="name" value="{{ old('name', $car->name) }}" required placeholder="Harrier"></div>
        <div class="col-12"><label class="form-label">Tagline</label><input class="form-control" name="tagline" value="{{ old('tagline', $car->tagline) }}" maxlength="300"></div>
        <div class="col-md-4"><label class="form-label">Body type</label><select class="form-select" name="body_type_id"><option value="">Select body type</option>@foreach($masterBodies as $v)<option value="{{ $v->id }}" data-type="{{ strtolower($v->vehicle_type ?: 'car') }}" @selected((string) old('body_type_id',$car->body_type_id)===(string) $v->id)>{{ $v->name }}</option>@endforeach</select></div>
        <div class="col-md-8"><label class="form-label">Fuel types</label><select class="form-select" name="fuel_types[]" multiple size="3">@foreach($masterFuels as $v)<option value="{{ $v->id }}" @selected(in_array($v->id,$car->fuels->pluck('id')->all() ?? []))>{{ $v->name }}</option>@endforeach</select><div class="form-text">Hold Ctrl/Cmd to select multiple fuel types.</div></div>
        <div class="col-md-4"><label class="form-label">Price from (₹ lakh)</label><input type="number" step="0.01" class="form-control" name="price_min_lakh" value="{{ old('price_min_lakh', $car->price_min ? $car->price_min / 100000 : '') }}"></div>
        <div class="col-md-4"><label class="form-label">Price to (₹ lakh)</label><input type="number" step="0.01" class="form-control" name="price_max_lakh" value="{{ old('price_max_lakh', $car->price_max ? $car->price_max / 100000 : '') }}"></div>
        <div class="col-md-4"><label class="form-label">Launch date</label><input type="date" class="form-control" name="launch_date" value="{{ old('launch_date', $car->launch_date?->format('Y-m-d')) }}"></div>
        <div class="col-12"><label class="form-label">Overview</label><textarea class="editor" name="overview" rows="10">{{ old('overview', $car->overview) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Highlights (one per line)</label><textarea class="form-control" name="highlights" rows="6">{{ old('highlights', implode("\n", $car->highlights ?? [])) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Specs (<code>Label: value</code> per line)</label><textarea class="form-control" name="specs" rows="6" placeholder="Engine: 1.5L turbo petrol&#10;Power: 170 hp">{{ old('specs', $specsText) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Meta title</label><input class="form-control" name="meta_title" maxlength="70" value="{{ old('meta_title', $car->meta_title) }}"></div>
        <div class="col-md-6"><label class="form-label">Meta description</label><input class="form-control" name="meta_description" maxlength="320" value="{{ old('meta_description', $car->meta_description) }}"></div>
      </div></div></div>

      <div class="card"><div class="card-header"><h5 class="card-title mb-0">Photos</h5></div><div class="card-body">
        @if ($car->gallery)
          <div class="row g-2 mb-3">
            @foreach ($car->gallery as $i => $p)
              <div class="col-4 col-md-3"><img src="{{ \Illuminate\Support\Str::startsWith($p, 'http') ? $p : (\Illuminate\Support\Str::startsWith($p, '/') ? asset(ltrim($p, '/')) : \Illuminate\Support\Facades\Storage::disk('public')->url($p)) }}" class="w-100 rounded" style="aspect-ratio:4/3;object-fit:cover" alt="">
                <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove[]" value="{{ $p }}" id="rm{{ $i }}"><label class="form-check-label small" for="rm{{ $i }}">Remove</label></div></div>
            @endforeach
          </div>
        @endif
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Upload photos</label><input type="file" class="form-control" name="photos[]" multiple accept="image/*"></div>
          <div class="col-md-6"><label class="form-label">…or fetch from image URLs (one per line)</label><textarea class="form-control" name="image_urls" rows="2"></textarea></div>
        </div>
        @if ($car->archive_gallery)<p class="small text-muted mt-3 mb-0"><i class="ti ti-archive"></i> {{ count($car->archive_gallery) }} pre-facelift photo(s) are archived.</p>@endif
      </div></div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4"><div class="card-body">
        <label class="form-label">Status</label>
        <select class="form-select mb-3" name="status">@foreach (['upcoming', 'launched', 'facelift', 'discontinued'] as $s)<option value="{{ $s }}" @selected(old('status', $car->status) === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_published" value="1" id="pub" @checked(old('is_published', $car->is_published))><label class="form-check-label" for="pub">Published</label></div>
        <button class="btn btn-primary w-100">Save model</button>
      </div></div>

      <div class="card mb-4"><div class="card-header"><h5 class="card-title mb-0">Pin fields</h5><small class="text-muted">Pinned fields are never changed by automation.</small></div><div class="card-body">
        @foreach ($lockable as $k => $label)
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="locked[]" value="{{ $k }}" id="lk{{ $k }}" @checked(in_array($k, $locked))><label class="form-check-label" for="lk{{ $k }}">{{ $label }}</label></div>
        @endforeach
      </div></div>

      @if ($linked->isNotEmpty())
      <div class="card"><div class="card-header"><h5 class="card-title mb-0">Linked stories</h5></div><div class="card-body">
        @foreach ($linked as $a)<div class="mb-2 small"><span class="badge bg-label-secondary">{{ $a->pivot->event }}</span> <a href="{{ route('admin.articles.edit', $a) }}">{{ \Illuminate\Support\Str::limit($a->title, 55) }}</a></div>@endforeach
        <small class="text-muted">Last refreshed: {{ $car->refreshed_at?->diffForHumans() ?? 'never' }}</small>
      </div></div>
      @endif
    </div>
  </div>
</form>
@push('scripts')
<script>
// Body type choices depend on the vehicle type: changing the type clears the selection and rebuilds the list.
(() => {
  const type = document.getElementById('vehicle-type'), body = document.querySelector('select[name=body_type_id]');
  if (!type || !body) return;
  const all = [...body.options].filter(o => o.value).map(o => ({ value: o.value, label: o.textContent, type: o.dataset.type, selected: o.defaultSelected }));
  const render = (clear) => {
    const keep = clear ? '' : body.value;
    body.innerHTML = '<option value="">Select body type</option>';
    all.filter(o => o.type === type.value).forEach(o => body.add(new Option(o.label, o.value, false, o.value === keep)));
    $(body).trigger('change.select2');          // redraw the select2 box
  };
  // select2 fires jQuery events only, so a native addEventListener would never see the change.
  $(type).on('change', () => render(true));
  render(false);
})();
</script>
@endpush
@endsection
