@extends('admin.layout')
@section('title', 'My profile')

@section('content')
<h4 class="mb-4">My profile</h4>
<div class="card" style="max-width:700px"><div class="card-body">
  <form method="POST" action="{{ route('admin.profile.update') }}">@csrf @method('PUT')
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $user->name) }}" required></div>
      <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="{{ old('email', $user->email) }}" required></div>
      <div class="col-12"><label class="form-label">Bio</label><input class="form-control" name="bio" value="{{ old('bio', $user->bio) }}" maxlength="500"></div>
    </div>
    <h6 class="mt-4">Change password</h6>
    <div class="row g-3 mb-4">
      <div class="col-md-4"><input type="password" class="form-control" name="current_password" placeholder="Current password" autocomplete="current-password"></div>
      <div class="col-md-4"><input type="password" class="form-control" name="password" placeholder="New password" autocomplete="new-password"></div>
      <div class="col-md-4"><input type="password" class="form-control" name="password_confirmation" placeholder="Repeat new password" autocomplete="new-password"></div>
    </div>
    <button class="btn btn-primary">Save profile</button>
  </form>
</div></div>
@endsection
