@extends('site.layout')
@section('title', 'E-Challan Check Online — Vehicle Challan Status & Payment | ' . \App\Models\Setting::get('site.name'))
@section('description', 'Check your traffic e-challan by vehicle number and pay it on the official Parivahan and state traffic police portals. Step-by-step guide, official links and tips to avoid fake payment sites.')

@push('head')
@php($faq = ($seo?->faqItems()) ?: [
  ['q' => 'How do I check an e-challan with my vehicle number?', 'a' => 'Enter your vehicle number on this page and open the official Parivahan e-Challan portal. On the portal, choose "Check Challan Details", enter the vehicle number and the captcha, and the pending challans are listed with the amount and the offence.'],
  ['q' => 'Do you store my vehicle number or challan details?', 'a' => 'No. The number is checked in your browser only to make sure its format is valid. Challan details are shown by the government portal; we never see or store them.'],
  ['q' => 'How can I pay an e-challan?', 'a' => 'Pay only on the official government portal (echallan.parivahan.gov.in) or your state traffic police website, using UPI, net banking, cards or wallets. Keep the payment receipt for your records.'],
  ['q' => 'What if I think the challan is wrong?', 'a' => 'Do not ignore it. Note the challan number and offence, then use the dispute or grievance option on the portal, or contest it before the traffic court or the virtual court within the time stated on the notice.'],
  ['q' => 'What happens if I do not pay a challan?', 'a' => 'An unpaid challan is usually moved to the court after the period on the notice. It can then involve a higher penalty and may block services such as vehicle transfer or insurance renewal in some states. Paying or contesting on time avoids this.'],
  ['q' => 'How do I avoid fake challan messages?', 'a' => 'Official portals end in .gov.in. Do not pay through links received in SMS, WhatsApp or email, and never share OTPs or card details. Type the portal address yourself or use this page.'],
])
@unless ($seo?->faqItems())
  <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()], JSON_UNESCAPED_SLASHES) !!}</script>
