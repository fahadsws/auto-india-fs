@extends('site.layout')
@section('title', 'Sell Your Car — Get the Best Offer | '.\App\Models\Setting::get('site.name'))
@section('description', 'Tell us about your car and our team will call you with the best offer.')

@section('content')
@include('site.partials.crumb', ['title' => 'Sell your car', 'trail' => []])
<div class="w" style="max-width:720px;padding-top:40px">
  <div class="aside-box">
    @if (session('success'))<div class="alert alert-ok">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('sell.submit') }}">@csrf
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px"><div class="field"><label>Your name</label><input name="name" value="{{ old('name') }}" required></div><div class="field"><label>Phone</label><input name="phone" type="tel" value="{{ old('phone') }}" required></div></div>
      <div class="field"><label>Email (optional)</label><input name="email" type="email" value="{{ old('email') }}"></div>
      <div class="field"><label>About your car</label><textarea name="message" rows="5" required placeholder="Make, model, year, km driven, city, expected price…">{{ old('message') }}</textarea></div>
      <button class="go btn-block"><i class="ti ti-send"></i> Request a call back</button>
    </form>
  </div>
</div>
@endsection
