@extends('site.layout')

@push('head')
    <script
        type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => \App\Models\Setting::get('site.name'), 'url' => url('/'), 'potentialAction' => ['@type' => 'SearchAction', 'target' => route('search') . '?q={q}', 'query-input' => 'required name=q']], JSON_UNESCAPED_SLASHES) !!}</script>
    <script>window.HOME_BANNERS = {!! $heroBannersJson !!};</script>
    <style>
        .q{display:none!important}
        .brand-strip{overflow:hidden;padding-top:18px;padding-bottom:18px}
        .brand-track{display:flex;gap:14px;width:max-content;animation:brand-scroll 28s linear infinite}
        .brand-track:hover{animation-play-state:paused}
        .brand-chip{display:flex;align-items:center;gap:9px;min-width:132px;padding:10px 14px;border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--ink);font-weight:700;white-space:nowrap;transition:transform .2s,box-shadow .2s}
        .brand-chip:hover{transform:translateY(-3px);box-shadow:0 8px 18px #0001}
        .brand-logo{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--wash);overflow:hidden}.brand-logo img{width:24px;height:24px;object-fit:contain}
        @keyframes brand-scroll{from{transform:translateX(0)}to{transform:translateX(-35%)}}

        /* ---- Hero / banner / ads: fixed ratios so nothing stretches or breaks ---- */
        .hero > .w{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:clamp(20px,4vw,56px);align-items:center}
        .hero > .w > *{min-width:0}
        .bn{position:relative;width:100%;max-width:100%;overflow:hidden;border-radius:16px;background:var(--wash,#f2f3f5)}
        .bn-track{display:flex;width:100%;transition:transform .5s ease;will-change:transform}
        .bn-slide{position:relative;display:block;flex:0 0 100%;width:100%;min-width:0;aspect-ratio:16/9;overflow:hidden}
        .bn-slide img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center}
        .bn-cap{position:absolute;left:0;right:0;bottom:0;padding:clamp(14px,2.4vw,28px) clamp(14px,2.4vw,28px) clamp(40px,5vw,56px);background:linear-gradient(to top,rgba(0,0,0,.72),rgba(0,0,0,0));color:#fff}
        .bn-cap .t{margin:6px 0 0;font-size:clamp(17px,2.2vw,28px);line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .bn-cap .m{margin:4px 0 0;font-size:clamp(13px,1.3vw,15px);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .bn-ctl{position:absolute;left:0;right:0;bottom:12px;display:flex;align-items:center;justify-content:space-between;padding:0 16px;pointer-events:none}
        .bn-ctl > *{pointer-events:auto}
        .hero h1.h{font-size:clamp(28px,4vw,48px);line-height:1.1;overflow-wrap:anywhere}
        .hero form,.hero .hero-search-wrap{width:100%;max-width:100%}
        /* Ads: no extra card/background/border — only a plain centred container, image shown in full */
        .ad-slot{width:100%;max-width:1200px;margin:clamp(16px,2.5vw,28px) auto;padding:0 16px;box-sizing:border-box}
        .ad-slot :where(div,a,picture,figure,section){border:0!important;box-shadow:none!important;padding:0!important;margin-left:0;margin-right:0;width:100%;max-width:100%;aspect-ratio:auto!important;height:auto!important;min-height:0!important}
        .ad-slot{position:relative}
        .ad-slot img,.ad-slot video{display:block;width:100%;height:auto!important;max-width:100%;object-fit:contain;border-radius:12px}
        .ad-slot iframe{display:block;width:100%;max-width:100%;border:0}
        @media(max-width:900px){
            .hero > .w{grid-template-columns:1fr}
            .bn-slide{aspect-ratio:16/10}
        }
        @media(max-width:600px){
            .bn{border-radius:12px}
            .bn-slide{aspect-ratio:4/3}
            .bn-cap .m{display:none}
        }
        /* ---- Sell / inspection band (was .mag) ---- */
        .mag .w {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) minmax(360px, 480px) !important;
            gap: clamp(28px, 6vw, 84px) !important;
            align-items: center;
            padding-top: 64px;
            padding-bottom: 64px
        }

        .mag .sell-copy p {
            max-width: 46ch
        }

        .home-news-pills {
            padding: 0;
            border: 0;
            margin: 0;
            justify-content: center;
        }

        .mag .sell-points {
            list-style: none;
            margin: 22px 0 0;
            padding: 0;
            display: grid;
            gap: 10px
        }

        .mag .sell-points li {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            line-height: 1.45
        }

        .mag .sell-points svg {
            flex: none;
            width: 20px;
            height: 20px;
            margin-top: 1px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round
        }

        .mag .aside-box {
            margin: 0;
            background: #fff;
            color: var(--ink);
            border: 0;
            border-radius: 18px;
            padding: 26px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, .2)
        }

        .mag .aside-box .field {
            margin-bottom: 12px
        }

        .mag .aside-box .field label {
            color: var(--ink)
        }

        .mag .aside-box .go {
            justify-content: center;
            border: 0;
            cursor: pointer
        }

        .mag .aside-box .fine {
            margin: 12px 0 0;
            font-size: 12.5px;
            text-align: center;
            color: var(--muted)
        }

        /* ---- Page rhythm ---- */
        .home-sec {
            margin-top: clamp(40px, 5vw, 68px)
        }

        .home-sec .sh {
            align-items: flex-end
        }

        .home-sec .sh .sub {
            margin: 6px 0 0;
            max-width: 60ch;
            font-size: 15px;
            line-height: 1.5;
            color: var(--muted)
        }

        .home-sec .sh a:focus-visible,
        .home-sec a:focus-visible,
        .q a:focus-visible,
        .mag button:focus-visible,
        .mag input:focus-visible,
        .mag textarea:focus-visible {
            outline: 2px solid var(--red);
            outline-offset: 3px;
            border-radius: 6px
        }

        .q a svg.qi {
            width: 44px;
            height: 44px;
            fill: none;
            stroke: var(--red);
            stroke-width: 1.6;
            stroke-linecap: round;
            stroke-linejoin: round
        }

        .social-band {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 24px
        }

        .social-band .soc {
            flex: 1 1 420px
        }

        @media(max-width:760px) {
            .mag .w {
                grid-template-columns: 1fr !important;
                gap: 24px !important;
                padding-top: 36px;
                padding-bottom: 36px
            }

            .mag .aside-box {
                padding: 18px
            }

            .home-sec .sh .sub {
                font-size: 14px
            }
                    .home-sec .sh {
            flex-direction: column;
        }
        }
    </style>
@endpush

@section('content')
    @php
        $siteName = \App\Models\Setting::get('site.name') ?: config('app.name', 'Our site');
        $image = fn($item) => $item->image_url ?? $item->hero_url ?? asset('img/placeholder.svg');
        $url = fn($item) => $item->url ?? '#';
        $banners = collect($homeSettings?->hero_banners ?? [])->filter(fn($b) => !empty($b['title']) || !empty($b['text']) || !empty($b['image']))->values();

        // Social links & follower counts come from Settings — nothing is shown until you fill them in.
        $social = collect([
            ['label' => 'YouTube subscribers', 'url' => \App\Models\Setting::get('social.youtube'), 'count' => \App\Models\Setting::get('social.youtube_count')],
            ['label' => 'Instagram followers', 'url' => \App\Models\Setting::get('social.instagram'), 'count' => \App\Models\Setting::get('social.instagram_count')],
            ['label' => 'Facebook followers', 'url' => \App\Models\Setting::get('social.facebook'), 'count' => \App\Models\Setting::get('social.facebook_count')],
            ['label' => 'X followers', 'url' => \App\Models\Setting::get('social.x'), 'count' => \App\Models\Setting::get('social.x_count')],
        ])->filter(fn($s) => !empty($s['url']))->values();
        $socialTotal = \App\Models\Setting::get('social.total_reach');
    @endphp

    {{-- 1 · HERO ─────────────────────────────────────────────── --}}
    <div class="hero">
        <div class="w">

            <div class="bn" id="bn" role="region" aria-roledescription="carousel" aria-label="Top stories">
                <div class="bn-track" id="bnt">
                    @foreach($banners as $banner)
                        <a class="bn-slide" href="{{ $banner['url'] ?? '#' }}"
                            aria-label="{{ $loop->iteration }} of {{ $banners->count() }}">
                            <img src="{{ $banner['image'] ?? asset('img/placeholder.svg') }}" alt="" {{ $loop->first ? 'fetchpriority=high' : 'loading=lazy' }}>
                            <div class="bn-cap"><span class="tag">{{ $banner['tag'] ?? 'Featured' }}</span>
                                <p class="t">{{ $banner['title'] ?? '' }}</p>
                                <p class="m">{{ $banner['text'] ?? '' }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
                <div class="bn-ctl">
                    <div class="bn-dots" id="bnd"></div>
                    <div class="bn-nav">
                        <button type="button" id="bnp" aria-label="Previous story"><svg viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path d="M15 5l-7 7 7 7" />
                            </svg></button>
                        <button type="button" id="bnn" aria-label="Next story"><svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M9 5l7 7-7 7" />
                            </svg></button>
                    </div>
                </div>
            </div>

                        <div style="display: flex; justify-content: center; flex-direction: column;">
                <h1 class="h">Your next car starts here</h1>
                <p class="lede">
                    Search any model, or browse by type — prices, reviews and launches in one place.
                </p>
                <form id="ff" method="GET" action="{{ route('search') }}" role="search">
                    <div class="hero-search-wrap"><input id="fq" name="q" type="search"
                            placeholder="Try “Creta”, “SUV under 15 lakh” or “EV”"
                            aria-label="Search cars, used cars, news or videos" autocomplete="off" />
                        <div id="hero-suggestions" class="hero-suggestions" hidden></div>
                    </div>
                </form>
                <div class="pop">
                    <span>Jump to:</span><a href="{{ route('newcars.index') }}">New cars</a><a
                        href="{{ route('cars.index') }}">Used cars</a><a href="{{ route('news.index') }}">News</a><a
                        href="{{ route('videos.index') }}">Videos</a>
                </div>
            </div>
        </div>
    </div>



    {{-- 4 · TRENDING ───────────────────────────────────────────── --}}
    <div class="w brand-strip" aria-label="Browse car brands">
        <div class="brand-track">
            @foreach($masterBrands as $brand)
                <a class="brand-chip" href="{{ url('cars/'.$brand->slug) }}"><span class="brand-logo"><img src="{{ $brand->image ?: asset('img/placeholder.svg') }}" alt="{{ $brand->name }}" loading="lazy"></span><span>{{ $brand->name }}</span></a>
            @endforeach
        </div>
    </div>
        @include('site.partials.ad-horizontal', ['ads' => $homeSettings->adsFor('horizontal')])

    {{-- 4 · TRENDING — one block per vehicle type (cars / bikes / trucks); hidden if that type has none --}}
    @foreach($vehicles as $type => $vb)
    @if($vb['trending']->isNotEmpty())
        <section class="w home-sec" aria-labelledby="sec-trending-{{ $type }}" style="padding-top: 30px;">
            <div class="sh">
                <div>
                    <h2 class="h" id="sec-trending-{{ $type }}">{{ ['car' => 'What everyone’s looking at', 'bike' => 'Bikes riders are eyeing', 'truck' => 'Trucks built for the big jobs'][$type] ?? 'What everyone’s looking at' }}</h2>
                    <p class="sub">Trending {{ strtolower($vb['label']) }} right now.</p>
                </div>
                <a href="{{ url($vb['path']) }}">Browse all {{ strtolower($vb['label']) }}</a>
            </div>
            <div class="tr">
                @foreach($vb['trending'] as $car)
                    <a href="{{ $car->url }}"><span class="m">{{ $car->brand }}</span><b>{{ $car->name }}</b><img
                            src="{{ $car->hero_url }}" alt="" loading="lazy"><span class="ar"><svg viewBox="0 0 24 24"
                                aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" />
                            </svg></span></a>
                @endforeach
            </div>
        </section>
    @endif
    @endforeach

    @foreach($vehicles as $type => $vb)
    {{-- 5 · UPCOMING ───────────────────────────────────────────── --}}
    @if($vb['upcoming']->isNotEmpty())
        <section class="w home-sec" aria-labelledby="sec-upcoming-{{ $type }}">
            <div class="sh">
                <div>
                    <h2 class="h" id="sec-upcoming-{{ $type }}">Coming to showrooms soon</h2>
                    <p class="sub">Plan ahead — expected launch dates for upcoming {{ strtolower($vb['label']) }}.</p>
                </div>
                <a href="{{ url($vb['path']) }}?status=upcoming">All upcoming {{ strtolower($vb['label']) }}</a>
            </div>
            <div class="up">
                @foreach($vb['upcoming'] as $car)
                    <a href="{{ $car->url }}"><img class="c" src="{{ $car->hero_url }}" alt="" loading="lazy">
                        <div><img class="l" src="{{ $car->brandMaster?->image ?: asset('img/placeholder.svg') }}"
                                alt="{{ $car->brand }}" loading="lazy">
                            <p class="t" style="font-size:18px">{{ $car->full_name }}</p><span
                                class="d">{{ optional($car->launch_date)->format('M Y') ?? 'Coming soon' }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
    @endforeach
    {{-- 6 · COLLECTIONS ────────────────────────────────────────── --}}
    @if(count($collections))
        <section class="w home-sec" aria-labelledby="sec-collections">
            <div class="sh">
                <div>
                    <h2 class="h" id="sec-collections">Find a car that fits your life</h2>
                    <p class="sub">Hand-picked lists for budget, family, mileage and more.</p>
                </div>
            </div>
            <div class="sc" id="col">
                @foreach($collections as $collection)
                    <a href="{{ $collection['url'] ?? '#' }}"><b>{{ $collection['title'] ?? $collection['name'] ?? '' }}</b><span
                            class="cnt">{{ $collection['count'] ?? '' }}</span><span class="more">Explore →</span><img
                            src="{{ $collection['image'] ?? asset('img/placeholder.svg') }}" alt="" loading="lazy"></a>
                @endforeach
            </div>
        </section>
    @endif
    {{-- 3 · NEW LAUNCHES ───────────────────────────────────────── --}}
    @foreach($vehicles as $type => $vb)
    @if($vb['launched']->isNotEmpty())
        <section class="w home-sec" aria-labelledby="sec-launch-{{ $type }}">
            <div class="sh">
                <div>
                    <h2 class="h" id="sec-launch-{{ $type }}">Just launched</h2>
                    <p class="sub">The newest {{ strtolower($vb['label']) }} on sale, with starting prices.</p>
                </div>
                <a href="{{ url($vb['path']) }}">See all new {{ strtolower($vb['label']) }}</a>
            </div>
            <ul class="rows">
                @foreach($vb['launched'] as $car)
                    <li><a href="{{ $car->url }}"><img src="{{ $car->hero_url }}" alt="" loading="lazy">
                            <div><span class="m">{{ $car->brand }}</span>
                                <p class="name">{{ $car->name }}<span class="nl">{{ $car->status_label }}</span></p>
                            </div>
                            <p class="sp m">
                                {{ implode(', ', $car->fuel_types) }}{{ $car->body_type ? ' · ' . $car->body_type : '' }}
                            </p>
                            <p class="pr"><small>From</small>{{ $car->price_label }}</p>
                        </a></li>
                @endforeach
            </ul>
        </section>
    @endif
    @endforeach

    {{-- 7 · COMPARISONS ────────────────────────────────────────── --}}
    <section class="w home-sec" aria-labelledby="sec-compare">
        <div class="sh">
            <div>
                <h2 class="h" id="sec-compare">Torn between two? Put them head to head</h2>
                <p class="sub">Specs, features and prices side by side.</p>
            </div>
            <a href="{{ route('compare.index') }}">Start a comparison</a>
        </div>
        @if ($suggestedComparisons->isNotEmpty())
            <div id="vs">@include('site.compare._suggested', ['suggested' => $suggestedComparisons])</div>
        @else
            <p class="m">Pick any two cars and see them side by side. <a href="{{ route('compare.index') }}"
                    style="color:var(--red);font-weight:700">Start comparing →</a></p>
        @endif
    </section>

    {{-- 8 · EXPERT REVIEWS ─────────────────────────────────────── --}}
    @if(count($expertReviews))
        <section class="w home-sec" aria-labelledby="sec-reviews">
            <div class="sh">
                <div>
                    <h2 class="h" id="sec-reviews">Verdicts from our road testers</h2>
                    <p class="sub">Honest, independent reviews before you spend a rupee.</p>
                </div>
                <a href="{{ url('car-reviews') }}">Read all reviews</a>
            </div>
            <div class="grid rv" id="er">@foreach($expertReviews as $article)<a href="{{ $url($article) }}"><img
                    src="{{ $article->thumbnail }}" alt="" loading="lazy">
                <div class="rv-b"><span class="rv-k">{{ $article->category?->name ?? 'Review' }}</span>
                    <p class="t">{{ $article->title }}</p>
                    <p class="rv-m"><b>{{ $article->author?->name ?? '' }}</b></p>
                </div>
            </a>@endforeach</div>
        </section>
    @endif

    {{-- 9 · NEWS ───────────────────────────────────────────────── --}}
    @if($hero || count($side))
        <section class="w home-sec" aria-labelledby="sec-news">
            <div class="sh">
                <div>
                    <h2 class="h" id="sec-news">Today in the car world</h2>
                    <p class="sub">Breaking news, launches and stories from the industry.</p>
                </div>
                <nav class="cat-pills home-news-pills" aria-label="News categories">
                    <a href="{{ route('news.index') }}" class="pill on">All</a>
                    @foreach($newsCategories as $newsCategory)
                        <a href="{{ route('news.category', $newsCategory) }}" class="pill">{{ $newsCategory->name }}</a>
                    @endforeach
                </nav>
                <a href="{{ url('car-news') }}">More news</a>
            </div>
            <div class="two">
                <div id="nb">@if($hero)<a class="big" href="{{ $url($hero) }}"><img src="{{ $image($hero) }}" alt=""><span
                        class="tag">{{ $hero->category?->name ?? 'News' }}</span>
                    <p class="t">{{ $hero->title }}</p>
                    <p class="m">
                        {{ optional($hero->published_at)->format('d M') }}{{ $hero->author?->name ? ' · ' . $hero->author->name : '' }}
                    </p>
                </a>@endif</div>
                <ul class="li" id="nn">@foreach($side as $article)
                    <li><a href="{{ $url($article) }}"><img src="{{ $image($article) }}" alt="" loading="lazy">
                            <div><span class="tag">{{ $article->category?->name ?? 'News' }}</span>
                                <p class="t">{{ $article->title }}</p>
                                <p class="m">
                                    {{ optional($article->published_at)->format('d M') }}{{ $article->author?->name ? ' · ' . $article->author->name : '' }}
                                </p>
                            </div>
                </a></li>@endforeach
                </ul>
            </div>
        </section>
    @endif

    {{-- 10 · SELL / INSPECTION (conversion band) ───────────────── --}}
    <div class="mag home-sec" id="sell" style="margin-top:clamp(48px,6vw,80px)">
        <div class="w">
            <div class="sell-copy">
                <h2 class="h">Selling your car? Get a free inspection</h2>
                <p>Tell us about your car. Our team will inspect it and call you back with a fair offer — no obligation.</p>
                <ul class="sell-points">
                    <li><svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12l5 5 9-10" />
                        </svg>Free, no-obligation valuation</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12l5 5 9-10" />
                        </svg>Callback from our team, usually within a day</li>
                    <li><svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M5 12l5 5 9-10" />
                        </svg>Paperwork guidance from start to finish</li>
                </ul>
            </div>
            <div class="aside-box">
                @if (session('success'))
                <div class="alert alert-ok" role="status">{{ session('success') }}</div>@endif
                @if ($errors->any())
                <div class="alert alert-err" role="alert">{{ $errors->first() }}</div>@endif
                <form method="POST" action="{{ route('sell.submit') }}">
                    @csrf
                    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <div class="field"><label for="sell-name">Your name</label><input id="sell-name" name="name"
                            value="{{ old('name') }}" placeholder="Full name" autocomplete="name" required></div>
                    <div class="field"><label for="sell-phone">Phone number</label><input id="sell-phone" name="phone"
                            type="tel" inputmode="tel" value="{{ old('phone') }}" placeholder="10-digit mobile number"
                            autocomplete="tel" required></div>
                    <div class="field"><label for="sell-email">Email <span
                                style="font-weight:400;color:var(--muted)">(optional)</span></label><input id="sell-email"
                            name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com"
                            autocomplete="email"></div>
                    <div class="field"><label for="sell-msg">About your car</label><textarea id="sell-msg" name="message"
                            rows="3" required
                            placeholder="e.g. 2021 Hyundai Creta, 35,000 km, Delhi">{{ old('message') }}</textarea></div>
                    <button class="go btn-block" type="submit"><i class="ti ti-send"></i><span>Book my free
                            inspection</span></button>
                    <p class="fine">We’ll only use your details to contact you about this request.</p>
                </form>
            </div>
        </div>
    </div>

    {{-- 11 · COMMUNITY ─────────────────────────────────────────── --}}
    @if(count($advice))
        <section class="w home-sec" aria-labelledby="sec-community">
            <div class="cm">
                <div>
                    <h2 class="h" id="sec-community" style="font-size: clamp(28px, 3.4vw, 40px)">
                        Ask owners and experts before you buy
                    </h2>
                    <div class="st">
                        <div><b>50+</b><span>Experts</span></div>
                        <div><b>10k+</b><span>Members</span></div>
                        <div><b>6k+</b><span>Posts</span></div>
                    </div>
                    <a class="btn" style="background: var(--ink); color: #fff" href="{{ url('advice') }}">Join the
                        conversation</a>
                </div>
                <div class="adv" id="adv">@foreach($advice as $article)<a href="{{ $url($article) }}"><img
                        src="{{ $image($article) }}" alt="" loading="lazy">
                    <p class="t">{{ $article->title }}</p>
                </a>@endforeach</div>
            </div>
        </section>
    @endif

    {{-- 12 · SOCIAL (only when configured in Settings) ─────────── --}}
    @if($social->isNotEmpty())
        <section class="w home-sec" aria-labelledby="sec-social">
            <div class="stats social-band">
                <b>@if($socialTotal){{ $socialTotal }}<span>readers &amp; viewers across our channels</span>@else<span
                id="sec-social" style="font-size:inherit">Follow {{ $siteName }}</span>@endif</b>
                <div class="soc">
                    @foreach($social as $s)
                        <a href="{{ $s['url'] }}" target="_blank" rel="noopener noreferrer"><b>{{ $s['count'] ?: '→' }}</b><span
                                class="m">{{ $s['label'] }}</span></a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 13 · BROWSE / SEO LINKS ────────────────────────────────── --}}
    <section class="w home-sec" aria-labelledby="sec-browse" style="margin-bottom:clamp(40px,5vw,72px)">
        <div class="sh">
            <div>
                <h2 class="h" id="sec-browse">Browse by brand, body style or fuel</h2>
            </div>
        </div>
        <div class="ex" id="ex">
            <div>
                <h3>Popular brands</h3>
                <div>@foreach($masterBrands as $brand)<a
                href="{{ url('cars/' . $brand->slug) }}">{{ $brand->name }}</a>@endforeach</div>
            </div>
            <div>
                <h3>Body style</h3>
                <div>@foreach($masterBodyTypes as $body)<a
                href="{{ url('cars/' . $body->slug . '-cars-in-india') }}">{{ $body->name }}</a>@endforeach</div>
            </div>
            <div>
                <h3>Fuel type</h3>
                <div>@foreach($masterFuels as $fuel)<a
                href="{{ url('cars/' . $fuel->slug . '-cars-in-india') }}">{{ $fuel->name }}</a>@endforeach</div>
            </div>
        </div>
    </section>
@endsection

@push('head')
    <style>
        .hero-search-wrap {
            position: relative;
            flex: 1;
            min-width: 0
        }

        .hero-search-wrap input {
            width: 100%
        }

        .hero-suggestions {
            position: absolute;
            z-index: 20;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            background: #fff;
            color: var(--ink);
            border: 1px solid var(--line);
            border-radius: 12px;
            box-shadow: 0 16px 36px #0002;
            overflow: hidden;
            text-align: left
        }

        .hero-suggestions strong {
            display: block;
            padding: 15px 20px;
            color: var(--red);
            font-size: 15px
        }

        .hero-suggestions a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 16px 20px;
            border-top: 1px solid var(--line);
            font-size: 16px;
            line-height: 1.3
        }

        .hero-suggestions a:hover {
            background: var(--wash)
        }

        .hero-suggestions a .suggestion-label {
            color: var(--ink);
            font-size: 16px
        }

        .hero-suggestions a .suggestion-arrow {
            color: #9aa3b2;
            font-size: 22px;
            line-height: 1
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => { const input = document.querySelector('#fq'), box = document.querySelector('#hero-suggestions'); if (!input || !box) return; let timer; input.addEventListener('input', () => { clearTimeout(timer); const q = input.value.trim(); if (q.length < 2) { box.hidden = true; return } timer = setTimeout(async () => { try { const r = await fetch('{{ route('search.suggestions') }}?q=' + encodeURIComponent(q)); const items = await r.json(); box.innerHTML = '<strong>Results</strong>' + (items.length ? items.map(x => `<a href="${x.url}"><span class="suggestion-label">${x.label}</span><span class="suggestion-arrow">↗</span></a>`).join('') : '<div style="padding:15px 20px;color:var(--ink2)">No results found</div>'); box.hidden = false } catch (e) { box.hidden = true } }, 180) }); document.addEventListener('click', e => { if (!e.target.closest('.hero-search-wrap')) box.hidden = true }) });
        document.addEventListener('DOMContentLoaded', () => { const box = document.querySelector('#bn'), track = document.querySelector('#bnt'), dotsEl = document.querySelector('#bnd'); if (!box || !track || !dotsEl) return; const slides = [...track.children], n = slides.length; if (n < 2) { dotsEl.parentElement.hidden = true; return } let i = 0, timer = null, hold = false; dotsEl.innerHTML = slides.map((x, k) => `<button type="button" data-i="${k}" aria-label="Go to story ${k + 1}"></button>`).join(''); const dots = [...dotsEl.children], go = k => { i = (k + n) % n; track.style.transform = `translateX(-${i * 100}%)`; slides.forEach((s, j) => { s.tabIndex = j == i ? 0 : -1; s.setAttribute('aria-hidden', j == i ? 'false' : 'true') }); dots.forEach((d, j) => d.setAttribute('aria-current', j == i ? 'true' : 'false')) }, stop = () => { clearInterval(timer); timer = null }, play = () => { if (matchMedia('(prefers-reduced-motion: reduce)').matches || hold || timer) return; timer = setInterval(() => go(i + 1), 5000) }; document.querySelector('#bnp').onclick = () => (stop(), go(i - 1), play()); document.querySelector('#bnn').onclick = () => (stop(), go(i + 1), play()); dotsEl.onclick = e => { const b = e.target.closest('button'); if (b) (stop(), go(+b.dataset.i), play()) }; const pause = () => (hold = true, stop()), resume = () => (hold = false, play()); box.addEventListener('mouseenter', pause); box.addEventListener('mouseleave', resume); box.addEventListener('focusin', pause); box.addEventListener('focusout', resume); box.addEventListener('keydown', e => { if (e.key == 'ArrowLeft') go(i - 1); if (e.key == 'ArrowRight') go(i + 1) }); let x0 = null; box.addEventListener('touchstart', e => x0 = e.touches[0].clientX, { passive: true }); box.addEventListener('touchend', e => { if (x0 === null) return; const d = e.changedTouches[0].clientX - x0; if (Math.abs(d) > 40) (stop(), go(i + (d < 0 ? 1 : -1)), play()); x0 = null }, { passive: true }); document.addEventListener('visibilitychange', () => document.hidden ? stop() : play()); go(0); play() });
    </script>
@endpush