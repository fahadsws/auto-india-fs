{{-- One selectable model in the Trending picker. Expects: $m (VehicleModel), $type, $checked. --}}
<div class="col-md-6 col-lg-4 mb-3">
    <label class="form-check border rounded p-2 h-100">
        <input class="form-check-input ms-0 me-2" type="checkbox" name="trending[{{ $type }}][]" value="{{ $m->id }}" @checked($checked)>
        <span class="form-check-label">{{ $m->full_name }}<small class="d-block text-muted">{{ $m->status_label }}@if ($m->latest_event_at) · {{ $m->latest_event_at->diffForHumans() }}@endif</small></span>
    </label>
</div>
