@extends('admin.layout')
@section('title', 'Users')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h4 class="mb-0">Users</h4><a href="{{ route('admin.users.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add user</a></div>
<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Role</label><select class="dt-filter" name="role"><option value="">All roles</option>@foreach ($roles as $r)<option>{{ $r->name }}</option>@endforeach</select></div>
    <div class="col-6 col-md-3 col-xl-2"><label class="form-label small mb-1">Status</label><select class="dt-filter" name="status" data-search="off"><option value="">Any status</option><option value="1">Active</option><option value="0">Disabled</option></select></div>
    <div class="col-6 col-md-3 col-xl-3"><label class="form-label small mb-1">Joined between</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.users.data'), 'order' => [[0, 'asc']], 'columns' => [
    ['title' => 'User', 'orderable' => true], ['title' => 'Roles'], ['title' => 'Articles', 'class' => 'd-none d-lg-table-cell'], ['title' => 'Joined', 'orderable' => true], ['title' => 'Status'], ['title' => '', 'class' => 'text-end'],
  ]])
</div>
@endsection
