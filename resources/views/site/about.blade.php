@extends('site.layout')
@section('title', 'About Us | '.\App\Models\Setting::get('site.name'))
@section('description', \App\Models\Setting::get('site.about'))

@section('content')
@include('site.partials.crumb', ['title' => 'About '.\App\Models\Setting::get('site.name'), 'trail' => []])
<div class="w" style="max-width:820px;padding-top:40px">
  <div class="prose"><p>{{ \App\Models\Setting::get('site.about') }}</p></div>
  <div class="grid" style="margin-top:30px">
    @foreach ([['ti-news', 'Fresh news', 'AI-assisted coverage of launches, reviews and industry updates, published throughout the day.'], ['ti-car', 'Used car marketplace', 'Browse cars and send an enquiry — our sales team follows up personally.'], ['ti-microphone', 'Talk to our AI', 'Ask in English or Hinglish, by typing or by voice. It checks our own data first.']] as [$i, $t, $d])
      <div class="aside-box" style="margin:0"><i class="ti {{ $i }}" style="font-size:32px;color:var(--red)"></i><h4 style="margin-top:8px">{{ $t }}</h4><p class="m" style="margin:0">{{ $d }}</p></div>
    @endforeach
  </div>
</div>
@endsection
