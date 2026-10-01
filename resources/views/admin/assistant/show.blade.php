@extends('admin.layout')
@section('title', 'AI conversation')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="mb-0">{{ $s->lead?->name ?? 'Anonymous #'.$s->id }} <small class="text-muted">{{ $s->lead?->phone }} · {{ $s->lead?->email }}</small></h4>
  <div class="d-flex gap-2">
    <form method="POST" action="{{ route('admin.assistant.reset', $s) }}">@csrf<button class="btn btn-label-secondary">Reset today's usage</button></form>
    <form method="POST" action="{{ route('admin.assistant.block', $s) }}">@csrf<button class="btn btn-label-danger">{{ $s->blocked_until && $s->blocked_until->isFuture() ? 'Unblock' : 'Block' }}</button></form>
    <a class="btn btn-outline-secondary" href="{{ route('admin.assistant.index') }}">Back</a>
  </div>
</div>
<div class="card"><div class="card-body">
  @forelse ($logs as $l)
    <div class="mb-3">
      <div class="small text-muted">{{ $l->created_at->format('d M H:i') }} · {{ $l->source }} · {{ $l->tokens_in + $l->tokens_out }} tokens @if ($l->cached)· cached/local @endif @if ($l->voice)· voice @endif</div>
      <div><b>Q:</b> {{ $l->question }}</div>
      <div><b>A:</b> {{ $l->answer }}</div>
      @php $src = $l->sources ?? null; $mode = $src['mode'] ?? null; @endphp
      <div class="small mt-1">
        @if ($mode === 'database')<span class="badge bg-label-success">From your database</span>@elseif ($mode === 'search')<span class="badge bg-label-info">From your site content</span>@elseif ($mode === 'ai')<span class="badge bg-label-secondary">AI general knowledge</span>@endif
        @foreach (($src['items'] ?? []) as $it)<a class="ms-1" href="{{ $it['url'] ?? '#' }}" target="_blank">{{ $it['title'] ?? '' }}</a>@if (! $loop->last),@endif @endforeach
      </div>
    </div>
  @empty <span class="text-muted">No messages.</span> @endforelse
</div></div>
@endsection
