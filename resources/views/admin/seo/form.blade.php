@extends('admin.layout')
@section('title', 'SEO — '.$label)

@section('content')
@php($tab = $errors->has('faq.*') ? 'faq' : 'seo')
<form method="POST" action="{{ route('admin.seo.update', $key) }}" id="seoForm">
  @csrf @method('PUT')
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div class="d-flex align-items-center gap-3">
      <a href="{{ route('admin.seo.index') }}" class="btn btn-label-secondary"><i class="ti ti-arrow-left me-1"></i>Back</a>
      <h4 class="mb-0">SEO — {{ $label }}</h4>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-label-secondary"><i class="ti ti-eye me-1"></i>View page</a>
      <button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Save SEO</button>
    </div>
  </div>
  @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
  <div class="alert alert-info py-2">Leave a field empty to keep the page's built-in value. Only filled fields override.</div>

  <div class="card">
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item"><button class="nav-link {{ $tab === 'seo' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#t-seo"><i class="ti ti-world me-2"></i>SEO &amp; Schema</button></li>
      <li class="nav-item"><button class="nav-link {{ $tab === 'faq' ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#t-faq"><i class="ti ti-help-circle me-2"></i>FAQ</button></li>
    </ul>
    <div class="card-body tab-content pt-4">
      @include('admin.partials.seo-fields', ['m' => $entry, 'tab' => $tab, 'seoUrl' => $url])
      <hr class="my-4">
      <div class="d-flex justify-content-between gap-2">
        <div>@if ($entry->exists)<button class="btn btn-label-danger" type="submit" form="seoReset" onclick="return confirm('Remove all custom SEO for this page?')"><i class="ti ti-restore me-1"></i>Reset to defaults</button>@endif</div>
        <div class="d-flex gap-2"><a href="{{ route('admin.seo.index') }}" class="btn btn-label-secondary">Cancel</a><button class="btn btn-primary" type="submit"><i class="ti ti-device-floppy me-1"></i>Save SEO</button></div>
      </div>
    </div>
  </div>
</form>
@if ($entry->exists)<form id="seoReset" method="POST" action="{{ route('admin.seo.destroy', $key) }}">@csrf @method('DELETE')</form>@endif
@endsection
