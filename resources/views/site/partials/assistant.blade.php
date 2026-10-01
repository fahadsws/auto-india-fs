{{-- AI assistant widget. mode = float (all pages) | page (/assistant) --}}
@php
  $asstName = \App\Models\Setting::get('assistant.name', 'Auto Guide');
  $siteName = \App\Models\Setting::get('site.name', config('app.name'));
  $greeting = \App\Models\Setting::get('assistant.greeting') ?: 'नमस्ते! मैं आपका स्मार्ट असिस्टेंट हूँ। मैं आपकी कैसे मदद कर सकता हूँ?';
  $mode = $mode ?? 'float';
  $voiceOn = \App\Services\ElevenLabs::configured();
  $voiceGreet = str_replace('{name}', $asstName, \App\Models\Setting::get('assistant.voice_greeting') ?: 'नमस्ते! मैं {name} हूँ, आपका कार असिस्टेंट। बताइए, मैं आपकी क्या मदद करूँ?');
  // Conversation starters only: they type the question for the visitor. Test drives, inspections and enquiries are taken by the AI in conversation.
  $chips = [
    ['नई गाड़ियाँ', 'ti-car', 'ask', 'नई गाड़ियों में क्या नया लॉन्च हुआ है?'],
    ['पुरानी गाड़ी', 'ti-car-garage', 'ask', 'मुझे पुरानी गाड़ी चाहिए'],
    ['शोरूम / डीलर', 'ti-map-pin', 'ask', 'आपका शोरूम कहाँ है और टाइमिंग क्या है?'],
    ['कीमत जानें', 'ti-tag', 'ask', 'मुझे एक गाड़ी की ऑन-रोड कीमत जाननी है'],
    ['EMI / लोन', 'ti-calculator', 'ask', 'गाड़ी के लोन और EMI के बारे में बताइए'],
    ['अपनी गाड़ी बेचें', 'ti-cash', 'ask', 'मुझे अपनी गाड़ी बेचनी है'],
  ];
  $promos = [
    ['नई गाड़ियाँ', 'नए लॉन्च, कीमत और वेरिएंट देखें', route('newcars.index'), 'p1'],
    ['सर्टिफ़ाइड पुरानी गाड़ियाँ', 'सबसे अच्छी कीमत पर अपनी अगली गाड़ी चुनें', route('cars.index'), 'p2'],
  ];
