@extends('admin.layout')
@section('title', 'Video library')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">Video library</h4>
  <a href="{{ route('admin.videos.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add videos</a>
</div>
<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Visibility</label><select class="dt-filter" name="visible" data-search="off"><option value="">All</option><option value="1">Visible</option><option value="0">Hidden</option></select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Channel</label><select class="dt-filter" name="channel"><option value="">All channels</option>@foreach ($channels as $c)<option>{{ $c }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Usage</label><select class="dt-filter" name="usage" data-search="off"><option value="">All</option><option value="used">Attached to articles</option><option value="unused">Not attached</option></select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Added between</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.videos.data'), 'order' => [[3, 'desc']], 'columns' => [
    ['title' => 'Video', 'orderable' => true], ['title' => 'Channel', 'orderable' => true], ['title' => 'Articles'], ['title' => 'Added', 'orderable' => true], ['title' => 'Visible'], ['title' => '', 'class' => 'text-end'],
  ]])
</div>
@endsection
