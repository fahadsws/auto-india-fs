@php
  $siteName = \App\Models\Setting::get('site.name', config('app.name'));
  $pageTitle = trim($__env->yieldContent('title')) ?: $siteName . ' — ' . \App\Models\Setting::get('site.tagline', 'Car news, reviews & used cars');
  $pageDesc = trim($__env->yieldContent('description')) ?: \App\Models\Setting::get('site.about', '');
  $pageImage = trim($__env->yieldContent('image')) ?: asset('img/placeholder.svg');
  $assistantOn = \App\Models\Setting::bool('assistant.enabled', true);
  $asstName = \App\Models\Setting::get('assistant.name', 'Auto Guide');
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="light">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $pageTitle }}</title>
  <meta name="description" content="{{ \Illuminate\Support\Str::limit($pageDesc, 160, '') }}">
  <link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) ?: url()->current() }}">
  @hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#e11d2e">
  <meta property="og:site_name" content="{{ $siteName }}">
  <meta property="og:title" content="{{ trim($__env->yieldContent('og_title')) ?: $pageTitle }}">
  <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(trim($__env->yieldContent('og_description')) ?: $pageDesc, 200, '') }}">
  <meta property="og:image" content="{{ $pageImage }}">
  <meta property="og:type" content="@yield('og_type', 'website')">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta name="twitter:card" content="summary_large_image">
  <link rel="alternate" type="application/rss+xml" title="{{ $siteName }} news" href="{{ route('feed') }}">
  <link rel="icon" href="{{ asset('vuexy/img/favicon/favicon.ico') }}">
  <script>try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) { }</script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="{{ asset('vuexy/vendor/fonts/tabler-icons.full.css') }}">
  <link rel="stylesheet" href="{{ asset('css/home-new.css') }}">
  <link rel="stylesheet" href="{{ asset('css/site-pages.css') }}">
  @stack('head')
</head>

