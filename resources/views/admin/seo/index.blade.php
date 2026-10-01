@extends('admin.layout')
@section('title', 'SEO')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div><h4 class="mb-1">SEO</h4><p class="text-muted mb-0">Meta tags, Open Graph, robots, schema and FAQ for the built-in pages. Pages you create have their own SEO tab.</p></div>
  @can('settings.manage')<a href="{{ route('admin.settings') }}" class="btn btn-label-secondary"><i class="ti ti-settings me-1"></i>Global SEO &amp; tracking</a>@endcan
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>Page</th><th>URL</th><th>Meta title</th><th>Robots</th><th>Schema</th><th>FAQ</th><th></th></tr></thead>
  <tbody>
  @foreach (\App\Models\SeoEntry::PAGES as $key => [$label, $route])
    @php($e = $entries->get($key))
    <tr>
      <td class="fw-medium"><a href="{{ route('admin.seo.edit', $key) }}">{{ $label }}</a> @unless ($e)<span class="badge bg-label-secondary ms-1">default</span>@else<span class="badge bg-label-success ms-1">customised</span>@endunless</td>
      <td><code>{{ parse_url(route($route), PHP_URL_PATH) }}</code></td>
      <td class="text-truncate" style="max-width:260px">{{ $e?->meta_title ?: '—' }}</td>
      <td>{!! $e ? \App\Support\Ui::badge($e->robots, $e->isNoindex() ? 'warning' : 'success') : '—' !!}</td>
      <td>{{ $e && $e->schema_type !== 'None' ? $e->schema_type : '—' }}</td>
      <td>{{ $e ? count($e->faqItems()) : '—' }}</td>
      <td class="text-end text-nowrap"><a href="{{ route($route) }}" target="_blank" rel="noopener" class="btn btn-sm btn-icon btn-text-secondary" title="View"><i class="ti ti-eye"></i></a><a href="{{ route('admin.seo.edit', $key) }}" class="btn btn-sm btn-icon btn-text-secondary" title="Edit"><i class="ti ti-edit"></i></a></td>
    </tr>
  @endforeach
  </tbody>
</table></div></div>
@endsection
