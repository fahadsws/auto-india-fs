@extends('admin.layout')
@section('title', $item->exists ? 'Edit menu item' : 'Add menu item')

@section('content')
<h4 class="mb-4">{{ $item->exists ? 'Edit menu item' : 'Add menu item' }}</h4>
<div class="card" style="max-width:760px"><div class="card-body">
  @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
  <form method="POST" action="{{ $item->exists ? route('admin.menus.update', $item) : route('admin.menus.store') }}">
    @csrf @if ($item->exists) @method('PUT') @endif
    <div class="mb-3"><label class="form-label">Location</label>
      <select class="form-select" name="location" id="loc">
        <option value="header" @selected(old('location', $item->location) === 'header')>Header</option>
        <option value="footer" @selected(old('location', $item->location) === 'footer')>Footer</option>
      </select></div>
    <div class="mb-3"><label class="form-label">Parent</label>
      <select class="form-select" name="parent_id" id="parent">
        <option value="" data-loc="">— Top level —</option>
        @foreach ($parents as $p)<option value="{{ $p->id }}" data-loc="{{ $p->location }}" @selected(old('parent_id', $item->parent_id) == $p->id)>{{ ucfirst($p->location) }}: {{ $p->title }}</option>@endforeach
      </select>
      <small class="text-muted">Choose a parent to make this a sub-item (dropdown entry in header, link under a heading in footer).</small></div>
    <div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" value="{{ old('title', $item->title) }}" required maxlength="100"></div>
    <div class="mb-3"><label class="form-label">URL</label><input class="form-control" name="url" value="{{ old('url', $item->url) }}" placeholder="/new-cars or https://…"><small class="text-muted">Optional for parents that only group sub-items.</small></div>
    <div class="mb-3"><label class="form-label">Sort order</label><input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}"><small class="text-muted">Lower numbers appear first.</small></div>
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="open_new_tab" value="1" id="nt" @checked(old('open_new_tab', $item->open_new_tab))><label class="form-check-label" for="nt">Open in new tab</label></div>
    <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $item->is_active))><label class="form-check-label" for="act">Active</label></div>
    <button class="btn btn-primary me-2">Save</button><a href="{{ route('admin.menus.index', ['location' => $item->location]) }}" class="btn btn-label-secondary">Cancel</a>
  </form>
</div></div>
@endsection

@push('scripts')<script>
const loc = document.getElementById('loc'), par = document.getElementById('parent');
const sync = () => {
  [...par.options].forEach(o => { if (o.value) { o.hidden = o.dataset.loc !== loc.value; } });
  if (par.selectedOptions[0]?.hidden) par.value = '';
};
loc.onchange = sync; sync();
par.onchange = () => { const o = par.selectedOptions[0]; if (o && o.value) loc.value = o.dataset.loc; sync(); };
</script>@endpush
