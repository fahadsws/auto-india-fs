{{-- Shared SEO & Schema + FAQ tab panes (Pages and SEO entries).
     Expects: $m (model with the SEO columns), $tab ('basic'|'seo'|'faq'), $seoUrl (URL shown in the preview).
     Optional: $slugInput / $titleInput (ids of inputs that feed the live preview). --}}
@php
  $faq = old('faq', $m->faq ?? []);
  $slugInput = $slugInput ?? null; $titleInput = $titleInput ?? null;
  $schemaTypes = $schemaTypes ?? \App\Support\SeoRules::SCHEMA_TYPES; $titleMax = $titleMax ?? 120;
@endphp
      {{-- SEO & Schema --}}
      <div class="tab-pane fade {{ $tab === 'seo' ? 'show active' : '' }}" id="t-seo">
        <div class="row g-4">
          <div class="col-lg-7">
            <div class="mb-3"><label class="form-label">Meta title <small class="text-muted" id="cMt"></small></label><input class="form-control" name="meta_title" id="f_mt" maxlength="{{ $titleMax }}" value="{{ old('meta_title', $m->meta_title) }}"><small class="text-muted">Aim for 50–60 characters. Falls back to the page title.</small></div>
            <div class="mb-3"><label class="form-label">Meta description <small class="text-muted" id="cMd"></small></label><textarea class="form-control" name="meta_description" id="f_md" rows="3" maxlength="320">{{ old('meta_description', $m->meta_description) }}</textarea><small class="text-muted">Aim for 120–160 characters. Falls back to the excerpt.</small></div>
            <div class="mb-3"><label class="form-label">Meta keywords</label><input class="form-control" name="meta_keywords" value="{{ old('meta_keywords', $m->meta_keywords) }}" placeholder="car loan, emi, ..."></div>
            <div class="row g-3 mb-3">
              <div class="col-md-7"><label class="form-label">Canonical URL</label><input class="form-control" name="canonical_url" value="{{ old('canonical_url', $m->canonical_url) }}" placeholder="Leave empty to use the page URL"></div>
              <div class="col-md-5"><label class="form-label">Robots</label><select class="form-select" name="robots">@foreach (\App\Support\SeoRules::ROBOTS as $k => $v)<option value="{{ $k }}" @selected(old('robots', $m->robots) === $k)>{{ $v }}</option>@endforeach</select></div>
            </div>
            <h6 class="mt-4">Social sharing (Open Graph)</h6>
            <div class="row g-3 mb-3">
              <div class="col-md-6"><label class="form-label">OG title</label><input class="form-control" name="og_title" value="{{ old('og_title', $m->og_title) }}"></div>
              <div class="col-md-6"><label class="form-label">OG image URL</label><input class="form-control" name="og_image" value="{{ old('og_image', $m->og_image) }}" placeholder="Defaults to the featured image"></div>
              <div class="col-12"><label class="form-label">OG description</label><textarea class="form-control" name="og_description" rows="2" maxlength="320">{{ old('og_description', $m->og_description) }}</textarea></div>
            </div>
            <h6 class="mt-4">Schema (structured data)</h6>
            <div class="mb-3"><label class="form-label">Schema type</label><select class="form-select" name="schema_type">@foreach ($schemaTypes as $k => $v)<option value="{{ $k }}" @selected(old('schema_type', $m->schema_type) === $k)>{{ $v }}</option>@endforeach</select>
              <small class="text-muted">FAQ entries are always added as FAQPage schema. A breadcrumb is added automatically.</small></div>
            <div class="mb-3"><label class="form-label">Custom JSON-LD (optional)</label><textarea class="form-control font-monospace" name="schema_json" rows="6" placeholder='{"@@context":"https://schema.org","@@type":"..."}'>{{ old('schema_json', $m->schema_json) }}</textarea><small class="text-muted">Paste a full JSON-LD object. Must be valid JSON.</small></div>
          </div>
          <div class="col-lg-5">
            <label class="form-label">Search preview</label>
            <div class="border rounded p-3 bg-white" style="max-width:600px">
              <div class="small text-success text-truncate" id="pvUrl">{{ $seoUrl }}</div>
              <div class="fs-5 text-primary" id="pvTitle" style="line-height:1.3"></div>
              <div class="small text-muted" id="pvDesc"></div>
            </div>
          </div>
        </div>
      </div>

      {{-- FAQ --}}
      <div class="tab-pane fade {{ $tab === 'faq' ? 'show active' : '' }}" id="t-faq">
        <p class="text-muted">Questions and answers are shown as an accordion on the page and added to Google as FAQ rich results.</p>
        <div id="faqList">
          @foreach ($faq as $i => $f)
          <div class="border rounded p-3 mb-3 faq-row">
            <div class="d-flex justify-content-between mb-2"><strong class="small text-uppercase text-muted">Question</strong><button type="button" class="btn btn-sm btn-text-danger faq-del"><i class="ti ti-trash"></i></button></div>
            <input class="form-control mb-2" name="faq[{{ $i }}][q]" value="{{ $f['q'] ?? '' }}" maxlength="300" placeholder="Question">
            <textarea class="form-control" name="faq[{{ $i }}][a]" rows="3" maxlength="3000" placeholder="Answer">{{ $f['a'] ?? '' }}</textarea>
          </div>
          @endforeach
        </div>
        <button type="button" class="btn btn-label-primary" id="faqAdd"><i class="ti ti-plus me-1"></i>Add question</button>
      </div>


@push('scripts')
<script>
(() => {
  const $ = id => document.getElementById(id), ti = @json($titleInput), si = @json($slugInput), base = @json($seoUrl);
  const baseRoot = @json(url('/')) + '/';
  window.seoPreview = function () {
    const mt = $('f_mt').value, md = $('f_md').value, t = ti && $(ti) ? $(ti).value : '';
    $('cMt').textContent = '(' + mt.length + ')'; $('cMd').textContent = '(' + md.length + ')';
    $('pvTitle').textContent = mt || t || 'Page title';
    $('pvDesc').textContent = md || 'Meta description will appear here.';
    $('pvUrl').textContent = si && $(si) ? baseRoot + ($(si).value || 'page-slug-url') : base;
  };
  ['f_mt', 'f_md'].concat(si ? [si] : []).forEach(id => $(id) && $(id).addEventListener('input', window.seoPreview)); window.seoPreview();

  const list = $('faqList'); let n = list.children.length + 100;
  $('faqAdd').addEventListener('click', () => {
    const d = document.createElement('div'); d.className = 'border rounded p-3 mb-3 faq-row';
    d.innerHTML = '<div class="d-flex justify-content-between mb-2"><strong class="small text-uppercase text-muted">Question</strong><button type="button" class="btn btn-sm btn-text-danger faq-del"><i class="ti ti-trash"></i></button></div>'
      + '<input class="form-control mb-2" name="faq[' + n + '][q]" maxlength="300" placeholder="Question"><textarea class="form-control" name="faq[' + n + '][a]" rows="3" maxlength="3000" placeholder="Answer"></textarea>';
    n++; list.appendChild(d);
  });
  window.seoSetFaq = function (items) {
    list.innerHTML = '';
    (items || []).forEach(f => { $('faqAdd').click(); const r = list.lastElementChild; r.querySelector('input').value = f.q || ''; r.querySelector('textarea').value = f.a || ''; });
  };
  list.addEventListener('click', e => { const b = e.target.closest('.faq-del'); if (b) b.closest('.faq-row').remove(); });
})();
</script>
@endpush
