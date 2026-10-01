@extends('admin.layout')
@section('title', 'Header & Footer')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
  <h4 class="mb-0">Header &amp; Footer</h4>
  <a href="{{ route('admin.menus.create', ['location' => $location]) }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Add {{ $location }} item</a>
</div>
<p class="text-muted mb-3">Header: top-level items are nav links; add sub-items to make a dropdown. Footer: top-level items are column headings, sub-items are the links under them.</p>
<ul class="nav nav-pills mb-3">
  <li class="nav-item"><a class="nav-link {{ $location === 'header' ? 'active' : '' }}" href="{{ route('admin.menus.index', ['location' => 'header']) }}">Header</a></li>
  <li class="nav-item"><a class="nav-link {{ $location === 'footer' ? 'active' : '' }}" href="{{ route('admin.menus.index', ['location' => 'footer']) }}">Footer</a></li>
</ul>
<div class="card"><div class="table-responsive">
  <table class="table align-middle mb-0">
    <thead><tr><th>Title</th><th>URL</th><th>Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody>
    @forelse ($items as $p)
      @foreach ([$p, ...$p->children] as $row)
      <tr>
        <td>@if ($row->parent_id)<span class="text-muted ms-4">↳</span> @endif<span class="{{ $row->parent_id ? '' : 'fw-semibold' }}">{{ $row->title }}</span></td>
        <td class="small text-muted">{{ $row->url ?: '—' }}@if ($row->open_new_tab) <i class="ti ti-external-link"></i>@endif</td>
        <td>{{ $row->sort_order }}</td>
        <td><span class="badge bg-label-{{ $row->is_active ? 'success' : 'secondary' }}">{{ $row->is_active ? 'Active' : 'Hidden' }}</span></td>
        <td class="text-end text-nowrap">
          @unless ($row->parent_id)<a class="btn btn-sm btn-label-primary" href="{{ route('admin.menus.create', ['parent' => $row->id]) }}"><i class="ti ti-plus"></i> Sub-item</a>@endunless
          <a class="btn btn-sm btn-icon btn-label-secondary" href="{{ route('admin.menus.edit', $row) }}" title="Edit"><i class="ti ti-edit"></i></a>
          <form method="POST" action="{{ route('admin.menus.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Delete this item{{ $row->parent_id ? '' : ' and all its sub-items' }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-icon btn-label-danger" title="Delete"><i class="ti ti-trash"></i></button></form>
        </td>
      </tr>
      @endforeach
    @empty
      <tr><td colspan="5" class="text-center text-muted py-4">Nothing here yet.</td></tr>
    @endforelse
    </tbody>
  </table>
</div></div>
@endsection
