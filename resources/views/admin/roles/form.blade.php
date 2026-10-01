@extends('admin.layout')
@section('title', $role->exists ? 'Edit role' : 'New role')

@php
  $labels = [
    'admin.access' => 'Access the admin panel', 'articles.view' => 'View articles', 'articles.create' => 'Create articles',
    'articles.edit_own' => 'Edit own articles', 'articles.edit_all' => 'Edit everyone\'s articles', 'articles.publish' => 'Publish articles',
    'articles.delete' => 'Delete articles', 'categories.manage' => 'Manage categories', 'sources.manage' => 'Manage news sources',
    'videos.manage' => 'Manage video library', 'cars.manage' => 'Manage new-car catalog', 'listings.manage' => 'Manage car listings', 'leads.view' => 'View leads', 'leads.manage' => 'Update / delete leads',
    'users.manage' => 'Manage users', 'roles.manage' => 'Manage roles', 'settings.manage' => 'Change settings & API keys', 'automation.manage' => 'Run & configure automation',
  ];
@endphp
@section('content')
<h4 class="mb-4">{{ $role->exists ? 'Edit role: '.$role->name : 'New role' }}</h4>
<form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
  @csrf @if ($role->exists) @method('PUT') @endif
  <div class="card mb-4" style="max-width:520px"><div class="card-body"><label class="form-label">Role name</label><input class="form-control" name="name" value="{{ old('name', $role->name) }}" required></div></div>
  <div class="row g-3 mb-4">
    @foreach ($groups as $group => $perms)
      <div class="col-md-6 col-xl-4"><div class="perm-group h-100 bg-white">
        <h6 class="mb-3">{{ $group }}</h6>
        @foreach ($perms as $p)
          <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $p }}" id="p_{{ $p }}" @checked(in_array($p, old('permissions', $granted)))><label class="form-check-label" for="p_{{ $p }}">{{ $labels[$p] ?? $p }}</label></div>
        @endforeach
      </div></div>
    @endforeach
  </div>
  <button class="btn btn-primary me-2">Save role</button><a href="{{ route('admin.roles.index') }}" class="btn btn-label-secondary">Cancel</a>
</form>
@endsection
