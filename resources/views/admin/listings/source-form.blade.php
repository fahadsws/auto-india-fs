@extends('admin.layout')
@section('title', $source->exists ? 'Edit listing source' : 'Add listing source')

@section('content')
<h4 class="mb-4">{{ $source->exists ? 'Edit listing source' : 'Add listing source' }}</h4>
<div class="card" style="max-width:820px"><div class="card-body">
  <form method="POST" action="{{ $source->exists ? route('admin.listing-sources.update', $source) : route('admin.listing-sources.store') }}">
    @csrf @if ($source->exists) @method('PUT') @endif
    <div class="row g-3">
      <div class="col-md-8"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $source->name) }}" required></div>
      <div class="col-md-4"><label class="form-label">Format</label><select class="form-select" name="format"><option value="json" @selected(old('format', $source->format) === 'json')>JSON</option><option value="csv" @selected(old('format', $source->format) === 'csv')>CSV</option></select></div>
      <div class="col-12"><label class="form-label">Mode</label>
        <select class="form-select" name="mode" id="mode"><option value="feed" @selected(old('mode', $source->mode) === 'feed')>Feed - a JSON/CSV endpoint you are allowed to use</option><option value="scrape" @selected(old('mode', $source->mode) === 'scrape')>Scrape - read a marketplace's public listing pages</option></select></div>
      <div class="col-12 scrape-only"><div class="alert alert-warning small mb-0">Scraping reads public pages politely: it honours robots.txt, waits between requests and stores facts plus our own summary and photos. Marketplace terms of use may still restrict automated access &mdash; make sure you are comfortable with that for each site, or use an official partner feed instead.</div></div>
      <div class="col-12 scrape-only"><label class="form-label">Listing pages to scan (one URL per line)</label><textarea class="form-control" name="list_urls" rows="4" placeholder="https://www.cardekho.com/used-car-in-mumbai">{{ old('list_urls', $source->list_urls) }}</textarea></div>
      <div class="col-md-6 scrape-only"><label class="form-label">Detail links contain</label><input class="form-control" name="detail_pattern" value="{{ old('detail_pattern', $source->detail_pattern) }}" placeholder="/used-car-details/"></div>
      <div class="col-md-3 scrape-only"><label class="form-label">New cars per run</label><input type="number" class="form-control" name="max_per_run" value="{{ old('max_per_run', $source->max_per_run) }}"></div>
      <div class="col-md-3 scrape-only"><label class="form-label">Delay (ms)</label><input type="number" class="form-control" name="delay_ms" value="{{ old('delay_ms', $source->delay_ms) }}"></div>
      <div class="col-12 scrape-only"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="respect_robots" value="1" id="rr" @checked(old('respect_robots', $source->respect_robots))><label class="form-check-label" for="rr">Respect robots.txt (strongly recommended)</label></div></div>
      <div class="col-12 feed-only"><label class="form-label">Feed URL</label><input class="form-control" name="url" value="{{ old("url", $source->url) }}" placeholder="https://…"></div>
      <div class="col-12 feed-only"><label class="form-label">Path to the list inside the JSON (optional)</label><input class="form-control" name="items_path" value="{{ old('items_path', $source->items_path) }}" placeholder="data.cars"><small class="text-muted">Dot notation. Leave blank if the JSON is a plain array.</small></div>
    </div>
    <h6 class="mt-4 feed-only">Field mapping</h6>
    <p class="small text-muted feed-only">Type the name of the key in the feed for each of our fields. Leave blank when the feed already uses our field name.</p>
    <div class="row g-2 feed-only">
      @foreach ($fields as $f)
        <div class="col-md-4"><label class="form-label small mb-1">{{ $f }}</label><input class="form-control form-control-sm" name="mapping[{{ $f }}]" value="{{ old('mapping.'.$f, ($source->mapping ?? [])[$f] ?? '') }}" placeholder="{{ $f }}"></div>
      @endforeach
    </div>
    <div class="form-check form-switch my-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $source->is_active))><label class="form-check-label" for="act">Active (imported on schedule)</label></div>
    <button class="btn btn-primary me-2">Save</button><a href="{{ route('admin.listings.index') }}" class="btn btn-label-secondary">Cancel</a>
  </form>
</div></div>
@endsection

@push('scripts')<script>const md = document.getElementById('mode'); const sy = () => { document.querySelectorAll('.scrape-only').forEach(e => e.style.display = md.value === 'scrape' ? '' : 'none'); document.querySelectorAll('.feed-only').forEach(e => e.style.display = md.value === 'feed' ? '' : 'none'); }; md.onchange = sy; sy();</script>@endpush
