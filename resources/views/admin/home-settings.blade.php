@extends('admin.layout')
@section('title', 'Home settings')

@php
    $banners = $settings->hero_banners ?: [[]];
    $collections = $settings->collections ?: [[]];
    $ads = $settings->ads ?: [[]];
    $pages = \App\Models\HomeSetting::AD_PAGES;

    // One image field: paste a URL or upload; `$name` is the field group (banners, collections, ads), `$fileName` its upload bucket.
    $imageField = fn (string $name, string $fileName, string $i, ?string $value, string $label) => '<div class="col-12"><label class="form-label d-block">'.e($label).'</label>'
        .($value ? '<img src="'.e($value).'" alt="" class="img-thumbnail mb-2 d-block" style="max-height:70px">' : '')
        .'<div class="btn-group btn-group-sm image-source-tabs"><button type="button" class="btn btn-outline-secondary active" data-image-source="url">Image URL</button><button type="button" class="btn btn-outline-secondary" data-image-source="upload">Upload</button></div>'
        .'<div class="mt-2" data-image-panel="url"><input class="form-control" name="'.$name.'['.$i.'][image]" value="'.e($value).'" placeholder="https://example.com/image.jpg"></div>'
        .'<div class="mt-2 d-none" data-image-panel="upload"><input class="form-control" type="file" name="'.$fileName.'['.$i.']" accept="image/*"></div></div>';
    $removeBtn = '<button type="button" class="btn btn-sm btn-icon btn-label-danger remove-row" title="Remove"><i class="ti ti-trash"></i></button>';
@endphp

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Home settings</h4>
            <p class="text-muted mb-0">Manage the content blocks shown on your homepage and the ads around the site.</p>
        </div>
        <div class="d-flex gap-2">
            @can('seo.manage')<a href="{{ route('admin.seo.edit', 'home') }}" class="btn btn-label-primary"><i class="ti ti-seo me-1"></i>Home page SEO</a>@endcan
            <a href="{{ route('home') }}" target="_blank" class="btn btn-label-secondary"><i class="ti ti-external-link me-1"></i>View homepage</a>
        </div>
    </div>

    <form id="home-settings-form" method="POST" enctype="multipart/form-data" action="{{ route('admin.home-settings.update') }}">
        @csrf @method('PUT')
        <ul class="nav nav-pills mb-4" role="tablist">
            <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="pill" data-bs-target="#home-banners"><i class="ti ti-photo me-1"></i>Banners</button></li>
            <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#home-ads"><i class="ti ti-ad-2 me-1"></i>Ad banners</button></li>
            <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#home-trending"><i class="ti ti-flame me-1"></i>Trending</button></li>
            <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#home-collections"><i class="ti ti-layout-grid me-1"></i>Collections</button></li>
        </ul>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="tab-content" style="padding:0">

                    {{-- Hero banners --}}
                    <div class="tab-pane fade show active" id="home-banners">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <div><h5 class="mb-0">Hero banners</h5><small class="text-muted">Slides in the homepage hero. Upload an image or paste a URL.</small></div>
                                <button type="button" class="btn btn-sm btn-label-primary add-row" data-target="banner-list" data-template="banner-tpl"><i class="ti ti-plus me-1"></i>Add banner</button>
                            </div>
                            <div class="card-body repeat-grid" id="banner-list">
                                @foreach ($banners as $i => $b)
                                    <div class="repeat-card">
                                        <div class="d-flex justify-content-between mb-3"><strong>Banner</strong>{!! $removeBtn !!}</div>
                                        <div class="row g-3">
                                            <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="banners[{{ $i }}][title]" value="{{ $b['title'] ?? '' }}"></div>
                                            <div class="col-md-4"><label class="form-label">Tag</label><input class="form-control" name="banners[{{ $i }}][tag]" value="{{ $b['tag'] ?? '' }}" placeholder="Featured"></div>
                                            <div class="col-12"><label class="form-label">Redirect URL</label><input class="form-control" name="banners[{{ $i }}][url]" value="{{ $b['url'] ?? '' }}" placeholder="/new-cars"></div>
                                            {!! $imageField('banners', 'banner_upload', (string) $i, $b['image'] ?? null, 'Banner image') !!}
                                            <div class="col-12"><label class="form-label">Supporting text</label><input class="form-control" name="banners[{{ $i }}][text]" value="{{ $b['text'] ?? '' }}"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <template id="banner-tpl">
                            <div class="repeat-card">
                                <div class="d-flex justify-content-between mb-3"><strong>Banner</strong>{!! $removeBtn !!}</div>
                                <div class="row g-3">
                                    <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="banners[__i__][title]"></div>
                                    <div class="col-md-4"><label class="form-label">Tag</label><input class="form-control" name="banners[__i__][tag]" placeholder="Featured"></div>
                                    <div class="col-12"><label class="form-label">Redirect URL</label><input class="form-control" name="banners[__i__][url]" placeholder="/new-cars"></div>
                                    {!! $imageField('banners', 'banner_upload', '__i__', null, 'Banner image') !!}
                                    <div class="col-12"><label class="form-label">Supporting text</label><input class="form-control" name="banners[__i__][text]"></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Ad banners --}}
                    <div class="tab-pane fade" id="home-ads">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <div>
                                    <h5 class="mb-0">Ad banners</h5>
