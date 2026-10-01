@extends('admin.layout')
@section('title', $user->exists ? 'Edit user' : 'Add user')

@section('content')
<h4 class="mb-4">{{ $user->exists ? 'Edit user' : 'Add user' }}</h4>
@php $mine = $user->exists && $user->id === auth()->id(); $have = old('roles', $user->roles->pluck('name')->all()); @endphp
<div class="card" style="max-width:760px"><div class="card-body">
  <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
    @csrf @if ($user->exists) @method('PUT') @endif
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
      <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ old('email', $user->email) }}" required></div>
      <div class="col-md-6"><label class="form-label">Password {{ $user->exists ? '(leave blank to keep)' : '' }}</label><input type="password" class="form-control" name="password" @required(! $user->exists) autocomplete="new-password"></div>
      <div class="col-12"><label class="form-label">Short bio (shown as author)</label><input class="form-control" name="bio" value="{{ old('bio', $user->bio) }}" maxlength="500"></div>
    </div>
    <h6 class="mt-4">Roles</h6>
    @if ($mine)<p class="small text-muted">You can't change your own roles or disable yourself.</p>@endif
    <div class="row g-2 mb-3">
      @foreach ($roles as $r)
        <div class="col-sm-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="roles[]" value="{{ $r->name }}" id="r{{ $r->id }}" @checked(in_array($r->name, $have)) @disabled($mine)><label class="form-check-label" for="r{{ $r->id }}">{{ $r->name }}</label></div></div>
      @endforeach
    </div>
    <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $user->is_active)) @disabled($mine)><label class="form-check-label" for="act">Account active</label></div>
    <button class="btn btn-primary me-2">Save</button><a href="{{ route('admin.users.index') }}" class="btn btn-label-secondary">Cancel</a>
  </form>
</div></div>
@endsection
