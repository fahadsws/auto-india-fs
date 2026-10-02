@extends('site.layout')
@section('title', 'Online Mechanic — Gaadi ki Problem, Kharcha aur Solution | ' . \App\Models\Setting::get('site.name'))
@section('description', 'Apni gaadi ki problem chat me batao. AI Mechanic Bhai sahi sawaal poochega, wajah samjhayega, kya karna hai batayega aur aapke sheher ke hisaab se fair repair cost dega — estimate download bhi karo.')

@push('head')
<script
  type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Online Mechanic', 'url' => route('mechanic'), 'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR']], JSON_UNESCAPED_SLASHES) !!}</script>
@php($faq = ($seo?->faqItems()) ?: [
  ['q' => 'Online mechanic kaise kaam karta hai?', 'a' => 'Aap problem batate ho, AI mechanic zaroori sawaal poochta hai (gaadi ki umar, kitna chali, sheher, haal hi mein kya kaam hua, kaunse raaste aur mausam). Phir wajah, kya karna hai aur kharche ka range batata hai.'],
  ['q' => 'Kya estimate bilkul sahi hota hai?', 'a' => 'Nahi. Chat se sirf andaza lagta hai. Final kharcha gaadi dekhne ke baad hi pata chalta hai. Estimate download karke 2 garage se compare karo taaki zyada paisa na jaye.'],
  ['q' => 'Kab gaadi chalana band kar dena chahiye?', 'a' => 'Brake ya steering mein dikkat, overheating, dhuan ya jalne/petrol ki smell, ya koi bada warning light ho to gaadi mat chalao aur tow karwao. Mechanic Bhai aise cases mein "Abhi mat chalao" ka signal deta hai.'],
  ['q' => 'Kharcha sheher ke hisaab se kyun badalta hai?', 'a' => 'Metro shehron mein labour aur branded parts mehnge hote hain, chhote shehron mein sasta. Genuine aur achhe aftermarket parts ke daam bhi alag hote hain. Estimate mein dono ka range diya jata hai.'],
])
@unless ($seo?->faqItems())
  <script
    type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()], JSON_UNESCAPED_SLASHES) !!}</script>
@endunless
@endpush

@section('content')
@include('site.partials.ad-horizontal', ['ads' => $homeSettings->adsFor('horizontal')])

