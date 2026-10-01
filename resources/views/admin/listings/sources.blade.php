@extends('admin.layout')
@section('title', 'Listing sources')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
    <h4 class="mb-0">Listing import &amp; scrape sources</h4>
    <div class="d-flex gap-2"><a href="{{ route('admin.listings.index') }}" class="btn btn-label-secondary">Back to
        listings</a><a href="{{ route('admin.listing-sources.create') }}" class="btn btn-primary"><i
          class="ti ti-plus me-1"></i>Add source</a></div>
  </div>
  <p class="text-muted mb-4">Feeds (JSON/CSV) and polite marketplace scrapers that fill the used-car catalog and mark sold
    cars automatically.</p>
  <div class="card mb-4">
    <div class="card-body">
      <h5 class="mb-1"><i class="ti ti-link me-1 text-primary"></i>Import a used car from a link</h5>
      <p class="text-muted small mb-3">Paste the page of one car (marketplace or dealer). We read the title, price, km,
        year, fuel, owner and photos and add it as an active listing. Duplicates are skipped.</p>
      <form method="POST" action="{{ route('admin.listing-sources.import-url') }}" class="row g-2 align-items-end">@csrf
        <div class="col-12 col-md-9"><label class="form-label small mb-1">Car page URL</label><input type="url" name="url"
            class="form-control" placeholder="https://www.example.com/used-car/..." required></div>
        <div class="col-12 col-md-3"><button class="btn btn-primary w-100">Import</button></div>
      </form>
    </div>
  </div>
  <div class="card dt-wrap">
    <div class="card-header border-bottom">
      <div class="row g-3 align-items-end">
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Mode</label><select class="dt-filter"
            name="mode" data-search="off">
            <option value="">Feed + scrape</option>
            <option value="feed">Feed</option>
            <option value="scrape">Scrape</option>
          </select></div>
        <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Status</label><select class="dt-filter"
            name="status" data-search="off">
            <option value="">Any status</option>
            <option value="active">Active</option>
            <option value="paused">Paused</option>
          </select></div>
        <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i
              class="ti ti-refresh me-1"></i>Reset</button></div>
      </div>
    </div>
    @include('admin.partials.dt', [
      'url' => route('admin.listing-sources.data'),
      'order' => [[0, 'asc']],
      'columns' => [
        ['title' => 'Source', 'orderable' => true],
        ['title' => 'Mode'],
        ['title' => 'Pages / URL', 'class' => 'd-none d-xl-table-cell'],
        ['title' => 'Listings'],
        ['title' => 'Last run', 'orderable' => true],
        ['title' => 'Status'],
        ['title' => '', 'class' => 'text-end'],
      ]
    ])
  </div>
@endsection