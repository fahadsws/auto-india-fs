@extends('admin.layout')
@section('title', 'AI chats & usage')

@section('content')
@php $pct = $globalLimit ? min(100, round($globalTokens / $globalLimit * 100)) : 0; @endphp
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">AI chats & usage</h4>
  @can('settings.manage')<a href="{{ route('admin.settings') }}" class="btn btn-label-primary"><i class="ti ti-adjustments me-1"></i>Limits & keys</a>@endcan
</div>
<div class="row g-4 mb-4">
  <div class="col-md-6"><div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between"><span class="text-muted">Site-wide tokens today</span><b>{{ number_format($globalTokens) }} / {{ number_format($globalLimit) }}</b></div>
    <div class="progress mt-2" style="height:10px"><div class="progress-bar {{ $pct > 85 ? 'bg-danger' : ($pct > 60 ? 'bg-warning' : 'bg-success') }}" style="width:{{ $pct }}%"></div></div>
    <small class="text-muted">At 100% the assistant answers only from your own site data (no AI spend) until midnight.</small>
  </div></div></div>
  <div class="col-md-2 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted">Chats today</div><h3 class="mb-0">{{ $chatsToday }}</h3></div></div></div>
  <div class="col-md-2 col-6"><div class="card h-100"><div class="card-body"><div class="text-muted">Served free (cache)</div><h3 class="mb-0">{{ $cachedToday }}</h3></div></div></div>
  <div class="col-md-2 col-12"><div class="card h-100"><div class="card-body"><div class="text-muted">Avg rating</div><h3 class="mb-0">{{ $avgRating ?: '–' }}</h3></div></div></div>
</div>

<div class="card mb-4"><div class="table-responsive"><table class="table">
  <thead><tr><th>Visitor</th><th>Contact</th><th>Msgs today</th><th>Tokens today</th><th>Tokens total</th><th>Last active</th><th></th></tr></thead>
  <tbody>
  @forelse ($sessions as $s)
    <tr>
      <td><a href="{{ route('admin.assistant.show', $s) }}" class="fw-medium">{{ $s->lead?->name ?? 'Anonymous #'.$s->id }}</a>
        @if ($s->blocked_until && $s->blocked_until->isFuture())<span class="badge bg-label-danger ms-1">blocked</span>@endif</td>
      <td class="small">{{ $s->lead?->phone }}<br>{{ $s->lead?->email }}</td>
      <td>{{ $s->usage_date?->isToday() ? $s->messages_today : 0 }}</td>
      <td>{{ $s->usage_date?->isToday() ? number_format($s->tokens_today) : 0 }}</td>
      <td>{{ number_format($s->tokens_total) }}</td>
      <td class="small">{{ $s->last_message_at?->diffForHumans() ?? '–' }}</td>
      <td class="text-end"><a class="btn btn-sm btn-label-secondary" href="{{ route('admin.assistant.show', $s) }}">Open</a></td>
    </tr>
  @empty
    <tr><td colspan="7" class="text-center text-muted py-4">No assistant visitors yet.</td></tr>
  @endforelse
  </tbody></table></div>
  <div class="card-footer">{{ $sessions->links() }}</div></div>

<div class="card"><div class="card-header"><h5 class="mb-0">Recent feedback</h5></div><div class="card-body">
  @forelse ($feedback as $f)
    <div class="border-bottom pb-2 mb-2"><b>{{ $f->rating ? str_repeat('★', $f->rating) : '–' }}</b> <span class="badge bg-label-secondary">{{ $f->reason ?: 'no reason' }}</span> <span class="small text-muted">{{ $f->created_at->diffForHumans() }}</span><div>{{ $f->comment }}</div></div>
  @empty <span class="text-muted">No feedback yet.</span> @endforelse
</div></div>
@endsection
