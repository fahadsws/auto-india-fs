@extends('admin.layout')
@section('title', 'Automation')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">Automation</h4>
  <form method="POST" action="{{ route('admin.automation.run') }}">@csrf<button class="btn btn-primary"><i class="ti ti-player-play me-1"></i>Run everything that's due</button></form>
</div>

<div class="card mb-4"><div class="card-body">
  <h5><i class="ti ti-link me-1 text-primary"></i>Cron web route</h5>
  <p class="text-muted mb-2">Call this URL every 5-15 minutes from any free pinger (cron-job.org, UptimeRobot, cPanel cron with <code>curl</code>). Each task then runs only when its own interval has elapsed.</p>
  <div class="code-box mb-2">{{ $cronUrl }}</div>
  <div class="small text-muted mb-3">Force a single task: <code>{{ $cronUrl }}/news</code> · tasks: {{ implode(', ', array_keys(\App\Services\Automation::TASKS)) }}</div>
  <form method="POST" action="{{ route('admin.automation.run') }}" data-confirm="The old URL will stop working immediately.">@csrf<input type="hidden" name="action" value="token"><button class="btn btn-sm btn-label-danger">Regenerate secret token</button></form>
</div></div>

<div class="card mb-4"><div class="table-responsive"><table class="table">
  <thead><tr><th>Task</th><th>Interval</th><th>Last run</th><th>Enabled</th><th></th></tr></thead>
  <tbody>
  @foreach ($tasks as $t)
    <tr><td class="fw-medium">{{ $t['label'] }}</td><td>every {{ $t['every'] }} min</td><td class="text-muted">{{ $t['last']?->diffForHumans() ?? 'never' }}</td>
      <td><span class="badge {{ $t['enabled'] ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $t['enabled'] ? 'Yes' : 'No' }}</span></td>
      <td class="text-end"><form method="POST" action="{{ route('admin.automation.run') }}">@csrf<input type="hidden" name="task" value="{{ $t['key'] }}"><button class="btn btn-sm btn-label-primary">Run now</button></form></td></tr>
  @endforeach
  </tbody></table></div>
  <div class="card-footer small text-muted">Change intervals in Settings → Task schedule. News default: every 180 min (3 h).</div></div>

<div class="card dt-wrap">
  <div class="card-header border-bottom">
    <h5 class="card-title mb-3">Run log</h5>
    <div class="row g-3 align-items-end">
      <div class="col-6 col-md-3"><label class="form-label small mb-1">Task</label><select class="dt-filter" name="task"><option value="">All tasks</option>@foreach (\App\Services\Automation::TASKS as $k => $t)<option value="{{ $k }}">{{ $t[0] }}</option>@endforeach</select></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-1">Result</label><select class="dt-filter" name="result" data-search="off"><option value="">Any result</option><option value="ok">OK</option><option value="error">Error</option><option value="skipped">Skipped</option></select></div>
      <div class="col-6 col-md-3"><label class="form-label small mb-1">Date range</label><input class="flatpickr-range dt-filter" name="range" placeholder="Pick a date range"></div>
      <div class="col-auto"><button type="button" class="btn btn-label-secondary dt-reset"><i class="ti ti-refresh me-1"></i>Reset</button></div>
    </div>
  </div>
  @include('admin.partials.dt', ['url' => route('admin.automation.logs'), 'order' => [[0, 'desc']], 'columns' => [
    ['title' => 'When', 'orderable' => true], ['title' => 'Task', 'orderable' => true], ['title' => 'Result', 'orderable' => true], ['title' => 'Message'], ['title' => 'Time', 'orderable' => true],
  ]])
</div>@endsection
