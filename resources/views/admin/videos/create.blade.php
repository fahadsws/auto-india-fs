@extends('admin.layout')
@section('title', 'Add videos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h4 class="mb-0">Add videos</h4><a href="{{ route('admin.videos.index') }}" class="btn btn-label-secondary">Back to library</a></div>
<div class="row g-4" style="max-width:1000px">
  <div class="col-lg-6"><div class="card h-100"><div class="card-body">
    <h6><i class="ti ti-link me-1"></i>Add by YouTube link</h6>
    <form method="POST" action="{{ route('admin.videos.store') }}">@csrf
      <input class="form-control mb-3" name="url" placeholder="https://www.youtube.com/watch?v=…" required>
      <button class="btn btn-primary">Add video</button>
    </form>
    <small class="text-muted d-block mt-3">No API key needed. Videos are embedded from YouTube and never hosted here.</small>
  </div></div></div>
  <div class="col-lg-6"><div class="card h-100"><div class="card-body">
    <h6><i class="ti ti-search me-1"></i>Find on YouTube by keyword</h6>
    <form method="POST" action="{{ route('admin.videos.search') }}">@csrf
      <input class="form-control mb-3" name="keyword" placeholder="e.g. Tata Nexon review" required @disabled(! $apiReady)>
      <button class="btn btn-primary" @disabled(! $apiReady)>Search &amp; save results</button>
    </form>
    @unless ($apiReady)<small class="text-danger d-block mt-3">Add a YouTube Data API key in Settings to enable search.</small>@else<small class="text-muted d-block mt-3">Each search uses 100 of your 10,000 free daily API units.</small>@endunless
  </div></div></div>
</div>
@endsection