<body>

  <div class="util">
    <div class="w">
      <span class="util-message"><svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="m12 3 1.4 5.1L18 10l-4.6 1.9L12 17l-1.4-5.1L6 10l4.6-1.9L12 3Z" />
          <path d="m19 15 .7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z" />
        </svg>India's AI-powered car news, reviews and used-car marketplace</span>
      <span class="util-links"><a href="{{ route('sell') }}">Sell your car</a><a href="{{ route('assistant') }}">Get advice</a></span>
    </div>
  </div>
  <header class="mast">
    <div class="w">
      <a class="logo" href="/" aria-label="AutomobileIndia home"><img
          src="https://automobilindia.com/wp-content/uploads/2023/05/automobil-india-logo.jpg"
          alt="AutomobileIndia" /></a>
      <nav aria-label="Main" id="nav">
        <ul>
          @foreach ($headerMenu as $m)
            @if ($m->children->isEmpty())
              @if ($m->url)<li><a class="nav-direct" href="{{ $m->url }}" @if ($m->open_new_tab) target="_blank" rel="noopener" @endif>{{ $m->title }}</a></li>@endif
            @else
              <li><button type="button" aria-haspopup="true">{{ $m->title }}</button>
                <div class="pl">
                  @if ($m->url)<a href="{{ $m->url }}" @if ($m->open_new_tab) target="_blank" rel="noopener" @endif>All {{ $m->title }}</a>@endif
                  @foreach ($m->children as $c)<a href="{{ $c->url ?: '#' }}" @if ($c->open_new_tab) target="_blank" rel="noopener" @endif>{{ $c->title }}</a>@endforeach
                </div></li>
            @endif
          @endforeach
        </ul>
      </nav>
      <div class="tools">
        <a class="tb2" id="sb" href="{{ route('assistant') }}" aria-label="Ask AI">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path d="m12 3 1.4 5.1L18 10l-4.6 1.9L12 17l-1.4-5.1L6 10l4.6-1.9L12 3Z" />
            <path d="m19 15 .7 2.3L22 18l-2.3.7L19 21l-.7-2.3L16 18l2.3-.7L19 15Z" />
          </svg><span>Ask AI</span>
        </a>
        <div class="loc" id="loc">
          <button class="tb2" id="lb" type="button" aria-haspopup="true" aria-expanded="false"
            aria-label="Choose your city">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
              stroke-linejoin="round">
              <path d="M12 21s7-6.2 7-11.5a7 7 0 0 0-14 0C5 14.8 12 21 12 21Z" />
              <circle cx="12" cy="9.5" r="2.5" />
            </svg><span id="city">Delhi</span>
          </button>
          <div class="pl" id="cl"></div>
        </div>
      </div>
      <button class="ib" id="mb" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="menu">
        <i></i><i></i><i></i>
      </button>
    </div>
    <div class="srch" id="sp" hidden>
      <div class="w">
        <form id="sf">
          <input id="sq" type="search" placeholder="Search a car, brand or comparison" aria-label="Search" /><button
            class="go">Search</button>
        </form>
        <div class="pop">
          <span>Popular:</span><a href="cars/tata/sierra">Tata Sierra</a><a href="cars/hyundai">Hyundai
            cars</a><a href="compare-cars/hyundai-creta-vs-kia-seltos">Creta vs Seltos</a>
        </div>
      </div>
    </div>
    <div class="menu" id="menu" hidden>
      <div class="w cols" id="mcols">
        @php $loose = $headerMenu->filter(fn ($m) => $m->children->isEmpty() && $m->url); @endphp
        @if ($loose->isNotEmpty())
        <div><h3>Menu</h3><ul>
          @foreach ($loose as $m)<li><a href="{{ $m->url }}" @if ($m->open_new_tab) target="_blank" rel="noopener" @endif>{{ $m->title }}</a></li>@endforeach
        </ul></div>
        @endif
        @foreach ($headerMenu->filter(fn ($m) => $m->children->isNotEmpty()) as $m)
        <div><h3>{{ $m->title }}</h3><ul>
          @if ($m->url)<li><a href="{{ $m->url }}">All {{ $m->title }}</a></li>@endif
          @foreach ($m->children as $c)<li><a href="{{ $c->url ?: '#' }}" @if ($c->open_new_tab) target="_blank" rel="noopener" @endif>{{ $c->title }}</a></li>@endforeach
        </ul></div>
        @endforeach
      </div>
    </div>
  </header>


  <main>@yield('content')</main>

  @if ($assistantOn && ! request()->routeIs('assistant'))
    @include('site.partials.assistant', ['mode' => 'float'])
  @endif

  <footer class="ft">
    <div class="w">
      <div class="ft-top">
        <div class="ft-soc"><span>Follow us</span><a href="https://www.youtube.com/user/autocarindia1" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22.5 6.4a2.8 2.8 0 0 0-2-2C18.9 4 12 4 12 4s-6.9 0-8.5.4a2.8 2.8 0 0 0-2 2A29 29 0 0 0 1 11.8c0 1.8.2 3.6.5 5.3a2.8 2.8 0 0 0 2 2c1.6.4 8.5.4 8.5.4s6.9 0 8.5-.4a2.8 2.8 0 0 0 2-2c.3-1.7.5-3.5.5-5.3s-.2-3.6-.5-5.4Z"/><path d="m9.8 15 5.7-3.2L9.8 8.5V15Z"/></svg></a><a href="https://www.instagram.com/autocar_india/" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/></svg></a><a href="https://www.facebook.com/autocarindiamag" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 8.5V7c0-.8.5-1.5 1.5-1.5H17V2h-2.5C11.8 2 10 3.8 10 6.5v2H7V12h3v10h4V12h2.8l.7-3.5H14Z"/></svg></a><a href="https://x.com/autocarindiamag" target="_blank" rel="noopener noreferrer" aria-label="X"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4l11.7 16H20L8.3 4H4Z"/><path d="M4.3 20l6.6-6.6M13.1 10.6 19.7 4"/></svg></a></div>
        <a class="ft-logo" href="/" aria-label="AutomobileIndia home"><img
            src="https://automobilindia.com/wp-content/uploads/2023/05/automobil-india-logo.jpg" alt="AutomobileIndia"
            loading="lazy" /></a>
        <a class="ft-up" href="#" onclick="scrollTo({top:0});return false">Back to top &uarr;</a>
      </div>
      <div class="ft-cols" id="fg">
        <div class="ft-about"><h3>AutomobileIndia</h3>
          <p>India's AI-powered car news, reviews and used-car marketplace.</p></div>
        @foreach ($footerMenu as $m)
        <div><h3>@if ($m->url)<a href="{{ $m->url }}" @if ($m->open_new_tab) target="_blank" rel="noopener" @endif>{{ $m->title }}</a>@else{{ $m->title }}@endif</h3>
          @if ($m->children->isNotEmpty())<ul>
          @foreach ($m->children as $c)<li><a href="{{ $c->url ?: '#' }}" @if ($c->open_new_tab) target="_blank" rel="noopener" @endif>{{ $c->title }}</a></li>@endforeach
        </ul>@endif</div>
        @endforeach
      </div>
      <div class="ft-fine">
        <span>&copy; 2026 AutomobileIndia. All rights reserved.</span>
      </div>
    </div>
  </footer>

  <div class="cm" id="cm" role="dialog" aria-modal="true" aria-labelledby="cm-t" hidden>
    <div class="cm-box">
      <button class="cm-x" id="cm-x" type="button" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
          <path d="M5 5l14 14M19 5L5 19" />
        </svg>
      </button>
      <svg class="cm-art" viewBox="0 0 280 150" aria-hidden="true">
        <defs>
          <radialGradient id="cmf" cx="50%" cy="50%" r="50%">
            <stop offset="55%" stop-color="#fff" stop-opacity="0" />
            <stop offset="100%" stop-color="#f7f6f4" />
          </radialGradient>
          <linearGradient id="cmp" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#7b7cf5" />
            <stop offset="1" stop-color="#4a3fd8" />
          </linearGradient>
        </defs>
        <g stroke="#c9caf3" stroke-width="1" fill="none">
          <path d="M20 118L110 74L180 96L262 60" />
          <path d="M8 100L92 70L170 88L270 50" />
          <path d="M40 140L120 100L200 126L270 96" />
          <path d="M60 148L128 116L210 146" />
          <path d="M80 60L120 145" />
          <path d="M120 55L150 148" />
          <path d="M160 60L182 146" />
          <path d="M200 55L220 140" />
          <path d="M40 75L78 142" />
          <path d="M235 52L250 130" />
        </g>
        <rect width="280" height="150" fill="url(#cmf)" />
        <ellipse cx="140" cy="112" rx="9" ry="3" fill="#4a3fd8" opacity=".85" />
        <path d="M140 108C140 108 120 84 120 66a20 20 0 0 1 40 0c0 18-20 42-20 42Z" fill="url(#cmp)" />
        <ellipse cx="139" cy="64" rx="5" ry="7" transform="rotate(25 139 64)" fill="#fff" />
      </svg>
      <h2 id="cm-t">Where are you buying?</h2>
      <p>Prices, offers and stock availability vary by city.</p>
      <button class="cm-go" id="cm-go" type="button">Detect my city</button>
      <div class="cm-err" id="cm-err" role="alert"></div>
      <button class="cm-man" id="cm-man" type="button" aria-expanded="false">Select City Manually</button>
      <div class="cm-cities" id="cm-cities" hidden></div>
    </div>
  </div>
</body>

</html>
