@extends('admin.layout')
@section('title', 'Vehicle catalog')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
  <h4 class="mb-0">New-vehicle catalog</h4>
  <a href="{{ route('admin.car-models.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add model</a>
</div>
<p class="text-muted mb-4">Pages are created and refreshed automatically when launch, facelift, price or spec news is published. Pin any field on the edit screen to stop automation changing it.</p>
<div class="card mb-4"><div class="card-body">
  <h5 class="mb-1"><i class="ti ti-link me-1 text-primary"></i>Add models from links</h5>
  <p class="text-muted small mb-3">Paste one or many launch / product page links, <b>one per line</b>. We read the specs, price and photos, write each model page in our own words, and fill the spec list (from the page's spec tables, then from the official spec sheet if the page has few). Tick "same model" if the links are different pages about one vehicle.</p>
  <form method="POST" action="{{ route('admin.car-models.import-url') }}" id="impForm" class="row g-2 align-items-end">@csrf
    <div class="col-12 col-lg-6"><label class="form-label small mb-1">Page links (one per line, up to 30)</label><textarea name="urls" id="impUrls" rows="4" class="form-control" placeholder="https://www.example.com/bikes/model-one&#10;https://www.example.com/bikes/model-two" required>{{ old('urls', old('url')) }}</textarea></div>
    <div class="col-6 col-lg-3"><label class="form-label small mb-1">Vehicle type</label><select name="vehicle_type" class="form-select">@foreach (config('vehicles') as $k => $vt)<option value="{{ $k }}" @selected(old('vehicle_type') === $k)>{{ $vt['label'] }}</option>@endforeach</select>
      <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="draft" id="imp-draft" value="1" checked><label class="form-check-label small" for="imp-draft">Keep hidden</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="merge" id="imp-merge" value="1"><label class="form-check-label small" for="imp-merge">All links are the same model</label></div></div>
    <div class="col-6 col-lg-3"><button class="btn btn-primary w-100" id="impGo"><i class="ti ti-download me-1"></i>Import</button></div>
  </form>
  <div id="impLog" class="mt-3 small" hidden></div>
</div></div>
<script>
(() => {
  const f = document.getElementById('impForm'); if (!f) return;
  const log = document.getElementById('impLog'), btn = document.getElementById('impGo');
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  f.addEventListener('submit', async e => {
    const links = [...new Set(f.urls.value.split(/[\s,]+/).filter(u => /^https?:\/\//i.test(u)))];
    if (links.length < 1) return;                                  // let the server explain
    const merge = f.merge.checked;
    if (links.length === 1 || merge) return;                       // a single page: normal submit, opens the new model
    e.preventDefault(); btn.disabled = true; log.hidden = false; log.innerHTML = '';
    let ok = 0;
    for (const [i, u] of links.entries()) {                        // one link per request: no timeouts, live progress
      const row = document.createElement('div'); row.className = 'py-1'; row.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + (i + 1) + '/' + links.length + ' · ' + esc(u); log.appendChild(row);
      try {
        const body = new FormData(); body.append('_token', f._token.value); body.append('url', u); body.append('vehicle_type', f.vehicle_type.value); if (f.draft.checked) body.append('draft', '1');
        const r = await fetch(f.action, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        let d = {}; try { d = await r.json(); } catch (er) {}
        if (r.ok && d.ok) { ok++; row.innerHTML = '<span class="text-success">✔</span> <a href="' + d.edit_url + '" target="_blank">' + esc(d.name) + '</a> · ' + d.specs + ' specs'; }
        else row.innerHTML = '<span class="text-danger">✖</span> ' + esc(u) + ' — ' + esc(d.message || ('Failed (' + r.status + ')'));
      } catch (er) { row.innerHTML = '<span class="text-danger">✖</span> ' + esc(u) + ' — connection problem'; }
    }
    btn.disabled = false;
    const done = document.createElement('div'); done.className = 'mt-2 fw-semibold'; done.innerHTML = ok + ' of ' + links.length + ' imported. <a href="">Refresh the list</a>'; log.appendChild(done);
  });
})();
</script>
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
