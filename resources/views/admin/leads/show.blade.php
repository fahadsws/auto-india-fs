@extends('admin.layout')
@section('title', 'Lead: '.$lead->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><h4 class="mb-0">Lead · {{ $lead->name }}</h4><a href="{{ route('admin.leads.index') }}" class="btn btn-label-secondary">Back</a></div>
<div class="row g-4">
  <div class="col-lg-7"><div class="card"><div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">Type</dt><dd class="col-sm-9"><span class="badge bg-label-primary">{{ $lead->type }}</span></dd>
      <dt class="col-sm-3">Phone</dt><dd class="col-sm-9"><a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a> @if($lead->phone)· <a href="https://wa.me/{{ preg_replace('/\D/', '', $lead->phone) }}" target="_blank" rel="noopener">WhatsApp</a>@endif</dd>
      <dt class="col-sm-3">Email</dt><dd class="col-sm-9">{{ $lead->email ?: '—' }}</dd>
      @if ($lead->listing)<dt class="col-sm-3">Car</dt><dd class="col-sm-9"><a href="{{ $lead->listing->url }}" target="_blank">{{ $lead->listing->title }}</a> · {{ $lead->listing->price_label }}</dd>@endif
      <dt class="col-sm-3">Message</dt><dd class="col-sm-9">{{ $lead->message ?: '—' }}</dd>
      <dt class="col-sm-3">Received</dt><dd class="col-sm-9">{{ $lead->created_at->format('d M Y, H:i') }} <small class="text-muted">({{ $lead->ip }})</small></dd>
    </dl>
  </div></div></div>
  <div class="col-lg-5">
    @can('leads.manage')
    <div class="card"><div class="card-body">
      <form method="POST" action="{{ route('admin.leads.update', $lead) }}">@csrf @method('PUT')
        <div class="mb-3"><label class="form-label">Status</label><select class="form-select" name="status">@foreach (['new', 'contacted', 'won', 'lost'] as $s)<option value="{{ $s }}" @selected($lead->status === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label">Assigned to</label><select class="form-select" name="assigned_to"><option value="">— Unassigned —</option>@foreach ($staff as $u)<option value="{{ $u->id }}" @selected($lead->assigned_to === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <div class="mb-3"><label class="form-label">Internal notes</label><textarea class="form-control" name="notes" rows="5">{{ $lead->notes }}</textarea></div>
        <button class="btn btn-primary">Update lead</button>
      </form>
      <hr><form method="POST" action="{{ route('admin.leads.destroy', $lead) }}" data-confirm="Delete this lead permanently?">@csrf @method('DELETE')<button class="btn btn-sm btn-label-danger">Delete lead</button></form>
    </div></div>
    @endcan
  </div>
</div>
@endsection
