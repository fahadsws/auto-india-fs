@extends('admin.layout')
@section('title', $page->exists ? 'Edit page' : 'Create page')

@section('content')
@php
  $tab = $errors->has('meta_title') || $errors->has('meta_description') || $errors->has('canonical_url') || $errors->has('schema_json') ? 'seo' : ($errors->has('faq.*') ? 'faq' : 'basic');
@endphp
<form method="POST" enctype="multipart/form-data" id="pageForm" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}">
  @csrf @if ($page->exists) @method('PUT') @endif

  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
      <a href="{{ route('admin.pages.index') }}" class="btn btn-label-secondary"><i class="ti ti-arrow-left me-1"></i>Back</a>
      <h4 class="mb-0">{{ $page->exists ? 'Edit page' : 'Create page' }}</h4>
    </div>
    <div class="d-flex gap-2">
      @if ($page->exists)<a href="{{ $page->is_live ? $page->url : route('admin.pages.preview', $page) }}" target="_blank" rel="noopener" class="btn btn-label-secondary"><i class="ti ti-eye me-1"></i>{{ $page->is_live ? 'View' : 'Preview' }}</a>@endif
      <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Save Page</button>
    </div>
  </div>

  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <div class="card">
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link {{ $tab === 'basic' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#t-basic"><i class="ti ti-settings me-2"></i>Basic Info</button></li>
      <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#t-content"><i class="ti ti-file-text me-2"></i>Content</button></li>
      <li class="nav-item"><button class="nav-link {{ $tab === 'seo' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#t-seo"><i class="ti ti-world me-2"></i>SEO &amp; Schema</button></li>
      <li class="nav-item"><button class="nav-link {{ $tab === 'faq' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#t-faq"><i class="ti ti-help-circle me-2"></i>FAQ</button></li>
    </ul>
    <div class="card-body tab-content pt-4">

      {{-- Basic info --}}
      <div class="tab-pane fade {{ $tab === 'basic' ? 'show active' : '' }}" id="t-basic">
        <div class="row g-4">
          <div class="col-md-6"><label class="form-label">Page Title <span class="text-danger">*</span></label>
            <input class="form-control" name="title" id="f_title" value="{{ old('title', $page->title) }}" placeholder="Enter page title" required maxlength="200"></div>
          <div class="col-md-6"><label class="form-label">Slug <span class="text-danger">*</span></label>
            <div class="input-group"><span class="input-group-text">{{ url('/') }}/</span><input class="form-control" name="slug" id="f_slug" value="{{ old('slug', $page->slug) }}" placeholder="page-slug-url" maxlength="160"></div>
            <small class="text-muted">The page opens at this URL. Lowercase letters, numbers and hyphens.</small></div>
          <div class="col-md-6"><label class="form-label">Template</label>
            <select class="form-select" name="template">@foreach (\App\Models\Page::TEMPLATES as $k => $v)<option value="{{ $k }}" @selected(old('template', $page->template) === $k)>{{ $v }}</option>@endforeach</select></div>
          <div class="col-md-6"><label class="form-label">Status</label>
            <select class="form-select" name="status"><option value="draft" @selected(old('status', $page->status) === 'draft')>Draft</option><option value="published" @selected(old('status', $page->status) === 'published')>Published</option></select></div>
          <div class="col-md-6"><label class="form-label">Publish date/time</label>
            <input type="datetime-local" class="form-control" name="published_at" value="{{ old('published_at', $page->published_at?->format('Y-m-d\TH:i')) }}"><small class="text-muted">Leave empty to publish immediately. A future date keeps the page hidden until then.</small></div>
          <div class="col-md-6"><label class="form-label">Featured image</label>
            @if ($page->featured_image)<div class="mb-2 d-flex align-items-center gap-3"><img src="{{ $page->image_url }}" class="thumb-lg" alt=""><div class="form-check"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="rmimg"><label class="form-check-label" for="rmimg">Remove</label></div></div>@endif
            <input type="file" class="form-control mb-2" name="image" accept="image/*"><input class="form-control" name="featured_image_url" placeholder="…or paste an image URL" value="{{ old('featured_image_url') }}"></div>
          <div class="col-12"><label class="form-label d-block">Show on this page</label>
            <div class="d-flex flex-wrap gap-4">
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_lead" value="1" id="sl" @checked(old('_token') ? old('show_lead') : $page->show_lead)><label class="form-check-label" for="sl">Lead form (Get best offers)</label></div>
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_ads" value="1" id="sa" @checked(old('_token') ? old('show_ads') : $page->show_ads)><label class="form-check-label" for="sa">Ads</label></div>
              <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_news" value="1" id="sn" @checked(old('_token') ? old('show_news') : $page->show_news)><label class="form-check-label" for="sn">Latest news</label></div>
            </div>
            <small class="text-muted">Ads are managed in Home settings → Ad banners (tick “Custom pages” on vertical ads).</small></div>
        </div>
      </div>

      {{-- Content --}}
      <div class="tab-pane fade" id="t-content">
        <div class="mb-3"><label class="form-label">Summary / excerpt</label><textarea class="form-control" name="excerpt" rows="2" maxlength="600" placeholder="Shown under the page title">{{ old('excerpt', $page->excerpt) }}</textarea></div>
        <label class="form-label">Body</label>
        <textarea name="body" id="f_body" class="editor">{{ old('body', $page->body) }}</textarea>
      </div>

      @include('admin.partials.seo-fields', ['m' => $page, 'tab' => $tab, 'seoUrl' => url('/').'/'.$page->slug, 'slugInput' => 'f_slug', 'titleInput' => 'f_title'])

      <hr class="my-4">
      <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('admin.pages.index') }}" class="btn btn-label-secondary">Cancel</a>
        <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Save Page</button>
      </div>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
  const $ = id => document.getElementById(id), slugify = s => s.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  const title = $('f_title'), slug = $('f_slug'); let manual = slug.value !== '';
  slug.addEventListener('input', () => manual = slug.value !== '');
  title.addEventListener('input', () => { if (!manual) slug.value = slugify(title.value); window.seoPreview && window.seoPreview(); });
  // editor sizing when its tab opens
  document.querySelector('[data-bs-target="#t-content"]').addEventListener('shown.bs.tab', () => { try { tinymce.activeEditor?.execCommand('mceAutoResize'); } catch (e) {} });
})();
</script>
@endpush