<small class="text-muted">
  <b>Horizontal:</b> Below the homepage hero (1200×250). 
  <b>Vertical:</b> Sidebar ads (300×600).
</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-label-primary add-row" data-target="ad-list" data-template="ad-tpl"><i class="ti ti-plus me-1"></i>Add ad</button>
                            </div>
                            <div class="card-body repeat-grid" id="ad-list">
                                @foreach ($ads as $i => $a)
                                    <div class="repeat-card ad-row">
                                        <div class="d-flex justify-content-between mb-3"><strong>Ad banner</strong>{!! $removeBtn !!}</div>
                                        <div class="row g-3">
                                            <div class="col-md-6"><label class="form-label">Name (internal)</label><input class="form-control" name="ads[{{ $i }}][title]" value="{{ $a['title'] ?? '' }}"></div>
                                            <div class="col-md-6"><label class="form-label">Click URL</label><input class="form-control" name="ads[{{ $i }}][url]" value="{{ $a['url'] ?? '' }}" placeholder="https://advertiser.com"></div>
                                            <div class="col-md-6"><label class="form-label">Orientation</label>
                                                <select class="form-select ad-orientation" name="ads[{{ $i }}][orientation]">
                                                    <option value="horizontal" @selected(($a['orientation'] ?? 'horizontal') === 'horizontal')>Horizontal — under home hero</option>
                                                    <option value="vertical" @selected(($a['orientation'] ?? '') === 'vertical')>Vertical — sidebar on pages</option>
                                                </select></div>
                                            <div class="col-md-6 d-flex align-items-end"><label class="form-check mb-2"><input type="checkbox" class="form-check-input" name="ads[{{ $i }}][active]" value="1" @checked($a['active'] ?? true)><span class="form-check-label">Active</span></label></div>
                                            <div class="col-12 ad-pages {{ ($a['orientation'] ?? '') === 'vertical' ? '' : 'd-none' }}"><label class="form-label d-block">Show on</label>
                                                @foreach ($pages as $k => $label)<label class="form-check form-check-inline"><input type="checkbox" class="form-check-input" name="ads[{{ $i }}][pages][]" value="{{ $k }}" @checked(in_array($k, $a['pages'] ?? [], true))><span class="form-check-label">{{ $label }}</span></label>@endforeach
                                            </div>
                                            {!! $imageField('ads', 'ad_upload', (string) $i, $a['image'] ?? null, 'Ad image') !!}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <template id="ad-tpl">
                            <div class="repeat-card ad-row">
                                <div class="d-flex justify-content-between mb-3"><strong>Ad banner</strong>{!! $removeBtn !!}</div>
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label">Name (internal)</label><input class="form-control" name="ads[__i__][title]"></div>
                                    <div class="col-md-6"><label class="form-label">Click URL</label><input class="form-control" name="ads[__i__][url]" placeholder="https://advertiser.com"></div>
                                    <div class="col-md-6"><label class="form-label">Orientation</label>
                                        <select class="form-select ad-orientation" name="ads[__i__][orientation]">
                                            <option value="horizontal">Horizontal — under home hero</option>
                                            <option value="vertical">Vertical — sidebar on pages</option>
                                        </select></div>
                                    <div class="col-md-6 d-flex align-items-end"><label class="form-check mb-2"><input type="checkbox" class="form-check-input" name="ads[__i__][active]" value="1" checked><span class="form-check-label">Active</span></label></div>
                                    <div class="col-12 ad-pages d-none"><label class="form-label d-block">Show on</label>
                                        @foreach ($pages as $k => $label)<label class="form-check form-check-inline"><input type="checkbox" class="form-check-input" name="ads[__i__][pages][]" value="{{ $k }}"><span class="form-check-label">{{ $label }}</span></label>@endforeach
                                    </div>
                                    {!! $imageField('ads', 'ad_upload', '__i__', null, 'Ad image') !!}
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Trending per vehicle type --}}
                    <div class="tab-pane fade" id="home-trending">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Trending models</h5>
                                <small class="text-muted">Pick what shows under “What everyone’s looking at”. Your current picks stay on top (even if old), followed by the 10 latest models.</small>
                                <ul class="nav nav-tabs mt-3">
                                    @foreach ($pickers as $type => $p)
                                        <li class="nav-item"><button type="button" class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#trend-{{ $type }}">{{ $p['label'] }} <span class="badge bg-label-secondary">{{ $p['selected']->count() }}</span></button></li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content p-0">
                                    @foreach ($pickers as $type => $p)
                                        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="trend-{{ $type }}">
                                            @if ($p['selected']->isNotEmpty())
                                                <h6 class="text-muted small text-uppercase">Selected</h6>
                                                <div class="row">@foreach ($p['selected'] as $m)@include('admin.partials.trending-option', ['m' => $m, 'type' => $type, 'checked' => true])@endforeach</div>
                                            @endif
                                            <h6 class="text-muted small text-uppercase mt-2">Latest {{ strtolower($p['label']) }}</h6>
                                            <div class="row">@forelse ($p['recent'] as $m)@include('admin.partials.trending-option', ['m' => $m, 'type' => $type, 'checked' => false])@empty<p class="text-muted">No published {{ strtolower($p['label']) }} yet.</p>@endforelse</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Collections --}}
                    <div class="tab-pane fade" id="home-collections">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between">
                                <div><h5 class="mb-0">Curated collections</h5><small class="text-muted">Each collection has a title, image and redirect URL.</small></div>
                                <button type="button" class="btn btn-sm btn-label-primary add-row" data-target="collection-list" data-template="collection-tpl"><i class="ti ti-plus me-1"></i>Add collection</button>
                            </div>
                            <div class="card-body repeat-grid" id="collection-list">
                                @foreach ($collections as $i => $c)
                                    <div class="repeat-card">
                                        <div class="d-flex justify-content-between mb-3"><strong>Collection</strong>{!! $removeBtn !!}</div>
                                        <div class="row g-3">
                                            <div class="col-12"><label class="form-label">Title</label><input class="form-control" name="collections[{{ $i }}][title]" value="{{ $c['title'] ?? '' }}"></div>
                                            <div class="col-12"><label class="form-label">Redirect URL</label><input class="form-control" name="collections[{{ $i }}][url]" value="{{ $c['url'] ?? '' }}"></div>
                                            {!! $imageField('collections', 'collection_upload', (string) $i, $c['image'] ?? null, 'Collection image') !!}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <template id="collection-tpl">
                            <div class="repeat-card">
                                <div class="d-flex justify-content-between mb-3"><strong>Collection</strong>{!! $removeBtn !!}</div>
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label">Title</label><input class="form-control" name="collections[__i__][title]"></div>
                                    <div class="col-12"><label class="form-label">Redirect URL</label><input class="form-control" name="collections[__i__][url]"></div>
                                    {!! $imageField('collections', 'collection_upload', '__i__', null, 'Collection image') !!}
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card home-settings-summary">
                    <div class="card-header"><h5 class="mb-0">Homepage setup</h5></div>
                    <div class="card-body">
                        <p class="small text-muted">Changes go live as soon as you save.</p>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary js-save"><i class="ti ti-device-floppy me-1"></i>Save changes</button>
                            <a href="{{ route('home') }}" target="_blank" class="btn btn-label-secondary">Preview homepage</a>
                        </div>
                        <hr>
                        <div class="small">
                            <div class="d-flex justify-content-between mb-2"><span>Hero banners</span><strong>{{ count($settings->hero_banners ?? []) }}</strong></div>
                            <div class="d-flex justify-content-between mb-2"><span>Ad banners</span><strong>{{ count($settings->ads ?? []) }}</strong></div>
                            @foreach ($pickers as $p)<div class="d-flex justify-content-between mb-2"><span>Trending {{ strtolower($p['label']) }}</span><strong>{{ $p['selected']->count() }}</strong></div>@endforeach
                            <div class="d-flex justify-content-between"><span>Collections</span><strong>{{ count($settings->collections ?? []) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('styles')
        <style>
            .repeat-card { border: 1px solid var(--bs-border-color); border-radius: .5rem; padding: 1rem; background: var(--bs-body-bg) }
            .repeat-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem }
            @media (max-width: 900px) { .repeat-grid { grid-template-columns: 1fr } }
            .home-settings-summary { position: sticky; top: 1.5rem }
        </style>
    @endpush
    @push('scripts')
        <script>
        (() => {
            const form = document.getElementById('home-settings-form');
            // Each new row gets a unique index so its fields (and file upload) stay together on the server.
            const next = {};
            document.querySelectorAll('.repeat-grid').forEach(g => next[g.id] = g.children.length + 1000);

            document.addEventListener('click', e => {
                const add = e.target.closest('.add-row');
                if (add) {
                    e.preventDefault();
                    const tpl = document.getElementById(add.dataset.template).innerHTML.replaceAll('__i__', next[add.dataset.target]++);
                    document.getElementById(add.dataset.target).insertAdjacentHTML('beforeend', tpl);
                    return;
                }
                const rm = e.target.closest('.remove-row');
                if (rm) { e.preventDefault(); rm.closest('.repeat-card').remove(); return; }
                const tab = e.target.closest('[data-image-source]');
                if (tab) {
                    const box = tab.closest('.col-12');
                    box.querySelectorAll('[data-image-source]').forEach(b => b.classList.toggle('active', b === tab));
                    box.querySelectorAll('[data-image-panel]').forEach(p => p.classList.toggle('d-none', p.dataset.imagePanel !== tab.dataset.imageSource));
                }
            });
            // "Show on" only applies to vertical ads.
            document.addEventListener('change', e => {
                if (!e.target.classList.contains('ad-orientation')) return;
                e.target.closest('.ad-row').querySelector('.ad-pages').classList.toggle('d-none', e.target.value !== 'vertical');
            });
            form.addEventListener('submit', () => {
                const btn = form.querySelector('.js-save');
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Saving…';
            });
        })();
        </script>
    @endpush
@endsection
