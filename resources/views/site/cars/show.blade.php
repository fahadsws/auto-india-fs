@extends('site.layout')
@section('title', $listing->title.' — '.$listing->price_label.' | '.\App\Models\Setting::get('site.name'))
@section('description', \Illuminate\Support\Str::limit(strip_tags($listing->description ?: $listing->title.' used car in '.$listing->city), 160, ''))
@section('image', $listing->image_url)

@push('head')
<style>
  .car-photo button{display:block;width:100%;padding:0;border:0;background:transparent;cursor:zoom-in}.car-photo button:focus-visible{outline:3px solid var(--red);outline-offset:3px;border-radius:10px}
  .image-viewer[hidden]{display:none}.image-viewer{position:fixed;z-index:1000;inset:0;background:rgba(8,10,16,.94);display:flex;align-items:center;justify-content:center;padding:32px}.image-viewer__image{max-width:min(1100px,90vw);max-height:82vh;object-fit:contain;border-radius:8px}.image-viewer__close{position:absolute;top:18px;right:22px;width:44px;height:44px;border:0;border-radius:50%;color:#fff;background:rgba(255,255,255,.14);cursor:pointer;font-size:28px}
</style>
<script type="application/ld+json">{!! json_encode(array_filter(['@context' => 'https://schema.org', '@type' => 'Car', 'name' => $listing->title, 'brand' => $listing->brand, 'model' => $listing->model, 'vehicleModelDate' => $listing->year, 'mileageFromOdometer' => $listing->km_driven ? ['@type' => 'QuantitativeValue', 'value' => $listing->km_driven, 'unitCode' => 'KMT'] : null, 'fuelType' => $listing->fuel, 'image' => $listing->image_url, 'offers' => $listing->price ? ['@type' => 'Offer', 'price' => $listing->price, 'priceCurrency' => 'INR', 'availability' => 'https://schema.org/InStock'] : null]), JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
<div class="w"><div class="car-wrap">
  <div>
    <div class="car-photo"><button type="button" class="car-image-trigger" aria-label="Open {{ $listing->title }} photo"><img src="{{ $listing->image_url }}" alt="{{ $listing->title }}"></button></div>
    @if ($listing->vehicleModel && in_array($listing->vehicleModel->status, ['facelift', 'launched']) && $listing->vehicleModel->latest_event_at?->gt(now()->subDays(90)))
      <a href="{{ $listing->vehicleModel->url }}" class="alert alert-err" style="display:block;margin:16px 0 0"><i class="ti ti-bolt"></i> {{ $listing->vehicleModel->status === 'facelift' ? 'A facelifted' : 'The new' }} {{ $listing->vehicleModel->full_name }} was recently launched — compare it with this used one →</a>
    @endif
    <h1 class="h" style="margin-top:24px;font-size:clamp(26px,3.4vw,38px)">{{ $listing->title }}</h1>
    <div class="price" style="font-size:1.8rem">{{ $listing->price_label }}</div>
    <table class="spec-table">
      @foreach (['Year' => $listing->year, 'Kilometres driven' => $listing->km_driven ? number_format($listing->km_driven).' km' : null, 'Fuel' => $listing->fuel, 'Transmission' => $listing->transmission, 'Ownership' => $listing->owner, 'Location' => $listing->city, 'Brand' => $listing->brand, 'Model' => $listing->model] as $k => $v)
        @if ($v)<tr><td>{{ $k }}</td><td>{{ $v }}</td></tr>@endif
      @endforeach
    </table>
    @if ($listing->description)<h3>About this car</h3><div class="prose">{!! nl2br(e($listing->description)) !!}</div>@endif
    <p class="m" style="margin-top:18px">Please verify the vehicle, documents and price with the seller before buying.@if($listing->source_name && $listing->source_name !== 'Demo') Listed via {{ $listing->source_name }}.@endif</p>
  </div>

  <div>
    <div class="aside-box sticky-form">
      <h3>Interested in this car?</h3><p class="m">Leave your details and our sales team will call you.</p>
      @if (session('success'))<div class="alert alert-ok">{{ session('success') }}</div>@endif
      @if ($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif
      <form method="POST" action="{{ route('cars.enquire', $listing) }}">@csrf
        <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
        <div class="field"><label>Your name</label><input name="name" value="{{ old('name') }}" required></div>
        <div class="field"><label>Phone</label><input name="phone" type="tel" value="{{ old('phone') }}" required placeholder="+91"></div>
        <div class="field"><label>Email (optional)</label><input name="email" type="email" value="{{ old('email') }}"></div>
        <div class="field"><label>Message (optional)</label><textarea name="message" rows="3" placeholder="I'd like a test drive…">{{ old('message') }}</textarea></div>
        <button class="go btn-block"><i class="ti ti-send"></i> Send enquiry</button>
      </form>
      <hr style="border:0;border-top:1px solid var(--line);margin:18px 0">
      <button class="btn-ghost btn-block" data-ask="Tell me about the {{ $listing->title }} and whether it's a good buy"><i class="ti ti-sparkles"></i> Ask AI about this car</button>
    </div>
  </div>
</div>

@if ($similar->isNotEmpty())
  <div class="sh"><h2 class="h">Similar cars</h2></div>
  <div class="grid">@foreach ($similar as $s)@include('site.partials.cards', ['type' => 'car', 'item' => $s])@endforeach</div>
@endif
<div class="image-viewer" id="image-viewer" hidden role="dialog" aria-modal="true" aria-label="Photo viewer">
  <button type="button" class="image-viewer__close" aria-label="Close photo viewer">&times;</button>
  <img class="image-viewer__image" alt="">
</div>
</div>
@endsection

@push('head')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const viewer = document.querySelector('#image-viewer'), trigger = document.querySelector('.car-image-trigger');
  if (!viewer || !trigger) return;
  const image = viewer.querySelector('.image-viewer__image'), closeButton = viewer.querySelector('.image-viewer__close'); let previousFocus;
  const close = () => { viewer.hidden = true; document.body.style.overflow = ''; previousFocus?.focus(); };
  trigger.addEventListener('click', () => { previousFocus = trigger; image.src = trigger.querySelector('img').currentSrc || trigger.querySelector('img').src; image.alt = @json($listing->title); viewer.hidden = false; document.body.style.overflow = 'hidden'; closeButton.focus(); });
  closeButton.addEventListener('click', close); viewer.addEventListener('click', event => { if (event.target === viewer) close(); });
  document.addEventListener('keydown', event => { if (!viewer.hidden && event.key === 'Escape') close(); });
});
</script>
@endpush
