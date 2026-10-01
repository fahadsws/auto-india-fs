@extends('admin.layout')
@section('title', $listing->exists ? 'Edit listing' : 'Add listing')

@section('content')
<h4 class="mb-4">{{ $listing->exists ? 'Edit listing' : 'Add listing' }}</h4>
<form method="POST" enctype="multipart/form-data" action="{{ $listing->exists ? route('admin.listings.update', $listing) : route('admin.listings.store') }}">
  @csrf @if ($listing->exists) @method('PUT') @endif
  <div class="row g-4">
    <div class="col-lg-8"><div class="card"><div class="card-body"><div class="row g-3">
      <div class="col-12"><label class="form-label">Title</label><input class="form-control" name="title" value="{{ old('title', $listing->title) }}" required placeholder="Hyundai Creta SX Diesel"></div>
      <div class="col-md-6"><label class="form-label">Brand</label><input class="form-control" name="brand" value="{{ old('brand', $listing->brand) }}"></div>
      <div class="col-md-6"><label class="form-label">Model</label><input class="form-control" name="model" value="{{ old('model', $listing->model) }}"></div>
      <div class="col-md-4"><label class="form-label">Year</label><input type="number" class="form-control" name="year" value="{{ old('year', $listing->year) }}"></div>
      <div class="col-md-4"><label class="form-label">Price (₹)</label><input type="number" class="form-control" name="price" value="{{ old('price', $listing->price) }}"></div>
      <div class="col-md-4"><label class="form-label">Km driven</label><input type="number" class="form-control" name="km_driven" value="{{ old('km_driven', $listing->km_driven) }}"></div>
      <div class="col-md-4"><label class="form-label">Fuel</label><select class="form-select" name="fuel"><option value=""></option>@foreach (['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'] as $o)<option @selected(old('fuel', $listing->fuel) === $o)>{{ $o }}</option>@endforeach</select></div>
      <div class="col-md-4"><label class="form-label">Transmission</label><select class="form-select" name="transmission"><option value=""></option>@foreach (['Manual', 'Automatic'] as $o)<option @selected(old('transmission', $listing->transmission) === $o)>{{ $o }}</option>@endforeach</select></div>
      <div class="col-md-4"><label class="form-label">Owner</label><input class="form-control" name="owner" value="{{ old('owner', $listing->owner) }}" placeholder="1st Owner"></div>
      <div class="col-md-6"><label class="form-label">City</label><input class="form-control" name="city" value="{{ old('city', $listing->city) }}"></div>
      <div class="col-md-6"><label class="form-label">Original listing URL</label><input class="form-control" name="source_url" value="{{ old('source_url', $listing->source_url) }}"></div>
      <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="5">{{ old('description', $listing->description) }}</textarea></div>
    </div></div></div></div>
    <div class="col-lg-4">
      <div class="card mb-4"><div class="card-body">
        <label class="form-label">Status</label>
        <select class="form-select mb-3" name="status">@foreach (['active', 'sold', 'hidden'] as $s)<option value="{{ $s }}" @selected(old('status', $listing->status) === $s)>{{ ucfirst($s) }}</option>@endforeach</select>
        <button class="btn btn-primary w-100 mb-2">Save listing</button><a href="{{ route('admin.listings.index') }}" class="btn btn-label-secondary w-100">Cancel</a>
      </div></div>
      <div class="card"><div class="card-body"><label class="form-label">Photo</label>
        @if ($listing->image_path)<img src="{{ $listing->image_url }}" class="thumb-lg mb-3" alt="">@endif
        <input type="file" class="form-control" name="image" accept="image/*"></div></div>
    </div>
  </div>
</form>
@endsection
