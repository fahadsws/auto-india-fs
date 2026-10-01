@extends('admin.layout')
@section('title', 'Car listings')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">Car listings</h4>
  <div class="d-flex flex-wrap gap-2">
    <a href="{{ route('admin.listing-sources.index') }}" class="btn btn-label-secondary"><i class="ti ti-plug me-1"></i>Import &amp; scrape sources</a>
    <button class="btn btn-label-primary" data-bs-toggle="modal" data-bs-target="#csvModal"><i class="ti ti-upload me-1"></i>Import CSV</button>
    <a href="{{ route('admin.listings.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add listing</a>
  </div>
</div>

<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Status</label><select class="dt-filter" name="status" data-search="off"><option value="">All statuses</option>@foreach (['active', 'sold', 'hidden'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Brand</label><select class="dt-filter" name="brand"><option value="">All brands</option>@foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Fuel</label><select class="dt-filter" name="fuel"><option value="">Any fuel</option>@foreach ($fuels as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">City</label><select class="dt-filter" name="city"><option value="">Any city</option>@foreach ($cities as $c)<option>{{ $c }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Source</label><select class="dt-filter" name="source"><option value="">All sources</option><option value="manual">Added manually</option>@foreach ($sources as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Price up to</label><select class="dt-filter" name="max_price" data-search="off"><option value="">Any price</option>@foreach ([300000 => '₹3 L', 600000 => '₹6 L', 1000000 => '₹10 L', 1500000 => '₹15 L', 2500000 => '₹25 L', 5000000 => '₹50 L'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Added between</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.listings.data'), 'order' => [[6, 'desc']], 'columns' => [
    ['title' => 'Car', 'orderable' => true], ['title' => 'Price', 'orderable' => true], ['title' => 'Details', 'class' => 'd-none d-xl-table-cell'], ['title' => 'Source', 'class' => 'd-none d-xxl-table-cell'], ['title' => 'Leads', 'class' => 'd-none d-xxl-table-cell'], ['title' => 'Status', 'orderable' => true], ['title' => 'Added', 'orderable' => true], ['title' => '', 'class' => 'text-end'],
  ]])
</div>

<div class="modal fade" id="csvModal" tabindex="-1"><div class="modal-dialog"><form class="modal-content" method="POST" action="{{ route('admin.listings.import') }}" enctype="multipart/form-data">@csrf
  <div class="modal-header"><h5 class="modal-title">Import listings from CSV</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <p class="small text-muted">First row = headers named <code>title, brand, model, year, price, km_driven, fuel, transmission, owner, city, description, image, url, external_id</code>. Only <code>title</code> is required. Re-importing the same <code>external_id</code> updates instead of duplicating.</p>
    <input type="file" name="csv" class="form-control" accept=".csv,text/csv" required>
  </div>
  <div class="modal-footer"><button class="btn btn-primary">Import</button></div>
</form></div></div>
@endsection
