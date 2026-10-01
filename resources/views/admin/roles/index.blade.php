@extends('admin.layout')
@section('title', 'Roles & permissions')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h4 class="mb-0">Roles &amp; permissions</h4><a href="{{ route('admin.roles.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>New role</a></div>
<div class="card dt-wrap">
  <div class="card-header border-bottom"><div class="row g-3 align-items-end">
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Usage</label><select class="dt-filter" name="usage" data-search="off"><option value="">All roles</option><option value="used">Assigned to users</option><option value="unused">Unassigned</option></select></div>
    <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
  </div></div>
  @include('admin.partials.dt', ['url' => route('admin.roles.data'), 'order' => [[0, 'asc']], 'columns' => [
    ['title' => 'Role', 'orderable' => true], ['title' => 'Users'], ['title' => 'Permissions'], ['title' => '', 'class' => 'text-end'],
  ]])
</div>
@endsection
