@extends('admin.layout')
@section('title', 'Edit video')

@section('content')
<h4 class="mb-4">Edit video</h4>
<div class="card" style="max-width:760px"><div class="card-body">
  <div class="ratio ratio-16x9 mb-4 rounded overflow-hidden"><iframe src="{{ $video->embed_url }}" title="{{ $video->title }}" allowfullscreen loading="lazy"></iframe></div>
  <form method="POST" action="{{ route('admin.videos.update', $video) }}">@csrf @method('PUT')
    <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" value="{{ old('title', $video->title) }}" required maxlength="250"></div>
    <div class="mb-3"><label class="form-label">Channel</label><input class="form-control" name="channel" value="{{ old('channel', $video->channel) }}"></div>
    <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $video->is_active))><label class="form-check-label" for="act">Visible on the website</label></div>
    <button class="btn btn-primary me-2">Save</button><a href="{{ route('admin.videos.index') }}" class="btn btn-label-secondary">Cancel</a>
  </form>
</div></div>
@endsection
