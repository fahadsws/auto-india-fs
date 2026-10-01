@extends('admin.layout')
@section('title', 'Pages')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">Pages</h4>
  <a href="{{ route('admin.pages.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Create page</a>
</div>
<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Status</label><select class="dt-filter" name="status" data-search="off"><option value="">All</option><option value="published">Published</option><option value="draft">Draft</option></select></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Template</label><select class="dt-filter" name="template" data-search="off"><option value="">All</option>@foreach (\App\Models\Page::TEMPLATES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Updated between</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.pages.data'), 'order' => [[4, 'desc']], 'columns' => [
    ['title' => 'Page', 'orderable' => true], ['title' => 'URL', 'orderable' => true], ['title' => 'Template', 'orderable' => true], ['title' => 'Status', 'orderable' => true], ['title' => 'Updated', 'orderable' => true], ['title' => '', 'class' => 'text-end'],
  ]])
</div>
@endsection
