@extends('admin.layout')
@section('title', 'Reset site data')

@section('content')
<h4 class="mb-1">Reset site data</h4>
<p class="text-muted mb-4">Deletes content and activity so the site starts clean. <b>Users, settings, roles &amp; permissions are never touched</b>, so you stay logged in and the site keeps its configuration.</p>
@include('admin.partials.flash')

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card"><div class="card-header d-flex justify-content-between"><h5 class="mb-0">Tables that will be emptied</h5><span class="badge bg-label-danger">{{ number_format(array_sum($counts)) }} rows</span></div>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Table</th><th class="text-end">Rows</th></tr></thead>
        <tbody>@forelse ($counts as $t => $n)<tr><td><code>{{ $t }}</code></td><td class="text-end">{{ number_format($n) }}</td></tr>@empty<tr><td colspan="2" class="text-muted p-3">Nothing to clear.</td></tr>@endforelse</tbody>
      </table></div>
      <div class="card-footer small text-muted">Always kept: {{ implode(', ', $kept) }}. Uploaded image files are not deleted.</div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card border-danger"><div class="card-body">
      <h5 class="text-danger"><i class="ti ti-alert-triangle me-1"></i>This cannot be undone</h5>
      <form method="GET" action="{{ route('admin.data-reset') }}" class="mb-3">
        <label class="form-label">Also keep (optional)</label>
        @foreach ($optional as $k => $label)
          <div class="form-check"><input class="form-check-input" type="checkbox" name="keep[]" value="{{ $k }}" id="k_{{ $k }}" @checked(in_array($k, $keep)) onchange="this.form.submit()"><label class="form-check-label" for="k_{{ $k }}">{{ $label }}</label></div>
        @endforeach
      </form>
      <form method="POST" action="{{ route('admin.data-reset.run') }}" onsubmit="return confirm('Delete all this data permanently?')">
        @csrf
        @foreach ($keep as $k)<input type="hidden" name="keep[]" value="{{ $k }}">@endforeach
        <div class="mb-3"><label class="form-label">Type <b>RESET</b> to confirm</label><input class="form-control @error('confirm') is-invalid @enderror" name="confirm" autocomplete="off" required>@error('confirm')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="mb-3"><label class="form-label">Your password</label><input type="password" class="form-control @error('password') is-invalid @enderror" name="password" autocomplete="current-password" required>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <button class="btn btn-danger w-100"><i class="ti ti-trash me-1"></i>Delete all data</button>
      </form>
      <p class="small text-muted mt-3 mb-0">Command line: <code>php artisan data:reset</code></p>
    </div></div>
  </div>
</div>
@endsection
