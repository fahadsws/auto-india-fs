@extends('admin.layout')
@section('title', 'Dashboard')

@push('styles')<link rel="stylesheet" href="{{ asset('vuexy/vendor/libs/apex-charts/apex-charts.css') }}">@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div><h4 class="mb-0">Hello, {{ auth()->user()->name }} 👋</h4><small class="text-muted">Here's what's happening on {{ config('app.name') }}.</small></div>
  @can('articles.create')<a href="{{ route('admin.articles.create') }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>New article</a>@endcan
</div>

<div class="row g-4 mb-4">
  @php
    $cards = [
      ['Articles', $stats['articles'], $stats['ai_articles'].' by AI', 'ti-news', 'primary'],
      ['Published', $stats['published'], 'live on site', 'ti-world', 'success'],
      ['Active cars', $stats['listings'], 'in marketplace', 'ti-car', 'info'],
      ['New leads', $stats['leads_new'], 'need follow-up', 'ti-messages', 'danger'],
      ['Videos', $stats['videos'], 'in library', 'ti-brand-youtube', 'warning'],
      ['AI chats (7d)', $stats['chats'], $stats['kb'].' items in knowledge base', 'ti-robot', 'secondary'],
    ];
  @endphp
  @foreach ($cards as [$label, $value, $sub, $icon, $color])
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card stat-card h-100"><div class="card-body">
        <div class="d-flex align-items-center mb-2"><div class="avatar me-2"><span class="avatar-initial rounded bg-label-{{ $color }}"><i class="ti {{ $icon }}"></i></span></div></div>
        <h3 class="mb-0">{{ number_format($value) }}</h3><div class="fw-medium">{{ $label }}</div><small class="text-muted">{{ $sub }}</small>
      </div></div>
    </div>
  @endforeach
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="card h-100"><div class="card-header"><h5 class="card-title mb-0">Last 14 days</h5><small class="text-muted">New articles and leads</small></div>
      <div class="card-body"><div id="activityChart"></div></div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-header"><h5 class="card-title mb-0">System health</h5></div>
      <div class="card-body">
        @foreach ($health as $label => $ok)
          <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
            <span>{{ $label }}</span>
            <span class="badge {{ $ok ? 'bg-label-success' : 'bg-label-danger' }}">{{ $ok ? 'OK' : 'Not set' }}</span>
          </div>
        @endforeach
        @can('settings.manage')<a href="{{ route('admin.settings') }}" class="btn btn-sm btn-label-primary mt-3">Open settings</a>@endcan
      </div></div>
  </div>
</div>

<div class="row g-4">
  @can('leads.view')
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header d-flex justify-content-between"><h5 class="card-title mb-0">Latest leads</h5><a href="{{ route('admin.leads.index') }}">View all</a></div>
      <div class="table-responsive"><table class="table">
        <tbody>
        @forelse ($recentLeads as $l)
          <tr><td><a href="{{ route('admin.leads.show', $l) }}" class="fw-medium">{{ $l->name }}</a><br><small class="text-muted">{{ $l->listing?->title ?? ucfirst($l->type) }}</small></td>
              <td>{{ $l->phone }}</td><td><span class="badge bg-label-{{ ['new' => 'danger', 'contacted' => 'warning', 'won' => 'success', 'lost' => 'secondary'][$l->status] }}">{{ $l->status }}</span></td>
              <td class="text-muted small">{{ $l->created_at->diffForHumans() }}</td></tr>
        @empty <tr><td class="text-muted p-4">No leads yet.</td></tr>
        @endforelse
        </tbody></table></div></div>
  </div>
  @endcan
  <div class="col-lg-6">
    <div class="card h-100"><div class="card-header d-flex justify-content-between"><h5 class="card-title mb-0">Recent articles</h5>@can('articles.view')<a href="{{ route('admin.articles.index') }}">View all</a>@endcan</div>
      <div class="table-responsive"><table class="table"><tbody>
        @forelse ($recentArticles as $a)
          <tr><td><a href="{{ route('admin.articles.edit', $a) }}" class="fw-medium">{{ \Illuminate\Support\Str::limit($a->title, 55) }}</a><br><small class="text-muted">{{ $a->category?->name }}</small></td>
              <td><span class="badge bg-label-{{ ['published' => 'success', 'draft' => 'secondary', 'scheduled' => 'info'][$a->status] }}">{{ $a->status }}</span></td>
              <td class="text-muted small">{{ $a->created_at->diffForHumans() }}</td></tr>
        @empty <tr><td class="text-muted p-4">No articles yet.</td></tr>
        @endforelse
      </tbody></table></div></div>
  </div>
  @can('automation.manage')
  <div class="col-12">
    <div class="card"><div class="card-header d-flex justify-content-between"><h5 class="card-title mb-0">Automation activity</h5><a href="{{ route('admin.automation') }}">Open automation</a></div>
      <div class="table-responsive"><table class="table"><tbody>
        @forelse ($logs as $l)
          <tr><td class="fw-medium">{{ \App\Services\Automation::TASKS[$l->task][0] ?? $l->task }}</td>
              <td><span class="badge bg-label-{{ ['ok' => 'success', 'error' => 'danger', 'skipped' => 'secondary'][$l->status] }}">{{ $l->status }}</span></td>
              <td class="small text-muted">{{ \Illuminate\Support\Str::limit($l->message, 110) }}</td><td class="small text-muted">{{ $l->created_at?->diffForHumans() }}</td></tr>
        @empty <tr><td class="text-muted p-4">No automation runs yet. Point a pinger at the cron URL shown on the Automation page.</td></tr>
        @endforelse
      </tbody></table></div></div>
  </div>
  @endcan
</div>
@endsection

@push('scripts')
<script src="{{ asset('vuexy/vendor/libs/apex-charts/apexcharts.js') }}"></script>
<script>
  new ApexCharts(document.querySelector('#activityChart'), {
    chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Public Sans' },
    series: [{ name: 'Articles', data: @json($chart['articles']) }, { name: 'Leads', data: @json($chart['leads']) }],
    xaxis: { categories: @json($chart['labels']), labels: { style: { colors: '#a5a3ae' } } },
    colors: ['#e11d2e', '#28c76f'], stroke: { curve: 'smooth', width: 3 },
    fill: { type: 'gradient', gradient: { opacityFrom: .35, opacityTo: .02 } },
    dataLabels: { enabled: false }, yaxis: { min: 0, forceNiceScale: true, labels: { formatter: v => Math.round(v) } }, legend: { position: 'top' }
  }).render();
</script>
@endpush
