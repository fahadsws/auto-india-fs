@extends('admin.layout')
@section('title', $category->exists ? 'Edit category' : 'New category')

@section('content')
<h4 class="mb-4">{{ $category->exists ? 'Edit category' : 'New category' }}</h4>
<div class="card" style="max-width:560px"><div class="card-body">
  <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
    @csrf @if ($category->exists) @method('PUT') @endif
    <div class="mb-4"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $category->name) }}" required autofocus placeholder="e.g. Motorsport">
</div>
    <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $category->exists ? $category->is_active : true))><label class="form-check-label" for="act">Enabled (shown in menus and filters)</label></div>
    <button class="btn btn-primary me-2">Save</button><a href="{{ route('admin.categories.index') }}" class="btn btn-label-secondary">Cancel</a>
  </form>
</div></div>
@endsection
