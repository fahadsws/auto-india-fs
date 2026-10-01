@extends('admin.layout')
@section('title', 'Categories')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">Categories</h4>
  <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>New category</a>
</div>
<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Usage</label>
      <select class="dt-filter" name="usage" data-search="off"><option value="">All categories</option><option value="used">With articles</option><option value="empty">Empty</option></select></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">State</label>
      <select class="dt-filter" name="state" data-search="off"><option value="">Enabled + disabled</option><option value="enabled">Enabled</option><option value="disabled">Disabled</option></select></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.categories.data'), 'order' => [[0, 'asc']], 'columns' => [
    ['title' => 'Name', 'orderable' => true], ['title' => 'Status', 'orderable' => true], ['title' => 'Articles', 'orderable' => true], ['title' => 'Created', 'orderable' => true], ['title' => '', 'class' => 'text-end'],
  ]])
</div>
@endsection