@endphp
<div class="aw {{ $mode === 'page' ? 'aw-page' : '' }}" id="ag" data-mode="{{ $mode }}" data-name="{{ $asstName }}" data-voice="{{ $voiceOn ? 1 : 0 }}" data-fallback="{{ \App\Models\Setting::bool('elevenlabs.browser_fallback', false) ? 1 : 0 }}" data-greet="{{ $voiceGreet }}"
     data-chat="{{ route('assistant.chat') }}" data-tts="{{ route('assistant.tts') }}" data-me="{{ route('assistant.me') }}"
     data-lead="{{ route('assistant.lead') }}" data-reset="{{ route('assistant.reset') }}" data-verify="{{ route('assistant.verify') }}" data-feedback="{{ route('assistant.feedback') }}">
  @if ($mode === 'float')
    <button class="aw-fab" id="agFab" type="button" aria-label="Chat with {{ $asstName }}">
      <span class="aw-rip"></span><span class="aw-rip r2"></span><span class="aw-rip r3"></span>
      @include('site.partials.bubble', ['cls' => 'aw-orb-sm'])
      <span class="aw-tip" id="agTip">{{ $asstName }} से पूछिए ✨</span>
    </button>
  @endif

  <section class="aw-panel {{ $mode === 'page' ? 'open' : '' }}" id="agPanel" role="dialog" aria-label="{{ $asstName }} assistant" aria-live="polite">
    <div class="aw-glow"></div>
    <div class="aw-body">
      <div class="aw-tools">
        <button type="button" class="aw-ib" id="agSpeak" title="जवाब बोलकर सुनाएँ" aria-label="Toggle spoken replies"><i class="ti ti-volume-off"></i></button>
        <button type="button" class="aw-ib" id="agReset" title="नई चैट" aria-label="Start a new chat"><i class="ti ti-refresh"></i></button>
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
        <h3>स्वागत है! आपसे मिलकर अच्छा लगा</h3>
        <p class="aw-sub">थोड़ी जानकारी दीजिए, फिर {{ $asstName }} आपकी मदद करेगा — कीमत, तुलना, टेस्ट ड्राइव और बहुत कुछ।</p>
        <label class="aw-f"><span>पूरा नाम</span><input name="name" autocomplete="name" maxlength="60" placeholder="जैसे: राहुल शर्मा" required></label>
        <label class="aw-f"><span>मोबाइल नंबर</span><div class="aw-ph"><b>+91</b><input name="phone" inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10 अंकों का मोबाइल नंबर" required></div></label>
        <label class="aw-f"><span>ईमेल <em>(हम वेरिफ़िकेशन कोड भेजेंगे)</em></span><input name="email" type="email" autocomplete="email" maxlength="120" placeholder="you@example.com" required></label>
        <label class="aw-f"><span>शहर <em>(वैकल्पिक)</em></span><input name="city" list="awCities" autocomplete="address-level2" maxlength="80" placeholder="आपका शहर"><datalist id="awCities">@foreach (\App\Support\Filters::CITIES as $c)<option value="{{ is_array($c) ? ($c['name'] ?? '') : $c }}">@endforeach</datalist></label>
        <input class="aw-hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <div class="aw-err" id="eLead" role="alert"></div>
        <button class="aw-btn" type="submit"><span>वेरिफ़िकेशन कोड भेजें</span><i class="ti ti-arrow-right"></i></button>
        <p class="aw-fine"><i class="ti ti-lock"></i> आपकी जानकारी सिर्फ़ आपकी मदद के लिए इस्तेमाल होती है, कभी साझा नहीं की जाती।</p>
      </form>

      {{-- step 2: OTP --}}
      <form class="aw-view aw-form" id="vOtp" novalidate>
        @include('site.partials.bubble', ['cls' => 'aw-orb-md'])
        <h3>अपना ईमेल देखिए</h3>
        <p class="aw-sub">हमने <b id="otpMail"></b> पर 6 अंकों का कोड भेजा है।</p>
        <div class="aw-otp" id="otpBoxes">@for ($i = 0; $i < 6; $i++)<input inputmode="numeric" maxlength="1" autocomplete="{{ $i ? 'off' : 'one-time-code' }}" aria-label="Digit {{ $i + 1 }}">@endfor</div>
        <div class="aw-err" id="eOtp" role="alert"></div>
        <button class="aw-btn" type="submit"><span>वेरिफ़ाई करें और बात शुरू करें</span><i class="ti ti-sparkles"></i></button>
        <p class="aw-fine"><button type="button" class="aw-lnk" id="otpResend" disabled>कोड दोबारा भेजें</button> · <button type="button" class="aw-lnk" id="otpBack">जानकारी बदलें</button></p>
      </form>

      {{-- chat --}}
      <div class="aw-view aw-chat" id="vChat">
        <div class="aw-scroll" id="agMsgs">
          <div class="aw-hero" id="agHero">
            @include('site.partials.bubble')
            <h3 id="agHello">नमस्ते!</h3>
            <p class="aw-sub">{{ $greeting }}</p>
            <div class="aw-promo" id="agPromo" aria-label="Highlights">
              @foreach ($promos as [$t, $d, $u, $c])<a class="aw-slide {{ $c }} {{ $loop->first ? 'on' : '' }}" href="{{ $u }}"><b>{{ $t }}</b><span>{{ $d }}</span><i class="ti ti-arrow-up-right"></i></a>@endforeach
              <div class="aw-dots">@foreach ($promos as $p)<i class="{{ $loop->first ? 'on' : '' }}"></i>@endforeach</div>
            </div>
            <p class="aw-hint">नीचे से कुछ चुनिए, या बोलकर / लिखकर अपना सवाल पूछिए</p>
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
            <button type="button" class="aw-mic" id="agMic" title="{{ $asstName }} से बोलकर बात करें" aria-label="Voice conversation"><i class="ti ti-microphone"></i></button>
            <input id="agText" placeholder="गाड़ियों के बारे में कुछ भी पूछिए…" maxlength="300" aria-label="Your message">
            <button class="aw-send" aria-label="Send"><i class="ti ti-arrow-up"></i></button>
          </div>
        </form>
        <div class="aw-foot"><span id="agLeft"></span><span>AI से गलती हो सकती है · <a href="{{ route('contact') }}">हमारी टीम से बात करें</a></span></div>
      </div>

      {{-- feedback --}}
      <form class="aw-view aw-form aw-fb" id="vFb">
        <small class="aw-sub">हमें बेहतर बनाने में मदद कीजिए</small>
        <h3>आपकी राय हमारे लिए ज़रूरी है</h3>
        <div class="aw-lbl">अपने अनुभव को रेटिंग दीजिए</div>
        <div class="aw-stars" id="fbStars">@for ($i = 1; $i <= 5; $i++)<button type="button" data-v="{{ $i }}" aria-label="{{ $i }} star"><i class="ti ti-star-filled"></i></button>@endfor</div>
        <div class="aw-lbl">अगर जवाब उम्मीद के मुताबिक नहीं थे, तो एक चुनिए</div>
        <label class="aw-rad"><input type="radio" name="reason" value="irrelevant"><span>जवाब सवाल से मेल नहीं खाता था</span></label>
        <label class="aw-rad"><input type="radio" name="reason" value="partly_correct"><span>जवाब आंशिक रूप से सही है</span></label>
        <label class="aw-rad"><input type="radio" name="reason" value="other"><span>अन्य</span></label>
        <label class="aw-f"><span>और कुछ कहना चाहें तो</span><textarea name="comment" rows="3" maxlength="500" placeholder="अपनी राय लिखिए (वैकल्पिक)…"></textarea></label>
        <button class="aw-btn dark" type="submit"><span>भेजें और बंद करें</span></button>
      </form>
    </div>
  </section>
</div>
