@extends('admin.layout')
@section('title', $source->exists ? 'Edit source' : 'Add source')

@section('content')
<h4 class="mb-4">{{ $source->exists ? 'Edit news source' : 'Add news source' }}</h4>
<div class="card" style="max-width:760px"><div class="card-body">
  <form method="POST" action="{{ $source->exists ? route('admin.sources.update', $source) : route('admin.sources.store') }}">
    @csrf @if ($source->exists) @method('PUT') @endif
    <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $source->name) }}" required></div>
    <div class="mb-3"><label class="form-label">Type</label>
      <select class="form-select" name="type" id="type">
        <option value="rss" @selected(old('type', $source->type) === 'rss')>RSS / Atom feed (recommended)</option>
        <option value="page" @selected(old('type', $source->type) === 'page')>News listing page (links harvested by pattern)</option>
      </select></div>
    <div class="mb-3"><label class="form-label">Coverage</label>
      <select class="form-select" name="scope"><option value="india" @selected(old('scope', $source->scope) === 'india')>India outlet (use all stories)</option><option value="global" @selected(old('scope', $source->scope) === 'global')>Global outlet (only stories relevant to Indian buyers)</option></select></div>
    <div class="mb-3"><label class="form-label">Feed / page URL</label><input class="form-control" name="feed_url" value="{{ old('feed_url', $source->feed_url) }}" required placeholder="https://…"></div>
    <div class="mb-3" id="patternBox"><label class="form-label">Article link must contain</label><input class="form-control" name="link_pattern" value="{{ old('link_pattern', $source->link_pattern) }}" placeholder="/news/"><small class="text-muted">Only for “listing page” type: only links containing this text are treated as articles.</small></div>
    <div class="mb-3"><label class="form-label">Default category</label>
      <select class="form-select" name="category_id"><option value="">— None —</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $source->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $source->is_active))><label class="form-check-label" for="act">Active</label></div>
    <button class="btn btn-primary me-2">Save</button><a href="{{ route('admin.sources.index') }}" class="btn btn-label-secondary">Cancel</a>
  </form>
</div></div>
@endsection

@push('scripts')<script>const t = document.getElementById('type'), p = document.getElementById('patternBox'); const sync = () => p.style.display = t.value === 'page' ? '' : 'none'; t.onchange = sync; sync();</script>@endpush
