{{-- AI assistant widget. mode = float (all pages) | page (/assistant) --}}
@php $asstName = \App\Models\Setting::get('assistant.name', 'Auto Guide'); $mode = $mode ?? 'float'; @endphp
<div class="{{ $mode === 'page' ? 'ag-page' : '' }}" id="ag" data-mode="{{ $mode }}" data-chat="{{ route('assistant.chat') }}" data-tts="{{ route('assistant.tts') }}" data-name="{{ $asstName }}">
  @if ($mode === 'float')
    <button class="ag-fab" id="agFab" aria-label="Chat with {{ $asstName }}"><span class="pulse"></span><i class="ti ti-sparkles"></i><span>Ask {{ $asstName }}</span></button>
  @endif
  <section class="ag-panel {{ $mode === 'page' ? 'open' : '' }}" id="agPanel" aria-label="{{ $asstName }} assistant">
    <div class="ag-head">
      <div class="ag-avatar"><i class="ti ti-robot"></i></div>
      <div style="flex:1"><b>{{ $asstName }}</b><small>Answers from our cars, news & videos first</small></div>
      <button class="icon-btn" id="agSpeak" title="Speak replies aloud" aria-label="Toggle spoken replies"><i class="ti ti-volume-off"></i></button>
      @if ($mode === 'float')<button class="icon-btn" id="agClose" aria-label="Close"><i class="ti ti-x"></i></button>@endif
    </div>
    <div class="ag-msgs" id="agMsgs" aria-live="polite"></div>
    <div class="ag-suggest" id="agSuggest">
      <button>Best SUV under ₹15 lakh?</button><button>Show used automatic cars</button><button>Petrol vs diesel vs EV?</button><button>What's new in car news?</button>
    </div>
    <div class="ag-status" id="agStatus"></div>
    <form class="ag-input" id="agForm" autocomplete="off">
      <button type="button" class="ag-mic" id="agMic" title="Talk to {{ $asstName }}" aria-label="Start voice conversation"><i class="ti ti-microphone"></i></button>
      <input id="agText" placeholder="Type or tap the mic and talk…" maxlength="600" aria-label="Your message">
      <button class="ag-send" aria-label="Send"><i class="ti ti-send"></i></button>
    </form>
  </section>
</div>
