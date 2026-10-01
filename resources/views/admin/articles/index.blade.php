@extends('admin.layout')
@section('title', 'Articles')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">Articles</h4>
  @can('articles.create')<a href="{{ route('admin.articles.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>New article</a>@endcan
</div>

@can('articles.create')
<div class="card mb-4"><div class="card-body">
  <h5 class="mb-1"><i class="ti ti-link me-1 text-primary"></i>Import an article from a link</h5>
  <p class="text-muted small mb-3">Paste any news page. We read the article, rewrite it in our own words with the same facts, keep the source credit, and open it here in the editor so you can check and publish it.</p>
  <form method="POST" action="{{ route('admin.articles.import-url') }}" class="row g-2 align-items-end" data-confirm="Fetch and rewrite this article now? This can take up to a minute.">@csrf
    <div class="col-12 col-lg-6"><label class="form-label small mb-1">Article URL</label><input type="url" name="url" value="{{ old('url') }}" class="form-control" placeholder="https://www.example.com/news/..." required></div>
    <div class="col-6 col-lg-3"><label class="form-label small mb-1">Category (optional)</label><select name="category_id" class="form-select"><option value="">Let AI choose</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
    <div class="col-6 col-lg-2"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="draft" id="imp-draft" value="1" checked><label class="form-check-label small" for="imp-draft">Save as draft</label></div></div>
    <div class="col-12 col-lg-1"><button class="btn btn-primary w-100">Import</button></div>
  </form>
</div></div>
@endcan

<div class="card dt-wrap">
  <div class="card-header border-bottom">
    <div class="row g-3 align-items-end">
      <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Status</label>
        <select class="dt-filter" name="status" data-search="off"><option value="">All statuses</option>@foreach (['published', 'scheduled', 'draft'] as $s)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach<option value="duplicate">Merged duplicates</option></select></div>
      <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Category</label>
        <select class="dt-filter" name="category"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
      <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Origin</label>
        <select class="dt-filter" name="origin" data-search="off"><option value="">AI + manual</option><option value="ai">AI generated</option><option value="manual">Written manually</option></select></div>
      @can('articles.edit_all')
      <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Author</label>
        <select class="dt-filter" name="author"><option value="">Anyone</option><option value="none">Automation desk</option>@foreach ($authors as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
      @endcan
      <div class="col-md-4 col-xl-3"><label class="form-label small mb-1">Created between</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
      <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
    </div>
  </div>
  @include('admin.partials.dt', [
    'url' => route('admin.articles.data'), 'order' => [[4, 'desc']],
    'columns' => [
      ['title' => 'Article', 'orderable' => true], ['title' => 'Status', 'orderable' => true], ['title' => 'Category', 'class' => 'd-none d-lg-table-cell'], ['title' => 'Author', 'class' => 'd-none d-xl-table-cell'],
      ['title' => 'Created', 'orderable' => true], ['title' => 'Views', 'orderable' => true, 'class' => 'd-none d-xxl-table-cell'], ['title' => '', 'class' => 'text-end'],
    ],
  ])
</div>
@endsection