<div class="w">
  <div class="article-wrap emi-wrap">
    <div>
      <div class="mch" id="mch" data-me="{{ route('assistant.me') }}" data-lead="{{ route('assistant.lead') }}" data-verify="{{ route('assistant.verify') }}"
        data-chat="{{ route('mechanic.chat') }}" data-url="{{ route('mechanic') }}" data-site="{{ \App\Models\Setting::get('site.name') }}">

        <header class="mch-hero">
          <div class="mch-hero-in">
            <span class="mch-kick"><i></i> LIVE · AI MECHANIC</span>
            <h1>Gaadi ko kya hua? <em>Mechanic Bhai</em> se poochho.</h1>
            <p>Gaadi ke X-ray par jo hissa pareshan kar raha hai use tap karo. Bhai sahi sawaal poochega, wajah samjhayega aur batayega <b>kitna kharcha hona chahiye</b> — taaki aap zyada paisa na do.</p>
            <ul class="mch-points"><li>🔍 Sahi sawaal</li><li>🧾 Fair kharcha</li><li>🚫 Upsell se bachao</li><li>📄 PDF estimate</li></ul>
          </div>
          <div class="mch-road" aria-hidden="true"></div>
        </header>

        {{-- gate: only shown if this visitor is not verified yet (same one-time check as the AI assistant) --}}
        <section class="mch-view show" id="vWait" aria-live="polite"><div class="mch-wait"><i class="mch-spin"></i><span>Garage ka shutter khul raha hai…</span></div></section>

        <form class="mch-view mch-panel" id="vLead" novalidate autocomplete="on">
          <h2>Pehle ek chhota sa parichay</h2>
          <p class="mch-sub">Ek baar details do aur email pe code verify karo — uske baad mechanic aapke saamne. Dobara form nahi poochhenge.</p>
          <div class="mch-row">
            <label class="mch-f"><span>Poora naam</span><input name="name" autocomplete="name" maxlength="60" placeholder="Rahul Sharma" required></label>
            <label class="mch-f"><span>Mobile</span><div class="mch-ph"><b>+91</b><input name="phone" inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10-digit number" required></div></label>
          </div>
          <div class="mch-row">
            <label class="mch-f"><span>Email <em>(verification code aayega)</em></span><input name="email" type="email" autocomplete="email" maxlength="120" placeholder="you@example.com" required></label>
            <label class="mch-f"><span>City <em>(optional)</em></span><input name="city" list="mchCities" autocomplete="address-level2" maxlength="80" placeholder="Aapka sheher"><datalist id="mchCities">@foreach (\App\Support\Filters::CITIES as $c)<option value="{{ is_array($c) ? ($c['name'] ?? '') : $c }}">@endforeach</datalist></label>
          </div>
          <input class="mch-hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
          <div class="mch-err" id="eLead" role="alert"></div>
          <button class="mch-btn" type="submit"><span>Code bhejo</span> <i>→</i></button>
          <p class="mch-fine">🔒 Details sirf aapki madad ke liye hain, kisi ko bechi nahi jaatin.</p>
        </form>

        <form class="mch-view mch-panel" id="vOtp" novalidate>
          <h2>Email check karo 📬</h2>
          <p class="mch-sub"><b id="otpMail"></b> pe 6 ank ka code gaya hai.</p>
          <div class="mch-otp" id="otpBoxes">@for ($i = 0; $i < 6; $i++)<input inputmode="numeric" maxlength="1" autocomplete="{{ $i ? 'off' : 'one-time-code' }}" aria-label="Digit {{ $i + 1 }}">@endfor</div>
          <div class="mch-err" id="eOtp" role="alert"></div>
          <button class="mch-btn" type="submit"><span>Verify karo &amp; mechanic se milo</span> <i>→</i></button>
          <p class="mch-fine"><button type="button" class="mch-lnk" id="otpResend" disabled>Code dobara bhejo</button> · <button type="button" class="mch-lnk" id="otpBack">Details badlo</button></p>
        </form>

        {{-- the workshop: X-ray car + chat --}}
        <section class="mch-view mch-shop" id="vShop">
          <div class="mch-left">
            <div class="mch-xray">
              <div class="mch-xhead"><b>Gaadi ka X-ray</b><span id="mXhint">Jo hissa pareshan kar raha hai, use tap karo 👆</span></div>
              <div class="mch-xcar" id="mXcar">
                <svg viewBox="0 0 600 260" role="img" aria-label="Car ka side view X-ray" class="mch-svg">
                  <ellipse cx="302" cy="242" rx="270" ry="9" fill="#000" opacity=".08"/>
                  <path d="M30 190V150Q30 128 58 122L132 108Q172 70 236 64H384Q436 66 468 104L540 114Q574 120 574 152V190Q574 200 564 200H36Q30 200 30 190Z" fill="#fff" stroke="#16181d" stroke-width="4" stroke-linejoin="round"/>
                  <path d="M168 106Q198 78 246 76H292V106Z" fill="#d9ecff" stroke="#16181d" stroke-width="3" stroke-linejoin="round"/>
                  <path d="M304 76H374Q408 80 430 106H304Z" fill="#d9ecff" stroke="#16181d" stroke-width="3" stroke-linejoin="round"/>
                  <path d="M298 76V192" stroke="#16181d" stroke-width="2" opacity=".3"/>
                  <rect x="33" y="136" width="24" height="14" rx="5" fill="#ffd23f" stroke="#16181d" stroke-width="3"/><rect x="548" y="130" width="22" height="14" rx="5" fill="#d90000" stroke="#16181d" stroke-width="3"/>
                  <g class="mch-xr" fill="rgba(217,0,0,.06)" stroke="#d90000" stroke-width="2.5" stroke-dasharray="6 5" stroke-linecap="round">
                    <rect x="66" y="124" width="92" height="52" rx="9"/><rect x="82" y="134" width="24" height="32" rx="4"/><rect x="112" y="134" width="24" height="32" rx="4"/>
                    <rect x="40" y="158" width="34" height="20" rx="4"/><rect x="236" y="150" width="84" height="32" rx="8"/>
                    <path d="M170 194H520Q548 194 548 170" fill="none"/><rect x="520" y="160" width="40" height="16" rx="8"/>
                    <path d="M180 112Q210 122 232 112" fill="none"/>
                  </g>
                  <g><circle cx="152" cy="200" r="48" fill="#f1ecdc" stroke="#16181d" stroke-width="3"/><circle cx="152" cy="200" r="38" fill="#16181d"/><circle cx="152" cy="200" r="23" fill="#e9e9e9"/><circle cx="152" cy="200" r="8" fill="#ffd23f" stroke="#16181d" stroke-width="2"/></g>
                  <g><circle cx="452" cy="200" r="48" fill="#f1ecdc" stroke="#16181d" stroke-width="3"/><circle cx="452" cy="200" r="38" fill="#16181d"/><circle cx="452" cy="200" r="23" fill="#e9e9e9"/><circle cx="452" cy="200" r="8" fill="#ffd23f" stroke="#16181d" stroke-width="2"/></g>
                </svg>
              </div>
              <div class="mch-zones" id="mZones"></div>
            </div>

            <details class="mch-job" open id="mJob">
              <summary><b>Gaadi ki Job Card</b><small id="mJobHint">Details bharo ya chat mein batao</small></summary>
              <div class="mch-jobgrid">
                <label>Gaadi<input id="pCar" maxlength="80" placeholder="Maruti Swift" autocomplete="off" list="mchCars"></label>
                <label>Umar<input id="pAge" maxlength="30" placeholder="2 saal" autocomplete="off"></label>
                <label>Kitni chali<input id="pKm" maxlength="30" placeholder="28,000 km" autocomplete="off"></label>
                <label>Fuel<select id="pFuel"><option value="">—</option>@foreach (\App\Services\Mechanic::FUELS as $f)<option value="{{ $f }}">{{ ucfirst($f) }}</option>@endforeach</select></label>
                <label class="wide">Sheher<input id="pCity" maxlength="60" placeholder="Pune" autocomplete="address-level2" list="mchCities"></label>
              </div>
              <datalist id="mchCars"><option value="Maruti Swift"><option value="Maruti Alto"><option value="Maruti WagonR"><option value="Maruti Baleno"><option value="Maruti Brezza"><option value="Maruti Ertiga"><option value="Hyundai i20"><option value="Hyundai Creta"><option value="Hyundai Venue"><option value="Tata Nexon"><option value="Tata Punch"><option value="Tata Harrier"><option value="Mahindra Thar"><option value="Mahindra Scorpio"><option value="Mahindra XUV700"><option value="Honda City"><option value="Toyota Innova Crysta"><option value="Kia Seltos"></datalist>
              <div class="mch-noted" id="mNoted" hidden><b>Maine ye note kiya:</b><ul id="mNotedList"></ul></div>
            </details>
          </div>

          <div class="mch-chat">
            <div class="mch-chead"><i class="mch-av" aria-hidden="true">🔧</i><div><b>Mechanic Bhai</b><small>Online · 20 saal ka experience (AI)</small></div></div>
            <div class="mch-msgs" id="mMsgs" aria-live="polite"></div>
            <div class="mch-quick" id="mQuick"></div>
            <form class="mch-input" id="mForm" autocomplete="off">
              <button type="button" class="mch-mic" id="mMic" title="Bol ke batao" aria-label="Bol ke batao" hidden>🎙</button>
              <textarea id="mText" rows="1" maxlength="500" placeholder="Problem likho…" aria-label="Apni problem likho"></textarea>
              <button class="mch-send" id="mSend" aria-label="Bhejo">➤</button>
            </form>
            <div class="mch-foot"><span id="mLeft"></span><span>AI se andaza hai · <button type="button" class="mch-lnk" id="mReset">Nayi problem</button></span></div>
          </div>
        </section>

        {{-- diagnosis --}}
        <section class="mch-result" id="mResult" hidden aria-live="polite"></section>
      </div>

      <div class="prose">
        <h2>Online mechanic se sahi kharcha kaise pata chalega</h2>
        <p>Mechanic Bhai sirf "ye kharab hai" nahi bolta. Wo pehle samajhta hai ki problem kab hoti hai, gaadi kitni purani hai, haal hi mein tyre ya battery badli ya nahi, aap kaunse raaste par zyada chalate ho aur mausam kaisa hai. Fir wajah, sasti jaanch, zaroori kaam aur <b>parts + labour ka range</b> aapke sheher ke hisaab se deta hai, aur ye bhi batata hai ki kaunsa kaam <b>mat karwana</b>.</p>
        <p>Result ko "Estimate download karo" se PDF bana lo aur garage mein dikhao. Gaadi ka total running cost jaanna ho to <a href="{{ route('costperkm') }}">cost per km calculator</a> bhi try karo.</p>
      </div>
      <h2 style="margin-top:28px">Frequently asked questions</h2>
      @foreach ($faq as $f)
        <details class="aside-box" style="padding:14px 18px;margin-bottom:10px">
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
  const root = document.getElementById('mch'), $ = (s, r = root) => r.querySelector(s), $$ = (s, r = root) => [...r.querySelectorAll(s)];
  const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
  const ls = { get: k => { try { return localStorage.getItem(k) } catch (e) { return null } }, set: (k, v) => { try { localStorage.setItem(k, v) } catch (e) {} }, del: k => { try { localStorage.removeItem(k) } catch (e) {} } };
  const ss = { get: k => { try { return sessionStorage.getItem(k) } catch (e) { return null } }, set: (k, v) => { try { sessionStorage.setItem(k, v) } catch (e) {} }, del: k => { try { sessionStorage.removeItem(k) } catch (e) {} } };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  let token = ls.get('aw_token') || '', visitor = '', busy = false;
  let state = { msgs: [], facts: {}, diag: null, zone: '', talk: [], hot: [] };
  try { const sv = JSON.parse(ss.get('mch_state') || 'null'); if (sv && Array.isArray(sv.msgs)) state = { msgs: sv.msgs.slice(-40), facts: sv.facts || {}, diag: sv.diag || null, zone: sv.zone || '', talk: sv.talk || [], hot: sv.hot || [] }; } catch (e) {}
  const save = () => ss.set('mch_state', JSON.stringify(state));
  const views = { wait: $('#vWait'), lead: $('#vLead'), otp: $('#vOtp'), shop: $('#vShop') };
  const show = v => { Object.entries(views).forEach(([k, n]) => n.classList.toggle('show', k === v)); $('#mResult').hidden = !(v === 'shop' && state.diag); };
  const api = async (url, body) => {
    const r = await fetch(url, { method: body ? 'POST' : 'GET', credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(token ? { 'X-Assistant-Token': token } : {}) } });
    let data = {}; try { data = await r.json(); } catch (e) {}
    return { ok: r.ok, status: r.status, data };
  };
  const firstError = d => (d.errors && Object.values(d.errors)[0] && Object.values(d.errors)[0][0]) || d.message || 'Kuch gadbad ho gayi, dobara try karo.';
  const inr = n => '₹' + Math.round(n).toLocaleString('en-IN');
  const range = (a, b) => (a === b ? inr(a) : inr(a) + ' – ' + inr(b));

  /* ---------- who is this visitor? Verified visitors never see the form again. ---------- */
  function enter(d) {
    if (d && d.token) { token = d.token; ls.set('aw_token', token); }
    visitor = ((d && d.name) || '').split(' ')[0];
    if (typeof d.left === 'number') leftHint(d.left);
    loadProfile(); show('shop'); renderAll();
    if (!state.msgs.length) greet();
  }
  (async () => {
    const r = await api(root.dataset.me).catch(() => null);
    if (r && r.ok && r.data.verified) enter(r.data);
    else if (r && r.ok && r.data.gate) { token = ''; ls.del('aw_token'); show('lead'); }
    else if (r && r.ok) enter(r.data);      // gate switched off in settings
    else show('lead');
  })();

  /* ---------- lead form + email OTP (same endpoints as the assistant) ---------- */
  const fLead = views.lead, eLead = $('#eLead');
  fLead.addEventListener('submit', async e => {
    e.preventDefault(); eLead.textContent = '';
    const v = Object.fromEntries(new FormData(fLead).entries());
    v.phone = (v.phone || '').replace(/\D/g, '').replace(/^91(?=\d{10}$)/, '');
    $$('.mch-f', fLead).forEach(x => x.classList.remove('bad'));
    const bad = (n, m) => { const i = fLead.elements[n]; i.closest('.mch-f').classList.add('bad'); i.focus(); eLead.textContent = m; };
    if (!/^[\p{L}\p{M}\s.'\-]{2,60}$/u.test((v.name || '').trim())) return bad('name', 'Apna sahi naam likho.');
    if (!/^[6-9]\d{9}$/.test(v.phone)) return bad('phone', '10 ank ka sahi mobile number daalo.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test((v.email || '').trim())) return bad('email', 'Sahi email daalo.');
    if (v.city && !ls.get('mch_city')) ls.set('mch_city', v.city);
    const btn = $('button[type=submit]', fLead); btn.disabled = true;
    const r = await api(root.dataset.lead, v).catch(() => ({ ok: false, data: { message: 'Connection problem. Dobara try karo.' } }));
    btn.disabled = false;
    if (!r.ok) { eLead.textContent = firstError(r.data); return; }
    if (r.data.verified) return enter(r.data);
    $('#otpMail').textContent = r.data.email || v.email;
    show('otp'); startResend(60); $('#otpBoxes input').focus();
  });
  const boxes = $$('#otpBoxes input'), eOtp = $('#eOtp'), resend = $('#otpResend'); let rt = null;
  boxes.forEach((b, i) => {
    b.addEventListener('input', () => { b.value = b.value.replace(/\D/g, '').slice(-1); if (b.value && boxes[i + 1]) boxes[i + 1].focus(); if (boxes.every(x => x.value)) views.otp.requestSubmit(); });
    b.addEventListener('keydown', e => { if (e.key === 'Backspace' && !b.value && boxes[i - 1]) boxes[i - 1].focus(); });
    b.addEventListener('paste', e => { const t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!t) return; e.preventDefault(); t.split('').forEach((c, j) => boxes[j] && (boxes[j].value = c)); boxes[Math.min(t.length, 5)].focus(); if (t.length === 6) views.otp.requestSubmit(); });
  });
  function startResend(s) { clearInterval(rt); resend.disabled = true; const tick = () => { resend.textContent = s > 0 ? 'Code dobara bhejo (' + s + 's)' : 'Code dobara bhejo'; if (s <= 0) { resend.disabled = false; clearInterval(rt); } s--; }; tick(); rt = setInterval(tick, 1000); }
  views.otp.addEventListener('submit', async e => {
    e.preventDefault(); eOtp.textContent = '';
    const code = boxes.map(b => b.value).join(''); if (code.length !== 6) { eOtp.textContent = '6 ank ka code daalo.'; return; }
    const btn = $('button[type=submit]', views.otp); btn.disabled = true;
    const r = await api(root.dataset.verify, { code }).catch(() => ({ ok: false, data: { message: 'Connection problem. Dobara try karo.' } }));
    btn.disabled = false;
    if (!r.ok) { eOtp.textContent = firstError(r.data); boxes.forEach(b => b.value = ''); boxes[0].focus(); if (r.data.restart) show('lead'); return; }
    enter(r.data);
  });
  resend.addEventListener('click', async () => { const v = Object.fromEntries(new FormData(fLead).entries()); v.phone = (v.phone || '').replace(/\D/g, ''); resend.disabled = true; const r = await api(root.dataset.lead, v).catch(() => null); if (r && r.ok) startResend(60); else { eOtp.textContent = r ? firstError(r.data) : 'Connection problem.'; resend.disabled = false; } });
  $('#otpBack').addEventListener('click', () => show('lead'));

  /* ---------- job card (profile) ---------- */
  const P = { car: $('#pCar'), age: $('#pAge'), km: $('#pKm'), fuel: $('#pFuel'), city: $('#pCity') };
  const FUELS = ['petrol', 'diesel', 'cng', 'electric', 'hybrid'];
  function loadProfile() {
    let p = {}; try { p = JSON.parse(ls.get('mch_profile') || '{}') || {}; } catch (e) {}
    if (!p.city && ls.get('mch_city')) p.city = ls.get('mch_city');
    Object.keys(P).forEach(k => { if (p[k] && !P[k].value) P[k].value = p[k]; });
  }
  const profile = () => Object.fromEntries(Object.keys(P).map(k => [k, (P[k].value || '').trim()]));
  const saveProfile = () => { ls.set('mch_profile', JSON.stringify(profile())); jobHint(); };
  Object.values(P).forEach(el => el.addEventListener('change', saveProfile));
  const jobHint = () => { const n = Object.values(profile()).filter(Boolean).length; $('#mJobHint').textContent = n >= 4 ? 'Details ready ✔' : 'Details bharo ya chat mein batao'; };
  const NOTED = { symptom: 'Problem', recent_work: 'Haal ka kaam', route: 'Raaste', weather: 'Mausam' };
  function applyFacts(f) {
    if (!f) return;
    Object.assign(state.facts, f);
    ['car', 'age', 'km', 'city'].forEach(k => { if (f[k] && !P[k].value.trim()) P[k].value = f[k]; });
    if (f.fuel && !P.fuel.value) { const v = FUELS.find(x => f.fuel.toLowerCase().includes(x)); if (v) P.fuel.value = v; }
    saveProfile(); renderNoted();
  }
  function renderNoted() {
    const rows = Object.entries(NOTED).filter(([k]) => state.facts[k]).map(([k, l]) => '<li><span>' + l + '</span>' + esc(state.facts[k]) + '</li>').join('');
    $('#mNoted').hidden = !rows; $('#mNotedList').innerHTML = rows;
  }

  /* ---------- chat ---------- */
  const msgsEl = $('#mMsgs'), quickEl = $('#mQuick'), text = $('#mText');
  /* ---------- Gaadi ka X-ray: tappable car parts ---------- */
  const ZONES = {
    engine: { n: 'Engine', e: '🔧', x: 19, y: 55, chips: ['Start nahi hoti', 'Awaaz aati hai', 'Dhuan / smell aati hai', 'Power / pickup kam', 'Garam ho jaata hai'], k: /engine|oil|spark|injector|coolant|radiator|overheat|misfire|timing|air filter|turbo|head gasket/i },
    battery: { n: 'Battery', e: '🔋', x: 9, y: 66, chips: ['Start mein dikkat', 'Light dim hai', 'Warning light aayi', 'Battery jaldi down hoti hai'], k: /battery|alternator|starter|electrical|wiring|fuse|sensor|ecu|light/i },
    ac: { n: 'AC', e: '❄️', x: 39, y: 36, chips: ['Thanda nahi karta', 'Smell aati hai', 'Awaaz aati hai', 'Paani tapakta hai'], k: /\bac\b|a\/c|compressor|condenser|refrigerant|cabin filter|blower|evaporator/i },
    gearbox: { n: 'Gear / Clutch', e: '⚙️', x: 46, y: 64, chips: ['Gear nahi lagta', 'Clutch hard / slip', 'Jhatke lagte hain', 'Awaaz aati hai'], k: /gear|clutch|transmission|cvt|amt|flywheel/i },
    suspension: { n: 'Suspension / Steering', e: '〰️', x: 50, y: 84, chips: ['Gaddhe pe thak-thak awaaz', 'Steering kaanp raha hai', 'Steering bhaari hai', 'Gaadi hilti hai'], k: /suspension|shock|strut|bush|steering|link rod|ball joint|rack|control arm/i },
    brakes: { n: 'Brake', e: '🛑', x: 25, y: 79, chips: ['Brake pe awaaz', 'Pedal naram / neeche jaata hai', 'Brake pe gaadi kaanpti hai', 'Ek taraf kheenchti hai'], k: /brake|pad|disc|rotor|caliper|abs/i },
    tyres: { n: 'Tyre', e: '⭕', x: 76, y: 62, chips: ['Jaldi ghis rahe hain', 'Hawa kam hoti rehti hai', 'Speed pe vibration', 'Ek taraf kheenchti hai'], k: /tyre|tire|wheel|alignment|balanc|puncture|tpms/i },
    exhaust: { n: 'Exhaust / Fuel', e: '💨', x: 92, y: 71, chips: ['Dhuan nikalta hai', 'Zyada awaaz', 'Mileage gir gaya', 'Petrol ki smell'], k: /exhaust|silencer|catalytic|dpf|fuel|egr|emission|mileage/i },
  };
  const ZKEYS = Object.keys(ZONES), xcar = $('#mXcar'), zonesEl = $('#mZones');
  xcar.insertAdjacentHTML('beforeend', ZKEYS.map(z => '<button type="button" class="mch-pin" data-zone="' + z + '" style="left:' + ZONES[z].x + '%;top:' + ZONES[z].y + '%" aria-label="' + ZONES[z].n + '"><b>' + ZONES[z].e + '</b><span></span></button>').join(''));
  zonesEl.innerHTML = ZKEYS.map(z => '<button type="button" data-zone="' + z + '"><b>' + ZONES[z].e + '</b> ' + ZONES[z].n + '</button>').join('');
  function paintZones() {
    const hot = Object.fromEntries((state.hot || []).map(h => [h.z, h.pct]));
    $$('[data-zone]', $('#vShop')).forEach(el => {
      const z = el.dataset.zone, isHot = z in hot;
      el.classList.toggle('on', state.zone === z); el.classList.toggle('talk', !isHot && (state.talk || []).includes(z)); el.classList.toggle('hot', isHot);
      const sp = $('span', el); if (sp) sp.textContent = isHot ? ZONES[z].n + (hot[z] ? ' · ' + hot[z] + '%' : '') : ZONES[z].n;
    });
    $('#mXhint').textContent = state.hot && state.hot.length ? 'Laal nishaan = yahan problem ho sakti hai 🔴' : (state.zone ? ZONES[state.zone].n + ' chuna — neeche chat mein batao' : 'Jo hissa pareshan kar raha hai, use tap karo 👆');
  }
  function zonesFrom(g) {
    const out = []; const add = (z, pct) => { if (z && ZONES[z] && !out.some(o => o.z === z) && out.length < 3) out.push({ z, pct: pct || 0 }); };
    const pctFor = z => { const c = (g.causes || []).find(c => ZONES[z].k.test(c.name + ' ' + (c.why || ''))); return c ? c.likelihood : 0; };
    (g.zones || []).forEach(z => add(z, pctFor(z)));
    if (!out.length) { (g.causes || []).forEach(c => ZKEYS.forEach(z => { if (ZONES[z].k.test(c.name)) add(z, c.likelihood); })); }
    if (!out.length) { const t = g.title + ' ' + (g.fix || []).map(f => f.work).join(' '); ZKEYS.forEach(z => { if (ZONES[z].k.test(t)) add(z, 0); }); }
    return out;
  }
  const zoneOf = txt => ZKEYS.find(z => ZONES[z].k.test(txt)) || '';
  function pickZone(z) {
    if (busy || !ZONES[z]) return;
    state.zone = z; paintZones();
    const m = { role: 'assistant', content: ZONES[z].n + ' mein kya dikkat hai? Neeche se chuno ya apne shabdon mein likho.' };
    state.msgs.push(m); bubble('assistant', fmtMsg(m.content)); save();
    setQuick(ZONES[z].chips.concat(['Kuch aur batata hoon']), ZONES[z].n);
    if (matchMedia('(max-width: 860px)').matches) $('.mch-chat').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  xcar.addEventListener('click', e => { const b = e.target.closest('[data-zone]'); if (b) pickZone(b.dataset.zone); });
  zonesEl.addEventListener('click', e => { const b = e.target.closest('[data-zone]'); if (b) pickZone(b.dataset.zone); });

  const STARTERS = ['Awaaz aa rahi hai', 'Mileage gir gaya', 'Warning light aayi hai', 'Start nahi ho rahi'];
  const bubble = (role, html, cls) => { const d = document.createElement('div'); d.className = 'mch-m ' + role + (cls ? ' ' + cls : ''); d.innerHTML = (role === 'assistant' ? '<i class="mch-av" aria-hidden="true">🔧</i>' : '') + '<div class="mch-b">' + html + '</div>'; msgsEl.appendChild(d); msgsEl.scrollTop = msgsEl.scrollHeight; return d; };
  const fmtMsg = s => esc(s).replace(/\n/g, '<br>');
  function renderAll() {
    msgsEl.innerHTML = ''; state.msgs.forEach(m => bubble(m.role, fmtMsg(m.content)));
    renderNoted(); jobHint(); paintZones();
    if (state.diag) renderDiag(state.diag, true);
    setQuick(state.diag ? ['Naya sawaal poochna hai', 'Nayi problem'] : (state.msgs.length > 1 ? [] : STARTERS));
  }
  function greet() {
    const hi = visitor ? 'Namaste ' + visitor + '! ' : 'Namaste! ';
    const m = { role: 'assistant', content: hi + 'Main Mechanic Bhai. Gaadi mein kya dikkat hai? Jitna ho sake saaf batao — kab hoti hai, kaisi awaaz ya smell hai. Pehle X-ray par pareshan hissa tap karo, ya seedha likh do. Job Card mein gaadi ki details bhar doge to estimate aur sahi aayega.' };
    state.msgs.push(m); save(); bubble('assistant', fmtMsg(m.content)); setQuick(STARTERS);
  }
  let quickPre = '';
  function setQuick(list, pre) { quickPre = pre || ''; quickEl.innerHTML = (list || []).map(q => '<button type="button">' + esc(q) + '</button>').join(''); }
  quickEl.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return; const q = b.textContent;
    if (q === 'Nayi problem') return reset();
    if (q === 'Naya sawaal poochna hai') { text.focus(); return; }
    if (q === 'Kuch aur batata hoon') { text.value = quickPre + ': '; text.focus(); return; }
    send(quickPre ? quickPre + ': ' + q : q);
  });
  const typing = () => bubble('assistant', '<span class="mch-dots"><i></i><i></i><i></i></span>', 'typing');
  const leftHint = n => { $('#mLeft').textContent = n > 0 ? n + ' message aaj ke baaki' : 'Aaj ka limit poora'; };
  text.addEventListener('input', () => { text.style.height = 'auto'; text.style.height = Math.min(text.scrollHeight, 130) + 'px'; });
  text.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#mForm').requestSubmit(); } });
  $('#mForm').addEventListener('submit', e => { e.preventDefault(); const v = text.value.trim(); if (v) send(v); });

  async function send(msg) {
    if (busy) return; busy = true; $('#mSend').disabled = true; text.value = ''; text.style.height = 'auto'; setQuick([]);
    const history = state.msgs.slice(-14).map(m => ({ role: m.role, content: m.content }));
    state.msgs.push({ role: 'user', content: msg }); bubble('user', fmtMsg(msg)); save();
    const t = typing();
    const r = await api(root.dataset.chat, { message: msg, history: history.filter(h => h.content), profile: (({ car, age, km, fuel, city }) => ({ car, age, km, fuel, city }))(profile()) }).catch(() => ({ ok: false, status: 0, data: { message: 'Connection problem. Dobara try karo.' } }));
    t.remove(); busy = false; $('#mSend').disabled = false;
    if (r.status === 401 && r.data.gate) { show('lead'); return; }
    if (!r.ok) { state.msgs.pop(); save(); bubble('assistant', esc(firstError(r.data)), 'err'); setQuick([]); text.value = msg; return; }
    const d = r.data;
    state.msgs.push({ role: 'assistant', content: d.reply }); bubble('assistant', fmtMsg(d.reply));
    applyFacts(d.facts); if (typeof d.left === 'number') leftHint(d.left);
    if (Array.isArray(d.zones) && d.zones.length) state.talk = d.zones;
    if (d.diagnosis) { if (!d.diagnosis.zones) d.diagnosis.zones = d.zones || []; state.diag = d.diagnosis; state.hot = zonesFrom(d.diagnosis); renderDiag(d.diagnosis); }
    paintZones();
    save(); setQuick(d.diagnosis ? ['Naya sawaal poochna hai', 'Nayi problem'] : (d.quick_replies || []));
    if (!d.diagnosis) text.focus();
  }
  function reset() { state = { msgs: [], facts: {}, diag: null, zone: '', talk: [], hot: [] }; ss.del('mch_state'); $('#mResult').hidden = true; $('#mResult').innerHTML = ''; renderNoted(); paintZones(); msgsEl.innerHTML = ''; greet(); window.scrollTo({ top: root.offsetTop - 10, behavior: 'smooth' }); }
  $('#mReset').addEventListener('click', reset);

  // dictation (Chrome/Edge/Safari): optional
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (SR) {
    const mic = $('#mMic'); mic.hidden = false; let rec = null;
    mic.addEventListener('click', () => {
      if (rec) { rec.stop(); return; }
      rec = new SR(); rec.lang = 'hi-IN'; rec.interimResults = false; rec.maxAlternatives = 1;
      rec.onresult = e => { text.value = ((text.value ? text.value + ' ' : '') + e.results[0][0].transcript).slice(0, 500); text.dispatchEvent(new Event('input')); };
      rec.onend = () => { rec = null; mic.classList.remove('on'); }; rec.onerror = () => { rec = null; mic.classList.remove('on'); };
      mic.classList.add('on'); try { rec.start(); } catch (e) { rec = null; mic.classList.remove('on'); }
    });
  }

  /* ---------- diagnosis card ---------- */
  const SEV = { low: ['Chal sakti hai', 'Zyada tension nahi — par theek zaroor karwao.', 1, '✔'], medium: ['Jaldi dikhao', 'Ek-do hafte mein garage dikha do.', 2, '⚠'], high: ['Bahut jaldi dikhao', 'Aise hi zyada mat chalao.', 3, '⚠'], stop: ['Abhi mat chalao', 'Gaadi roko aur tow karwao — safety ka mamla hai.', 4, '⛔'] };
  const list = (title, arr, cls) => arr && arr.length ? '<div class="mch-sec ' + (cls || '') + '"><h4>' + title + '</h4><ul>' + arr.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul></div>' : '';
  function renderDiag(g, quiet) {
    const s = SEV[g.severity] || SEV.medium, el = $('#mResult'), hot = state.hot && state.hot.length ? state.hot : zonesFrom(g);
    const causes = (g.causes || []).map(c => '<li><div class="mch-cb"><b>' + esc(c.name) + '</b><span>' + c.likelihood + '%</span></div><div class="mch-bar"><i style="width:' + c.likelihood + '%"></i></div>' + (c.why ? '<small>' + esc(c.why) + '</small>' : '') + '</li>').join('');
    const rows = (g.fix || []).map(f => { const z = zoneOf(f.work); return '<tr><td><b>' + (z ? '<em class="mch-ztag">' + ZONES[z].e + '</em>' : '') + esc(f.work) + '</b>' + (f.note ? '<small>' + esc(f.note) + '</small>' : '') + '</td><td>' + range(f.parts_min, f.parts_max) + '</td><td>' + range(f.labour_min, f.labour_max) + '</td><td><b>' + range(f.parts_min + f.labour_min, f.parts_max + f.labour_max) + '</b></td></tr>'; }).join('');
    const pm = (g.fix || []).reduce((a, f) => a + (f.parts_min + f.parts_max) / 2, 0), lm = (g.fix || []).reduce((a, f) => a + (f.labour_min + f.labour_max) / 2, 0), pp = pm + lm ? Math.round(pm / (pm + lm) * 100) : 50;
    const zt = hot.map(h => '<span class="mch-zchip">' + ZONES[h.z].e + ' ' + ZONES[h.z].n + (h.pct ? ' · ' + h.pct + '%' : '') + '</span>').join('');
    el.innerHTML = '<div class="mch-rhead"><b>SERVICE REPORT</b><span>' + new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) + '</span></div>'
      + '<div class="mch-rtop"><div class="mch-rmain">'
      + '<div class="mch-sev s' + s[2] + '"><i class="mch-light" aria-hidden="true">' + s[3] + '</i><div><b>' + s[0] + '</b><span>' + s[1] + '</span></div></div>'
      + '<h3>' + esc(g.title) + '</h3>' + (zt ? '<div class="mch-zchips">' + zt + '</div>' : '') + '<p class="mch-summary">' + esc(g.summary) + '</p></div>'
      + (rows ? '<div class="mch-cost"><small>Andaazan kharcha</small><b>' + range(g.total_min, g.total_max) + '</b><div class="mch-split" title="Parts vs labour"><i style="width:' + pp + '%"></i></div><div class="mch-splitl"><span>Parts</span><span>Labour</span></div>' + (g.city_note ? '<p>📍 ' + esc(g.city_note) + '</p>' : '') + '</div>' : '')
      + '</div><div class="mch-rbody">'
      + list('Kyun ho raha hai', g.why)
      + (causes ? '<div class="mch-sec"><h4>Ho sakte hain ye kaaran</h4><ul class="mch-causes">' + causes + '</ul></div>' : '')
      + list('Pehle ye check karo (sasta)', g.confirm_with)
      + list('Abhi kya karein taaki aur na bigde', g.stop_it_now, 'warn')
      + (rows ? '<div class="mch-sec"><h4>Kaam aur kharcha</h4><div class="mch-tw"><table class="mch-tbl"><thead><tr><th>Kaam</th><th>Parts</th><th>Labour</th><th>Total</th></tr></thead><tbody>' + rows + '</tbody><tfoot><tr><td colspan="3">Andaazan kul kharcha</td><td><b>' + range(g.total_min, g.total_max) + '</b></td></tr></tfoot></table></div></div>' : '')
      + list('Ye mat karwana', g.avoid, 'bad') + list('Aap khud kar sakte ho', g.diy) + list('Aage se bachne ke liye', g.prevention) + list('Garage mein ye poochho', g.ask_garage)
      + '<p class="mch-disc">' + esc(g.disclaimer) + '</p>'
      + '<div class="mch-acts"><button type="button" class="mch-btn" id="dDl">⬇ Estimate download karo (PDF)</button><button type="button" class="mch-btn alt" id="dWa">WhatsApp pe bhejo</button><button type="button" class="mch-btn alt" id="dCp">Copy karo</button></div><div class="mch-note" id="dNote" role="status"></div></div>';
    el.hidden = false; paintZones(); $('#dDl').onclick = () => printCard(g); $('#dWa').onclick = () => window.open('https://wa.me/?text=' + encodeURIComponent(summaryText(g)), '_blank', 'noopener'); $('#dCp').onclick = copyText;
    if (!quiet) setTimeout(() => el.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }), 150);
  }
  const carLine = () => { const p = profile(); return [p.car, p.age && p.age + ' purani', p.km, p.fuel && p.fuel.toUpperCase(), p.city].filter(Boolean).join(' · '); };
  const summaryText = g => '🔧 ' + g.title + (carLine() ? '\n' + carLine() : '') + '\n' + (SEV[g.severity] || SEV.medium)[0] + '\n\n' + g.summary + '\n\nAndaazan kharcha: ' + range(g.total_min, g.total_max) + '\n(AI estimate — garage mein confirm karo)\n\n' + root.dataset.url;
  async function copyText() { const g = state.diag; if (!g) return; try { await navigator.clipboard.writeText(summaryText(g)); $('#dNote').textContent = 'Copy ho gaya ✔'; } catch (e) { $('#dNote').textContent = 'Copy nahi ho paya — text select karke copy karo.'; } }

  /* ---------- download: printable job card (Print -> Save as PDF) ---------- */
  function printCard(g) {
    const p = profile(), f = state.facts, now = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }), s = SEV[g.severity] || SEV.medium;
    const li = (t, a) => a && a.length ? '<h3>' + t + '</h3><ul>' + a.map(x => '<li>' + esc(x) + '</li>').join('') + '</ul>' : '';
    const det = [['Gaadi', p.car], ['Umar', p.age], ['Kitni chali', p.km], ['Fuel', p.fuel], ['Sheher', p.city], ['Problem', f.symptom], ['Haal ka kaam', f.recent_work], ['Raaste', f.route], ['Mausam', f.weather]].filter(x => x[1]);
    const rows = (g.fix || []).map(x => '<tr><td>' + esc(x.work) + (x.note ? '<br><small>' + esc(x.note) + '</small>' : '') + '</td><td>' + range(x.parts_min, x.parts_max) + '</td><td>' + range(x.labour_min, x.labour_max) + '</td><td><b>' + range(x.parts_min + x.labour_min, x.parts_max + x.labour_max) + '</b></td></tr>').join('');
    const html = '<!doctype html><html><head><meta charset="utf-8"><title>Repair estimate - ' + esc(g.title) + '</title><style>'
      + '@page{size:A4;margin:14mm}*{box-sizing:border-box}body{font:13px/1.5 Arial,Helvetica,sans-serif;color:#111;margin:0}'
      + '.h{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:4px solid #ff7a1a;padding-bottom:10px;margin-bottom:14px}.h b{font-size:20px}.h span{color:#555}'
      + 'h2{margin:0 0 4px;font-size:20px}h3{margin:16px 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#b34700}ul{margin:0;padding-left:18px}li{margin:2px 0}'
      + '.sev{display:inline-block;padding:3px 10px;border-radius:99px;background:#111;color:#fff;font-weight:700;font-size:12px;margin-bottom:8px}'
      + 'table{border-collapse:collapse;width:100%}th,td{border:1px solid #bbb;padding:6px 8px;text-align:left;vertical-align:top}th{background:#f2f2f2}tfoot td{background:#fff3e8;font-weight:700}small{color:#555}'
      + '.det{display:grid;grid-template-columns:1fr 1fr;gap:2px 18px;margin:0 0 6px}.det div{border-bottom:1px dotted #bbb;padding:3px 0}.det span{color:#666;display:inline-block;min-width:92px}'
      + '.bad li{color:#a40000}.f{margin-top:18px;padding-top:8px;border-top:1px solid #bbb;color:#555;font-size:11px}'
      + '</style></head><body>'
      + '<div class="h"><div><b>' + esc(root.dataset.site) + ' · Mechanic Bhai</b><br><span>AI repair estimate / job card</span></div><span>' + now + '</span></div>'
      + (det.length ? '<div class="det">' + det.map(x => '<div><span>' + x[0] + '</span>' + esc(x[1]) + '</div>').join('') + '</div>' : '')
      + '<h2>' + esc(g.title) + '</h2><div class="sev">' + s[0] + '</div><p>' + esc(g.summary) + '</p>'
      + li('Kyun ho raha hai', g.why)
      + ((g.causes || []).length ? '<h3>Ho sakte hain ye kaaran</h3><ul>' + g.causes.map(c => '<li><b>' + esc(c.name) + '</b> (' + c.likelihood + '%)' + (c.why ? ' — ' + esc(c.why) : '') + '</li>').join('') + '</ul>' : '')
      + li('Pehle ye check karo', g.confirm_with) + li('Abhi kya karein', g.stop_it_now)
      + (rows ? '<h3>Kaam aur andaazan kharcha</h3><table><thead><tr><th>Kaam</th><th>Parts</th><th>Labour</th><th>Total</th></tr></thead><tbody>' + rows + '</tbody><tfoot><tr><td colspan="3">Andaazan kul kharcha</td><td>' + range(g.total_min, g.total_max) + '</td></tr></tfoot></table>' + (g.city_note ? '<p><small>' + esc(g.city_note) + '</small></p>' : '') : '')
      + '<div class="bad">' + li('Ye mat karwana', g.avoid) + '</div>' + li('Garage mein ye poochho', g.ask_garage)
      + '<div class="f">' + esc(g.disclaimer) + ' Kharcha parts ki quality, sheher aur gaadi ke model par nirbhar karta hai. · ' + esc(root.dataset.url) + '</div>'
      + '</body></html>';
    const fr = document.createElement('iframe'); fr.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0'; document.body.appendChild(fr);
    fr.onload = () => { try { fr.contentWindow.focus(); fr.contentWindow.print(); } catch (e) { $('#dNote').textContent = 'Print khul nahi paya — browser ke Print option se save karo.'; } setTimeout(() => fr.remove(), 60000); };
    fr.srcdoc = html; $('#dNote').textContent = 'Print window mein "Save as PDF" chuno 📄';
  }
})();
</script>
@endsection
