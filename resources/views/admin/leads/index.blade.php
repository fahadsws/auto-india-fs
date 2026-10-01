@extends('admin.layout')
@section('title', 'Leads')

@php $colors = ['new' => 'danger', 'contacted' => 'warning', 'won' => 'success', 'lost' => 'secondary']; @endphp
@section('content')
<h4 class="mb-4">Leads &amp; enquiries</h4>
<div class="row g-3 mb-4">
  @foreach ($colors as $s => $c)
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-3"><div class="text-muted small text-uppercase">{{ $s }}</div><h3 class="mb-0 text-{{ $c }}">{{ $counts[$s] ?? 0 }}</h3></div></div></div>
  @endforeach
</div>

<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Status</label><select class="dt-filter" name="status" data-search="off"><option value="">All statuses</option>@foreach ($colors as $s => $c)<option value="{{ $s }}">{{ ucfirst($s) }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Type</label><select class="dt-filter" name="type" data-search="off"><option value="">All types</option>@foreach (['enquiry', 'contact', 'sell', 'chatbot', 'test_drive', 'inspection'] as $t)<option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Assigned to</label><select class="dt-filter" name="assignee"><option value="">Anyone</option><option value="none">Unassigned</option>@foreach ($staff as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl"><label class="form-label small mb-1">Received between</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.leads.data'), 'order' => [[5, 'desc']], 'columns' => [
    ['title' => 'Lead', 'orderable' => true], ['title' => 'Interest', 'class' => 'd-none d-xl-table-cell'], ['title' => 'Contact'], ['title' => 'Status', 'orderable' => true], ['title' => 'Assigned', 'class' => 'd-none d-xl-table-cell'], ['title' => 'Received', 'orderable' => true], ['title' => '', 'class' => 'text-end'],
  ]])
</div>
@endsection
