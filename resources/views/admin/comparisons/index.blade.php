@extends('admin.layout')
@section('title', 'Suggested comparisons')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
  <h4 class="mb-0">Suggested comparisons</h4>
  <a href="{{ route('admin.comparisons.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add comparison</a>
</div>
<p class="text-muted mb-4">Promoted pairs shown on the home page and the Compare page. Visitors can still compare any two cars — these are just your recommendations.</p>
<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Visibility</label><select class="dt-filter" name="active" data-search="off"><option value="">All</option><option value="1">Active</option><option value="0">Hidden</option></select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Featured</label><select class="dt-filter" name="featured" data-search="off"><option value="">All</option><option value="1">Featured</option><option value="0">Not featured</option></select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Updated between</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.comparisons.data'), 'order' => [[3, 'desc']], 'columns' => [
    ['title' => 'Comparison', 'orderable' => true], ['title' => 'Featured', 'orderable' => true], ['title' => 'Active', 'orderable' => true], ['title' => 'Updated', 'orderable' => true], ['title' => '', 'class' => 'text-end'],
  ]])
</div>
@endsection
