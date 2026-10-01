@extends('site.layout')
@section('title', 'Contact Us | '.\App\Models\Setting::get('site.name'))

@section('content')
<div class="phead"><div class="w"><h1 class="h">Contact us</h1><p>Questions, feedback or partnerships — we'd love to hear from you.</p></div></div>
<div class="w" style="padding-top:40px"><div class="grid" style="grid-template-columns:1fr 1.2fr;align-items:start">
  <div class="aside-box"><h3>Get in touch</h3>
    <p><i class="ti ti-mail" style="color:var(--red)"></i> {{ \App\Models\Setting::get('site.email') }}</p><p><i class="ti ti-phone" style="color:var(--red)"></i> {{ \App\Models\Setting::get('site.phone') }}</p><p><i class="ti ti-map-pin" style="color:var(--red)"></i> {{ \App\Models\Setting::get('site.address') }}</p>
    <button class="btn-ghost" data-ask="I have a question about Automobil India"><i class="ti ti-sparkles"></i> Or ask our AI</button></div>
  <div class="aside-box">
    @if (session('success'))<div class="alert alert-ok">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-err">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('contact.submit') }}">@csrf
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
      <div class="field"><label>Name</label><input name="name" value="{{ old('name') }}" required></div>
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px"><div class="field"><label>Phone</label><input name="phone" type="tel" value="{{ old('phone') }}" required></div><div class="field"><label>Email</label><input name="email" type="email" value="{{ old('email') }}"></div></div>
      <div class="field"><label>Message</label><textarea name="message" rows="5">{{ old('message') }}</textarea></div>
      <button class="go"><i class="ti ti-send"></i> Send message</button>
    </form>
  </div>
</div></div>
@endsection
