{{-- AI assistant widget. mode = float (all pages) | page (/assistant) --}}
@php
  $asstName = \App\Models\Setting::get('assistant.name', 'Auto Guide');
  $siteName = \App\Models\Setting::get('site.name', config('app.name'));
  $greeting = \App\Models\Setting::get('assistant.greeting') ?: "I'm the {$siteName} Smart Assistant! How can I help you today?";
  $mode = $mode ?? 'float';
  $voiceOn = \App\Services\ElevenLabs::configured();
  $voiceGreet = str_replace('{name}', $asstName, \App\Models\Setting::get('assistant.voice_greeting') ?: "Hello! I'm {name}, your car assistant. How can I help you today?");
  $chips = [
    ['New launches', 'ti-car', 'ask', 'Show me the latest new car launches in India'],
    ['Locate dealer', 'ti-map-pin', 'ask', 'Help me find a dealer or showroom near me'],
    ['Request brochure', 'ti-download', 'ask', 'I would like to get the brochure of a car'],
    ['Get price', 'ti-tag', 'ask', 'I want to know the on-road price of a car'],
    ['Available colours', 'ti-palette', 'ask', 'What colours are available for popular cars?'],
    ['Book service', 'ti-tool', 'ask', 'I want to book a service for my car'],
  ];
  $promos = [
    ['New cars', 'Explore latest launches, prices & variants', route('newcars.index'), 'p1'],
    ['Certified used cars', 'Find your next car at the best price', route('cars.index'), 'p2'],
  ];