@endunless
<style>
  .ech{display:grid;gap:18px}
  .ech-card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:clamp(18px,3vw,28px)}
  html[data-theme=dark] .ech-card{background:var(--wash,#1b1e26)}
  .ech-card h2{margin:0 0 6px;font-size:22px}
  .ech-sub{margin:0 0 16px;color:var(--ink2,#5b5f69);font-size:15px;line-height:1.5}
  .ech-plate{display:flex;align-items:stretch;border:2px solid #14161c;border-radius:10px;overflow:hidden;max-width:420px;background:#fff}
  .ech-plate i{display:grid;place-items:center;background:#0b3d91;color:#fff;font:700 11px/1 sans-serif;padding:0 10px;font-style:normal;letter-spacing:.05em}
  .ech-plate input{flex:1;min-width:0;border:0;outline:0;padding:14px 14px;font:800 24px/1 'Archivo',sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#14161c;background:#fff}
  .ech-err{min-height:20px;margin:8px 0 0;color:#c0141c;font-size:14px}
  .ech-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
  .ech-btn{display:inline-flex;align-items:center;gap:8px;padding:13px 20px;border-radius:10px;border:0;background:var(--red,#d90000);color:#fff;font:700 15px 'Archivo',sans-serif;text-decoration:none;cursor:pointer}
  .ech-btn.alt{background:transparent;color:var(--ink);border:1.5px solid var(--line)}
  .ech-btn:hover{filter:brightness(1.08)}
  .ech-go{display:none;margin-top:18px;padding:16px;border-radius:12px;background:rgba(11,61,145,.07);border:1px solid rgba(11,61,145,.2)}
  .ech-go.show{display:block}
  .ech-go b{font-size:18px;letter-spacing:.1em}
  .ech-steps{margin:10px 0 0;padding-left:20px;line-height:1.7;color:var(--ink2,#5b5f69)}
  .ech-ports{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-top:12px}
  .ech-port{display:block;padding:14px 16px;border:1px solid var(--line);border-radius:12px;text-decoration:none;color:var(--ink)}
  .ech-port b{display:block;margin-bottom:3px}
  .ech-port span{font-size:13.5px;color:var(--ink2,#5b5f69);line-height:1.4}
  .ech-port small{display:block;margin-top:6px;color:#0b3d91;font-weight:600}
  .ech-port:hover{border-color:var(--red,#d90000)}
  .ech-warn{border-left:4px solid #e0a100;background:rgba(224,161,0,.1);padding:12px 14px;border-radius:8px;font-size:14.5px;line-height:1.5}
</style>
@endpush

@section('content')
@include('site.partials.crumb', ['title' => 'E-Challan Check'])
@include('site.partials.ad-horizontal', ['ads' => $homeSettings->adsFor('horizontal')])

<div class="w">
  <div class="article-wrap emi-wrap">
    <div class="ech">
      <section class="ech-card" id="chk">
        <h2>Check your e-challan</h2>
        <p class="ech-sub">Enter your vehicle registration number. We will take you to the official government portal, where pending challans are shown and can be paid. Nothing you type here is stored.</p>
        <form id="echForm" novalidate autocomplete="off">
          <label for="echNo" class="ech-sub" style="margin:0 0 6px;display:block">Vehicle number</label>
          <div class="ech-plate"><i>IND</i><input id="echNo" maxlength="13" placeholder="MH12AB1234" inputmode="text" autocapitalize="characters" spellcheck="false" aria-describedby="echErr"></div>
          <div class="ech-err" id="echErr" role="alert"></div>
          <div class="ech-row"><button class="ech-btn" type="submit">Check challan <span aria-hidden="true">→</span></button></div>
        </form>

        <div class="ech-go" id="echGo" aria-live="polite">
          <p style="margin:0 0 6px">Vehicle number: <b id="echShow"></b></p>
          <ol class="ech-steps">
            <li>Open the official Parivahan e-Challan portal (button below).</li>
            <li>Select <b>Check Challan Details</b> and enter the vehicle number: <button type="button" class="ech-btn alt" id="echCopy" style="padding:4px 10px;font-size:13px">Copy number</button></li>
            <li>Enter the captcha, review the challans and pay online if any are pending.</li>
          </ol>
          <div class="ech-row">
            <a class="ech-btn" id="echOpen" href="{{ $portals['parivahan'][1] }}" target="_blank" rel="noopener noreferrer">Open Parivahan e-Challan ↗</a>
            <a class="ech-btn alt" href="#state" id="echState">State traffic police portals</a>
          </div>
          <div class="ech-err" id="echNote" role="status" style="color:inherit"></div>
        </div>
      </section>

      <div class="ech-warn"><b>Beware of fake challan messages.</b> Official portals end in <b>.gov.in</b>. Never pay through a link sent by SMS, WhatsApp or email, and never share an OTP or card PIN.</div>

      <section class="ech-card" id="state">
        <h2>Official portals</h2>
        <p class="ech-sub">These are the government and traffic police websites. If a link ever stops working, start from the Parivahan portal.</p>
        <div class="ech-ports">
          @foreach ($portals as $p)
            <a class="ech-port" href="{{ $p[1] }}" target="_blank" rel="noopener noreferrer"><b>{{ $p[0] }}</b><span>{{ $p[2] }}</span><small>{{ parse_url($p[1], PHP_URL_HOST) }} ↗</small></a>
          @endforeach
        </div>
        <p class="ech-sub" style="margin-top:14px">You can also use the free <b>mParivahan</b> mobile app (Google Play / App Store) to see your vehicle and challans.</p>
      </section>

      <div class="prose">
        <h2>How e-challans work</h2>
        <p>When a traffic camera or an officer records an offence, an e-challan is created against the vehicle registration number and sent to the registered mobile number. The notice states the offence, the amount and the date by which it must be paid or contested.</p>
        <p>Check your vehicle for pending challans from time to time, especially before buying or selling a used car, renewing insurance or transferring ownership. Looking for a used car? Browse <a href="{{ route('cars.index') }}">used cars</a>, or work out your running costs with the <a href="{{ route('costperkm') }}">cost per km calculator</a>.</p>
      </div>
      <h2 style="margin-top:8px">Frequently asked questions</h2>
      @foreach ($faq as $f)
        <details class="aside-box" style="padding:14px 18px;margin-bottom:2px">
          <summary style="font-weight:700;cursor:pointer">{{ $f['q'] }}</summary>
          <p class="m" style="margin:10px 0 0">{{ $f['a'] }}</p>
        </details>
      @endforeach
    </div>

    <aside>
      <div class="aside-box emi-lead">@include('site.partials.lead-form')</div>
      @include('site.partials.ad-vertical', ['page' => 'emi'])
      @include('site.partials.latest-news', ['articles' => $latest])
    </aside>
  </div>
</div>

<script>
(() => {
  const $ = s => document.querySelector(s), inp = $('#echNo');
  try { const v = localStorage.getItem('ech_no'); if (v) inp.value = v; } catch (e) {}
  // Standard plates (MH12AB1234, DL1CAB1234, KA01A1234) and Bharat series (22BH1234AA).
  const valid = v => /^[A-Z]{2}\d{1,2}[A-Z]{0,3}\d{4}$/.test(v) || /^\d{2}BH\d{4}[A-Z]{1,2}$/.test(v);
  const clean = () => inp.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  inp.addEventListener('input', () => { $('#echErr').textContent = ''; });
  $('#echForm').addEventListener('submit', e => {
    e.preventDefault(); const v = clean();
    if (!valid(v)) { $('#echGo').classList.remove('show'); $('#echErr').textContent = 'Please enter a valid vehicle number, for example MH12AB1234.'; inp.focus(); return; }
    inp.value = v; try { localStorage.setItem('ech_no', v); } catch (er) {}
    $('#echShow').textContent = v; $('#echGo').classList.add('show'); $('#echNote').textContent = '';
    $('#echGo').scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'nearest' });
  });
  $('#echCopy').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(clean()); $('#echNote').textContent = 'Number copied. Paste it on the government portal.'; }
    catch (e) { $('#echNote').textContent = 'Could not copy automatically. Please type ' + clean() + ' on the portal.'; }
  });
  $('#echOpen').addEventListener('click', async () => { try { await navigator.clipboard.writeText(clean()); } catch (e) {} });   // convenience: the number is ready to paste
})();
</script>
@endsection
