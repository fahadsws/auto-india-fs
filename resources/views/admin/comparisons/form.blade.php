@extends('admin.layout')
@section('title', $comparison->exists ? 'Edit comparison' : 'Add comparison')

@php
  $opts = fn ($sel) => $cars->map(fn ($c) => '<option value="'.$c->id.'"'.((string) $sel === (string) $c->id ? ' selected' : '').'>'.e($c->full_name).($c->is_published ? '' : ' (hidden)').'</option>')->implode('');
@endphp
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <h4 class="mb-0">{{ $comparison->exists ? 'Edit comparison' : 'Add comparison' }}</h4>
  @if ($comparison->exists)<a href="{{ $comparison->url }}" target="_blank" class="btn btn-label-secondary"><i class="ti ti-eye me-1"></i>View page</a>@endif
</div>
<form method="POST" action="{{ $comparison->exists ? route('admin.comparisons.update', $comparison) : route('admin.comparisons.store') }}">
  @csrf @if ($comparison->exists) @method('PUT') @endif
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card"><div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Car A</label><select name="car_a_id" required><option value="">Select a car</option>{!! $opts(old('car_a_id', $comparison->car_a_id)) !!}</select>@error('car_a_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label">Car B</label><select name="car_b_id" required><option value="">Select a car</option>{!! $opts(old('car_b_id', $comparison->car_b_id)) !!}</select>@error('car_b_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label class="form-label">Custom title <span class="text-muted">(optional, defaults to "A vs B")</span></label><input class="form-control" name="title" maxlength="160" value="{{ old('title', $comparison->title) }}" placeholder="Hyundai Creta vs Kia Seltos: which compact SUV should you buy?"></div>
        <div class="col-12"><label class="form-label">Intro</label><textarea class="form-control" name="intro" rows="3" maxlength="2000">{{ old('intro', $comparison->intro) }}</textarea></div>
        <div class="col-12"><label class="form-label">Our verdict</label><textarea class="form-control" name="verdict" rows="5" maxlength="5000">{{ old('verdict', $comparison->verdict) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Our pick</label><select name="winner_id"><option value="">No pick</option>{!! $opts(old('winner_id', $comparison->winner_id)) !!}</select><div class="form-text">Must be one of the two cars above.</div>@error('winner_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</div>
        <div class="col-md-6"><label class="form-label">Sort order</label><input type="number" min="0" class="form-control" name="sort_order" value="{{ old('sort_order', $comparison->sort_order ?? 0) }}"><div class="form-text">Lower numbers first (featured pairs always lead).</div></div>
        <div class="col-md-6"><label class="form-label">Meta title</label><input class="form-control" name="meta_title" maxlength="70" value="{{ old('meta_title', $comparison->meta_title) }}"></div>
        <div class="col-md-6"><label class="form-label">Meta description</label><input class="form-control" name="meta_description" maxlength="320" value="{{ old('meta_description', $comparison->meta_description) }}"></div>
      </div></div></div>
    </div>
    <div class="col-lg-4">
      <div class="card"><div class="card-body">
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $comparison->is_active ?? true))><label class="form-check-label" for="act">Show as a suggestion</label></div>
        <div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="feat" @checked(old('is_featured', $comparison->is_featured))><label class="form-check-label" for="feat">Featured (shown first, on the home page)</label></div>
        <button class="btn btn-primary me-2">Save</button><a href="{{ route('admin.comparisons.index') }}" class="btn btn-label-secondary">Cancel</a>
      </div></div>
    </div>
  </div>
</form>
@endsection