@endphp
<div class="aw {{ $mode === 'page' ? 'aw-page' : '' }}" id="ag" data-mode="{{ $mode }}" data-name="{{ $asstName }}" data-voice="{{ $voiceOn ? 1 : 0 }}" data-fallback="{{ \App\Models\Setting::bool('elevenlabs.browser_fallback', false) ? 1 : 0 }}" data-greet="{{ $voiceGreet }}"
     data-chat="{{ route('assistant.chat') }}" data-tts="{{ route('assistant.tts') }}" data-me="{{ route('assistant.me') }}"
     data-lead="{{ route('assistant.lead') }}" data-reset="{{ route('assistant.reset') }}" data-verify="{{ route('assistant.verify') }}" data-feedback="{{ route('assistant.feedback') }}">
  @if ($mode === 'float')
    <button class="aw-fab" id="agFab" type="button" aria-label="Chat with {{ $asstName }}">
      <span class="aw-rip"></span><span class="aw-rip r2"></span><span class="aw-rip r3"></span>
      @include('site.partials.bubble', ['cls' => 'aw-orb-sm'])
      <span class="aw-tip" id="agTip">Ask {{ $asstName }} ✨</span>
    </button>
  @endif

  <section class="aw-panel {{ $mode === 'page' ? 'open' : '' }}" id="agPanel" role="dialog" aria-label="{{ $asstName }} assistant" aria-live="polite">
    <div class="aw-glow"></div>
    <div class="aw-body">
      <div class="aw-tools">
        <button type="button" class="aw-ib" id="agSpeak" title="Speak replies aloud" aria-label="Toggle spoken replies"><i class="ti ti-volume-off"></i></button>
        <button type="button" class="aw-ib" id="agReset" title="New chat" aria-label="Start a new chat"><i class="ti ti-refresh"></i></button>
        @if ($mode === 'float')
          <button type="button" class="aw-ib" id="agExpand" title="Expand" aria-label="Expand"><i class="ti ti-arrows-diagonal"></i></button>
          <button type="button" class="aw-ib" id="agClose" title="Close" aria-label="Close"><i class="ti ti-x"></i></button>
        @endif
      </div>

      {{-- loading --}}
      <div class="aw-view show" id="vLoad">@include('site.partials.bubble')</div>

      {{-- step 1: lead form --}}
      <form class="aw-view aw-form" id="vLead" novalidate autocomplete="on">
        @include('site.partials.bubble', ['cls' => 'aw-orb-md'])
        <h3>Welcome! Let's get acquainted</h3>
        <p class="aw-sub">Share a few details and {{ $asstName }} will be ready to help — prices, comparisons, test drives &amp; more.</p>
        <label class="aw-f"><span>Full name</span><input name="name" autocomplete="name" maxlength="60" placeholder="e.g. Rahul Sharma" required></label>
        <label class="aw-f"><span>Mobile number</span><div class="aw-ph"><b>+91</b><input name="phone" inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10-digit mobile" required></div></label>
        <label class="aw-f"><span>Email <em>(we'll send a verification code)</em></span><input name="email" type="email" autocomplete="email" maxlength="120" placeholder="you@example.com" required></label>
        <label class="aw-f"><span>City <em>(optional)</em></span><input name="city" list="awCities" autocomplete="address-level2" maxlength="80" placeholder="Your city"><datalist id="awCities">@foreach (\App\Support\Filters::CITIES as $c)<option value="{{ is_array($c) ? ($c['name'] ?? '') : $c }}">@endforeach</datalist></label>
        <input class="aw-hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="aw-err" id="eLead" role="alert"></div>
        <button class="aw-btn" type="submit"><span>Send verification code</span><i class="ti ti-arrow-right"></i></button>
        <p class="aw-fine"><i class="ti ti-lock"></i> Your details are used only to help you and are never shared.</p>
      </form>

      {{-- step 2: OTP --}}
      <form class="aw-view aw-form" id="vOtp" novalidate>
        @include('site.partials.bubble', ['cls' => 'aw-orb-md'])
        <h3>Check your email</h3>
        <p class="aw-sub">We sent a 6-digit code to <b id="otpMail"></b>.</p>
        <div class="aw-otp" id="otpBoxes">@for ($i = 0; $i < 6; $i++)<input inputmode="numeric" maxlength="1" autocomplete="{{ $i ? 'off' : 'one-time-code' }}" aria-label="Digit {{ $i + 1 }}">@endfor</div>
        <div class="aw-err" id="eOtp" role="alert"></div>
        <button class="aw-btn" type="submit"><span>Verify &amp; start chatting</span><i class="ti ti-sparkles"></i></button>
        <p class="aw-fine"><button type="button" class="aw-lnk" id="otpResend" disabled>Resend code</button> · <button type="button" class="aw-lnk" id="otpBack">Change details</button></p>
      </form>

      {{-- chat --}}
      <div class="aw-view aw-chat" id="vChat">
        <div class="aw-scroll" id="agMsgs">
          <div class="aw-hero" id="agHero">
            @include('site.partials.bubble')
            <h3 id="agHello">Hello there!</h3>
            <p class="aw-sub">{{ $greeting }}</p>
            <div class="aw-promo" id="agPromo" aria-label="Highlights">
              @foreach ($promos as [$t, $d, $u, $c])<a class="aw-slide {{ $c }} {{ $loop->first ? 'on' : '' }}" href="{{ $u }}"><b>{{ $t }}</b><span>{{ $d }}</span><i class="ti ti-arrow-up-right"></i></a>@endforeach
              <div class="aw-dots">@foreach ($promos as $p)<i class="{{ $loop->first ? 'on' : '' }}"></i>@endforeach</div>
            </div>
            <p class="aw-hint">Tap on any of the below or type your query in the chat box</p>
            <div class="aw-chips" id="agSuggest">
              @foreach ($chips as [$label, $icon, $type, $val])
                @if ($type === 'link')<a class="aw-chip" href="{{ $val }}" style="--i:{{ $loop->index }}"><i class="ti {{ $icon }}"></i><span>{{ $label }}</span></a>
                @else<button type="button" class="aw-chip" data-q="{{ $val }}" style="--i:{{ $loop->index }}"><i class="ti {{ $icon }}"></i><span>{{ $label }}</span></button>@endif
              @endforeach
            </div>
          </div>
        </div>
        <div class="aw-status" id="agStatus"></div>
        <form class="aw-input" id="agForm" autocomplete="off">
          <div class="aw-inner">
            <button type="button" class="aw-mic" id="agMic" title="Talk to {{ $asstName }}" aria-label="Voice conversation"><i class="ti ti-microphone"></i></button>
            <input id="agText" placeholder="Ask anything about cars…" maxlength="300" aria-label="Your message">
            <button class="aw-send" aria-label="Send"><i class="ti ti-arrow-up"></i></button>
          </div>
        </form>
        <div class="aw-foot"><span id="agLeft"></span><span>AI can make mistakes · <a href="{{ route('contact') }}">Talk to our team</a></span></div>
      </div>

      {{-- feedback --}}
      <form class="aw-view aw-form aw-fb" id="vFb">
        <small class="aw-sub">Help us improve your experience</small>
        <h3>We value your feedback</h3>
        <div class="aw-lbl">Rate your experience</div>
        <div class="aw-stars" id="fbStars">@for ($i = 1; $i <= 5; $i++)<button type="button" data-v="{{ $i }}" aria-label="{{ $i }} star"><i class="ti ti-star-filled"></i></button>@endfor</div>
        <div class="aw-lbl">If responses weren't as expected, choose one</div>
        <label class="aw-rad"><input type="radio" name="reason" value="irrelevant"><span>Response was irrelevant</span></label>
        <label class="aw-rad"><input type="radio" name="reason" value="partly_correct"><span>Response is partly correct</span></label>
        <label class="aw-rad"><input type="radio" name="reason" value="other"><span>Other</span></label>
        <label class="aw-f"><span>Additional comments</span><textarea name="comment" rows="3" maxlength="500" placeholder="Share your thoughts (optional)…"></textarea></label>
        <button class="aw-btn dark" type="submit"><span>Submit &amp; Close</span></button>
      </form>
    </div>
  </section>
</div>
