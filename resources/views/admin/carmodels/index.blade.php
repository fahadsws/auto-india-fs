@extends('admin.layout')
@section('title', 'Vehicle catalog')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
  <h4 class="mb-0">New-vehicle catalog</h4>
  <a href="{{ route('admin.car-models.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add model</a>
</div>
<p class="text-muted mb-4">Pages are created and refreshed automatically when launch, facelift, price or spec news is published. Pin any field on the edit screen to stop automation changing it.</p>
<div class="card mb-4"><div class="card-body">
  <h5 class="mb-1"><i class="ti ti-link me-1 text-primary"></i>Add a model from a link</h5>
  <p class="text-muted small mb-3">Paste a launch or product page. We read the specs, price and photos, write the model page in our own words, and open it here so you can check it and publish.</p>
  <form method="POST" action="{{ route('admin.car-models.import-url') }}" class="row g-2 align-items-end" data-confirm="Read this page and build the model now? This can take up to a minute.">@csrf
    <div class="col-12 col-lg-6"><label class="form-label small mb-1">Page URL</label><input type="url" name="url" value="{{ old('url') }}" class="form-control" placeholder="https://www.example.com/bikes/..." required></div>
    <div class="col-6 col-lg-3"><label class="form-label small mb-1">Vehicle type</label><select name="vehicle_type" class="form-select">@foreach (config('vehicles') as $k => $vt)<option value="{{ $k }}" @selected(old('vehicle_type') === $k)>{{ $vt['label'] }}</option>@endforeach</select></div>
    <div class="col-6 col-lg-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="draft" id="imp-draft" value="1" checked><label class="form-check-label small" for="imp-draft">Keep hidden</label></div></div>
    <div class="col-12 col-lg-1"><button class="btn btn-primary w-100">Import</button></div>
  </form>
</div></div>
<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Vehicle type</label><select class="dt-filter" name="type" data-search="off"><option value="">All types</option>@foreach (config("vehicles") as $k => $vt)<option value="{{ $k }}">{{ $vt["plural"] }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Status</label><select class="dt-filter" name="status" data-search="off"><option value="">All statuses</option>@foreach (['upcoming', 'launched', 'facelift', 'discontinued'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Brand</label><select class="dt-filter" name="brand"><option value="">All brands</option>@foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Body type</label><select class="dt-filter" name="body"><option value="">Any body type</option>@foreach ($bodies as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Visibility</label><select class="dt-filter" name="published" data-search="off"><option value="">All</option><option value="1">Published</option><option value="0">Hidden</option></select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Last event between</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.car-models.data'), 'order' => [[5, 'desc']], 'columns' => [
    ['title' => 'Model', 'orderable' => true], ['title' => 'Status', 'orderable' => true], ['title' => 'Price', 'orderable' => true], ['title' => 'Photos', 'class' => 'd-none d-xl-table-cell'], ['title' => 'Stories', 'class' => 'd-none d-xl-table-cell'], ['title' => 'Last event', 'orderable' => true], ['title' => '', 'class' => 'text-end'],
  ]])
</div>
@endsection
