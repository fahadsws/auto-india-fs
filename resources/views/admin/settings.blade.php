@extends('admin.layout')
@section('title', 'Settings')

@section('content')
<h4 class="mb-4">Settings</h4>
<form method="POST" action="{{ route('admin.settings.update') }}">
  @csrf @method('PUT')
  <div class="row g-4">
    <div class="col-lg-3">
      <div class="nav flex-column nav-pills" role="tablist">
        @foreach ($groups as $name => [$icon])
          <button type="button" class="nav-link text-start {{ $loop->first ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#g{{ $loop->index }}"><i class="ti {{ $icon }} me-2"></i>{{ $name }}</button>
        @endforeach
      </div>
    </div>
    <div class="col-lg-9">
      <div class="tab-content p-0 shadow-none bg-transparent">
        @foreach ($groups as $name => [$icon, $fields])
          <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="g{{ $loop->index }}">
            <div class="card"><div class="card-header"><h5 class="card-title mb-0">{{ $name }}</h5></div><div class="card-body">
              @foreach ($fields as [$key, $label, $type, $help])
                @php $name_ = str_replace('.', '__', $key); $val = old($name_, $values[$key]); @endphp
                <div class="mb-3">
                  @if ($type === 'bool')
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="{{ $name_ }}" value="1" id="{{ $name_ }}" @checked((string) $val === '1' || ($val === null && in_array($key, ['assistant.enabled', 'assistant.require_lead', 'assistant.otp_required', 'news.auto_publish', 'youtube.auto_attach']) || ($val === null && str_starts_with($key, 'cron.enabled'))))><label class="form-check-label" for="{{ $name_ }}">{{ $label }}</label></div>
                  @else
                    <label class="form-label" for="{{ $name_ }}">{{ $label }}</label>
                    @if ($type === 'textarea')<textarea class="form-control" rows="3" name="{{ $name_ }}" id="{{ $name_ }}">{{ $val }}</textarea>
                    @elseif ($type === 'secret')<input type="password" class="form-control" name="{{ $name_ }}" id="{{ $name_ }}" value="{{ $val }}" autocomplete="new-password" placeholder="Not set">
                    @else<input type="{{ $type === 'number' ? 'number' : 'text' }}" class="form-control" name="{{ $name_ }}" id="{{ $name_ }}" value="{{ $val }}">@endif
                  @endif
                  @if ($help)<div class="form-text">{{ $help }}</div>@endif
                </div>
              @endforeach
            </div></div>
          </div>
        @endforeach
      </div>
      <div class="mt-4 d-flex flex-wrap gap-2">
        <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save settings</button>
        <button type="button" class="btn btn-label-primary" id="runCheck"><i class="ti ti-stethoscope me-1"></i>Run setup check</button>
        <button type="button" class="btn btn-label-secondary" id="runReindex"><i class="ti ti-database-import me-1"></i>Rebuild knowledge base now</button>
      </div>
      <div class="card mt-3 d-none" id="checkCard"><div class="card-header"><h5 class="card-title mb-0">Setup check</h5><small class="text-muted">Save your settings first, then run the check. It tests the AI provider and plays a sample in your saved ElevenLabs voice.</small></div>
        <div class="card-body" id="checkBody"></div></div>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('runReindex').addEventListener('click', async function () {
  const btn = this, old = btn.innerHTML; btn.disabled = true; btn.textContent = 'Rebuilding…';
  try {
    const r = await fetch(@json(route('admin.settings.reindex')), { method: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' } });
    const d = await r.json(); btn.textContent = d.ok ? 'Done - ' + d.items + ' items indexed' : 'Failed';
  } catch (e) { btn.textContent = 'Failed: ' + e.message; }
  setTimeout(() => { btn.innerHTML = old; btn.disabled = false; }, 3500);
});
document.getElementById('runCheck').addEventListener('click', async function () {
  const btn = this, card = document.getElementById('checkCard'), body = document.getElementById('checkBody');
  btn.disabled = true; card.classList.remove('d-none'); body.innerHTML = '<div class="text-muted">Checking… (this calls your AI and voice providers once)</div>';
  try {
    const r = await fetch(@json(route('admin.settings.check')), { method: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' } });
    const d = await r.json(); const col = { ok: 'success', warn: 'warning', fail: 'danger' }, ic = { ok: 'check', warn: 'alert-triangle', fail: 'x' };
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    body.innerHTML = d.rows.map(x => '<div class="d-flex gap-2 mb-3"><span class="badge bg-label-' + col[x.status] + ' p-2 align-self-start"><i class="ti ti-' + ic[x.status] + '"></i></span><div><b>' + esc(x.label) + '</b><div class="text-muted small">' + esc(x.detail) + '</div>' + (x.audio ? '<audio controls class="mt-2" src="' + x.audio + '"></audio>' : '') + '</div></div>').join('');
  } catch (e) { body.innerHTML = '<div class="text-danger">The check could not run: ' + e.message + '</div>'; }
  btn.disabled = false;
});
</script>
@endpush
