@extends('site.layout')
@section('title', 'AI Car Assistant | '.\App\Models\Setting::get('site.name'))
@section('description', 'Chat or talk with our AI car assistant. It searches our own cars, news and videos first.')
@section('content')
<div class="guide-hero"><div class="w guide-hero-inner"><div><span class="guide-kicker">AUTOMOBILEINDIA / AI GUIDE</span><h1>{{ \App\Models\Setting::get('assistant.name', 'Auto Guide') }}</h1><p>Your clear, practical car companion. Ask about cars, prices, comparisons, ownership and the latest news.</p></div><div class="guide-mark" aria-hidden="true">AI</div></div></div>
<div class="w guide-layout"><aside class="guide-nav" aria-label="Auto Guide navigation"><span>Ask Auto Guide</span><a href="#ask">Start a conversation</a><a href="#how">How it works</a><a href="{{ route('cars.index') }}">Browse used cars</a><a href="{{ route('newcars.index') }}">Explore new cars</a><a href="{{ route('news.index') }}">Read car news</a></aside><div class="guide-chat" id="ask"><div class="guide-intro" id="how"><strong>Ask anything about your next car.</strong><span>We search our cars, news and videos first, then fill in the gaps with general knowledge.</span></div>@include('site.partials.assistant', ['mode' => 'page'])</div></div>
@endsection
