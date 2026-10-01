@extends('admin.layout')
@section('title', $article->exists ? 'Edit article' : 'New article')

@section('content')
<form method="POST" enctype="multipart/form-data" id="articleForm"
      action="{{ $article->exists ? route('admin.articles.update', $article) : route('admin.articles.store') }}">
  @csrf @if ($article->exists) @method('PUT') @endif

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h4 class="mb-0">{{ $article->exists ? 'Edit article' : 'New article' }}</h4>
    <div class="d-flex gap-2">
      <a href="{{ route('admin.articles.index') }}" class="btn btn-label-secondary">Back</a>
      <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Save</button>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      @can('articles.create')
      <div class="card mb-4"><div class="card-body">
        <label class="form-label"><i class="ti ti-sparkles text-primary"></i> Draft with AI</label>
        <div class="input-group">
          <input type="text" id="aiTopic" class="form-control" placeholder="Topic or outline, e.g. “Tata Sierra EV vs Mahindra XEV 9e — which to buy?”">
          <button type="button" class="btn btn-primary" id="aiBtn">Generate</button>
        </div>
        <small class="text-muted">Fills title, summary, body and SEO fields. Review before publishing.</small>
      </div></div>
      @endcan

      <div class="card mb-4"><div class="card-body">
        <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" id="f_title" value="{{ old('title', $article->title) }}" required></div>
        <div class="mb-3"><label class="form-label">Summary / excerpt</label><textarea class="form-control" name="excerpt" id="f_excerpt" rows="2" maxlength="600">{{ old('excerpt', $article->excerpt) }}</textarea></div>
        <label class="form-label">Body</label>
        <textarea name="body" id="f_body" class="editor">{{ old('body', $article->body) }}</textarea>
      </div></div>

      <div class="card"><div class="card-header"><h5 class="card-title mb-0">SEO</h5></div><div class="card-body">
        <div class="mb-3"><label class="form-label">Meta title</label><input class="form-control" name="meta_title" id="f_meta_title" maxlength="70" value="{{ old('meta_title', $article->meta_title) }}"></div>
        <div class="mb-3"><label class="form-label">Meta description</label><textarea class="form-control" name="meta_description" id="f_meta_description" rows="2" maxlength="320">{{ old('meta_description', $article->meta_description) }}</textarea></div>
      </div></div>
    </div>

    <div class="col-lg-4">
      <div class="card mb-4"><div class="card-header"><h5 class="card-title mb-0">Publish</h5></div><div class="card-body">
        @cannot('articles.publish')<div class="alert alert-warning py-2 small">You can save drafts. An editor will review and publish.</div>@endcannot
        <div class="mb-3"><label class="form-label">Status</label>
          <select class="form-select" name="status" @cannot('articles.publish') disabled @endcannot>
            @foreach (['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published'] as $k => $v)<option value="{{ $k }}" @selected(old('status', $article->status) === $k)>{{ $v }}</option>@endforeach
          </select>
          @cannot('articles.publish')<input type="hidden" name="status" value="draft">@endcannot
        </div>
        <div class="mb-3"><label class="form-label">Publish date/time</label>
          <input type="datetime-local" class="form-control" name="published_at" value="{{ old('published_at', $article->published_at?->format('Y-m-d\TH:i')) }}"><small class="text-muted">Future date + “Scheduled” publishes automatically.</small></div>
        <div class="mb-3"><label class="form-label">Category</label>
          <select class="form-select" name="category_id"><option value="">— None —</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $article->category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
        @if ($article->source_url)<p class="small text-muted mb-0">Source: <a href="{{ $article->source_url }}" target="_blank" rel="noopener nofollow">{{ parse_url($article->source_url, PHP_URL_HOST) }}</a></p>@endif
      </div></div>

      <div class="card mb-4"><div class="card-header"><h5 class="card-title mb-0">Featured image</h5></div><div class="card-body">
        @if ($article->image_path)<img src="{{ $article->image_url }}" class="thumb-lg mb-3" alt="">@endif
        <input type="file" class="form-control" name="image" accept="image/*">
      </div></div>

      @can('videos.manage')
      <div class="card"><div class="card-header"><h5 class="card-title mb-0">Attached videos</h5></div><div class="card-body">
        <select class="form-select" name="videos[]" multiple size="8">
          @foreach ($videos as $v)<option value="{{ $v->id }}" @selected(in_array($v->id, old('videos', $attached)))>{{ \Illuminate\Support\Str::limit($v->title, 50) }}</option>@endforeach
        </select>
        <small class="text-muted">Ctrl/Cmd-click to select several.</small>
      </div></div>
      @endcan
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
  const aiBtn = document.getElementById('aiBtn');
  aiBtn?.addEventListener('click', async () => {
    const topic = document.getElementById('aiTopic').value.trim(); if (!topic) return;
    aiBtn.disabled = true; aiBtn.textContent = 'Writing…';
    try {
      const r = await fetch(@json(route('admin.articles.ai')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': ADMIN.csrf }, body: JSON.stringify({ topic }) });
      const d = await r.json(); if (!r.ok) throw new Error(d.error || 'Failed');
      f_title.value = d.title || ''; f_excerpt.value = d.excerpt || ''; f_meta_title.value = d.meta_title || d.title || ''; f_meta_description.value = d.meta_description || '';
      tinymce.get('f_body').setContent(d.body_html || '');
    } catch (e) { alert(e.message); }
    aiBtn.disabled = false; aiBtn.textContent = 'Generate';
  });
</script>
@endpush
