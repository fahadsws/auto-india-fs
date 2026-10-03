@extends('site.layout')
@section('title', 'Roast My Car — AI Hinglish Roast + Share Card | ' . \App\Models\Setting::get('site.name'))
@section('description', 'Apni gaadi ka naam daalo aur AI se mazedaar Hinglish roast pao. Result ek e-challan share card ban jaata hai — WhatsApp aur Instagram pe doston ko bhejo. Halka mazaak, koi gaali ya brand-bashing nahi.')

@push('head')
<script
  type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Roast My Car', 'url' => route('roast'), 'applicationCategory' => 'EntertainmentApplication', 'operatingSystem' => 'Any', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR']], JSON_UNESCAPED_SLASHES) !!}</script>
@php($faq = ($seo?->faqItems()) ?: [
  ['q' => 'Roast my car kya hai?', 'a' => 'Aap apni gaadi ka naam aur kuch aadatein batate ho, AI Hinglish mein ek halka-phulka mazedaar roast likhta hai, aur result ek share karne layak e-challan card ban jaata hai.'],
  ['q' => 'Kya meri gaadi ya company ko gaali di jaayegi?', 'a' => 'Nahi. Roast sirf owner ki funny aadaton (EMI, parking, AC 24 pe, dhulai) par hota hai. Kisi brand ko neecha dikhana, gaali ya kisi dharm, jaati, region ya insaan par mazaak is feature ke rules mein allowed nahi hai.'],
  ['q' => 'Thar ya Scorpio jaisi gaadiyon ka roast kyun nahi hota?', 'a' => 'Kuch gaadiyon ki road presence ke saamne roast chhota padta hai. Thar, Scorpio, Fortuner, Bolero jaisi gaadiyon ko roast ki jagah ek "Aura Certificate" milta hai.'],
  ['q' => 'Card kaise share karun?', 'a' => 'Roast ke baad Share button dabao (WhatsApp, Instagram, jahan chaho) ya PNG download karke status laga do.'],
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
      <div class="rst" id="rst" data-me="{{ route('assistant.me') }}" data-lead="{{ route('assistant.lead') }}"
        data-verify="{{ route('assistant.verify') }}" data-roast="{{ route('roast.run') }}" data-url="{{ route('roast') }}"
        data-site="{{ parse_url(config('app.url') ?: url('/'), PHP_URL_HOST) }}">

        <header class="rst-hero">
          <div class="rst-tape" aria-hidden="true"></div>
          <div class="rst-hero-in">
            <span class="rst-kick">E-CHALLAN · ROAST BRANCH</span>
            <h1>Aapki gaadi ka <em>challan</em> kaatein?</h1>
            <p>Gaadi ka naam daalo. AI Hinglish mein halka roast likhega, aur result ban jaayega share karne layak card. Dil pe mat lena — gaadi pyaari hai, bas aadatein mazedaar hain 😄</p>
          </div>
          <div class="rst-hero-stamp" aria-hidden="true"><span>DIL PE<br>MAT LENA</span></div>
        </header>

        {{-- 1) gate: shown only if this visitor has not been verified yet (same one-time check as the AI assistant) --}}
        <section class="rst-view show" id="vWait" aria-live="polite"><div class="rst-wait"><i class="rst-spin"></i><span>Aapka challan-book khul raha hai…</span></div></section>

        <form class="rst-view rst-panel" id="vLead" novalidate autocomplete="on">
          <h2>Roast jaari rakhne ke liye details do</h2>
          <p class="rst-sub">Free roasts khatam ho gaye. Ek baar details do aur email pe code verify karo — phir roast jaari rahega (din ke limit ke saath). Dobara form nahi poochhenge.</p>
          <div class="rst-row">
            <label class="rst-f"><span>Poora naam</span><input name="name" autocomplete="name" maxlength="60" placeholder="Rahul Sharma" required></label>
            <label class="rst-f"><span>Mobile</span><div class="rst-ph"><b>+91</b><input name="phone" inputmode="numeric" autocomplete="tel-national" maxlength="10" placeholder="10-digit number" required></div></label>
          </div>
          <div class="rst-row">
            <label class="rst-f"><span>Email <em>(verification code aayega)</em></span><input name="email" type="email" autocomplete="email" maxlength="120" placeholder="you@example.com" required></label>
            <label class="rst-f"><span>City <em>(optional)</em></span><input name="city" list="rstCities" autocomplete="address-level2" maxlength="80" placeholder="Aapka sheher"><datalist id="rstCities">@foreach (\App\Support\Filters::CITIES as $c)<option value="{{ is_array($c) ? ($c['name'] ?? '') : $c }}">@endforeach</datalist></label>
          </div>
          <input class="rst-hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
          <div class="rst-err" id="eLead" role="alert"></div>
          <button class="rst-btn" type="submit"><span>Code bhejo</span> <i>→</i></button>
          <p class="rst-fine">🔒 Details sirf aapki madad ke liye hain, kisi ko bechi nahi jaatin.</p>
        </form>

        <form class="rst-view rst-panel" id="vOtp" novalidate>
          <h2>Email check karo 📬</h2>
          <p class="rst-sub"><b id="otpMail"></b> pe 6 ank ka code gaya hai.</p>
          <div class="rst-otp" id="otpBoxes">@for ($i = 0; $i < 6; $i++)<input inputmode="numeric" maxlength="1" autocomplete="{{ $i ? 'off' : 'one-time-code' }}" aria-label="Digit {{ $i + 1 }}">@endfor</div>
          <div class="rst-err" id="eOtp" role="alert"></div>
          <button class="rst-btn" type="submit"><span>Verify karo &amp; roast shuru</span> <i>→</i></button>
          <p class="rst-fine"><button type="button" class="rst-lnk" id="otpResend" disabled>Code dobara bhejo</button> · <button type="button" class="rst-lnk" id="otpBack">Details badlo</button></p>
        </form>

        {{-- 2) the tool --}}
        <form class="rst-view rst-panel rst-tool" id="vTool" novalidate autocomplete="off">
          <div class="rst-hi" id="rHi"></div>
          <div class="rst-q"><span class="rst-n">1</span>Gaadi kaun si hai?</div>
          <label class="rst-sl" for="rPlate">Number plate <em>(optional — card pe chhapega)</em></label>
          <div class="rst-plate"><i aria-hidden="true"><b>IND</b></i><input id="rPlate" maxlength="13" placeholder="MH 12 AB 1234" aria-label="Number plate" autocapitalize="characters" autocomplete="off" spellcheck="false"></div>
          <label class="rst-sl" for="rCar">Gaadi ka naam</label>
          <input class="rst-car" id="rCar" list="rstCars" maxlength="60" placeholder="Jaise: Swift, Innova, Thar" aria-label="Gaadi ka naam" autocomplete="off">
          <datalist id="rstCars">@foreach ($cars as $c)<option value="{{ $c }}">@endforeach</datalist>
          <div class="rst-hint">List se chuno ya khud likho — kuch bhi chalega. Number plate ho to roast aur mazedaar banega.</div>

          <div class="rst-q"><span class="rst-n">2</span>Kab se saath hai?</div>
          <div class="rst-chips" id="rOwned" role="radiogroup">@foreach (\App\Services\Roast::OWNED as $k => $l)<button type="button" role="radio" data-v="{{ $k }}" class="{{ $k === 'y1' ? 'on' : '' }}">{{ $l }}</button>@endforeach</div>

          <div class="rst-q"><span class="rst-n">3</span>Aapki aadatein <small>(max 3 chuno)</small></div>
          <div class="rst-chips multi" id="rHabits">@foreach (\App\Services\Roast::HABITS as $k => $l)<button type="button" data-v="{{ $k }}">{{ $l }}</button>@endforeach</div>

          <div class="rst-q"><span class="rst-n">4</span>Roast kitna tez?</div>
          <div class="rst-seg" id="rLevel" role="radiogroup">@foreach (\App\Services\Roast::LEVELS as $k => $l)<button type="button" role="radio" data-v="{{ $k }}" class="{{ $k === 'medium' ? 'on' : '' }}">{{ $l }}</button>@endforeach</div>

          <label class="rst-f rst-nm"><span>Card pe naam <em>(optional)</em></span><input id="rName" maxlength="24" placeholder="Jaise: Rahul" autocomplete="given-name"></label>
          <div class="rst-err" id="eTool" role="alert"></div>
          <button class="rst-btn big" type="submit" id="rGo"><span>Challan kaato</span> <i>🔥</i></button>
          <p class="rst-fine">AI ka mazaak hai. Kisi brand, insaan ya samaj ko neecha dikhana maqsad nahi.</p>
        </form>

        {{-- loading --}}
        <section class="rst-view rst-panel rst-load" id="vLoad" aria-live="polite">
          <div class="rst-printer" aria-hidden="true"><i></i><i></i><i></i></div>
          <h2 id="rLoadTxt">Challan book bhar rahe hain…</h2>
        </section>

        {{-- 3) result --}}
        <section class="rst-view rst-res" id="vRes">
          <div class="rst-cardwrap"><canvas id="rCard" width="1080" height="1350" role="img" aria-label="Aapka roast card"></canvas></div>
          <div class="rst-acts">
            <button class="rst-btn" type="button" id="rShare"><span>Share karo</span> <i>↗</i></button>
            <button class="rst-btn alt" type="button" id="rWa"><span>WhatsApp</span></button>
            <button class="rst-btn alt" type="button" id="rDl"><span>PNG download</span></button>
          </div>
          <div class="rst-note" id="rNote" role="status"></div>
          <div class="rst-more">
            <button type="button" class="rst-lnk" id="rAgain">🔁 Isi gaadi ka naya roast</button> · <button type="button" class="rst-lnk" id="rOther">🚗 Doosri gaadi</button>
          </div>
          <p class="rst-fine" id="rLeft"></p>
        </section>
      </div>

      <div class="prose">
        <h2>Roast my car kaise kaam karta hai</h2>
        <p>Gaadi ka naam, kitne saal se hai aur aapki 2-3 aadatein batao. AI un par ek halka Hinglish roast likhta hai — EMI, parking, AC, dhulai, nimbu-mirchi jaisi baatein. Result ek e-challan jaisa card hota hai jo aap WhatsApp status, Instagram story ya family group mein daal sakte ho.</p>
        <p>Rules saaf hain: roast sirf <b>owner ki funny aadaton</b> par hota hai. Kisi brand ko neecha nahi dikhaya jaata, koi gaali ya hateful baat nahi, aur Thar, Scorpio jaisi "mass entry" gaadiyon ko roast ki jagah <b>Aura Certificate</b> milta hai. Gaadi ka asli kharcha jaanna ho to <a href="{{ route('costperkm') }}">cost per km calculator</a> try karo.</p>
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
  const root = document.getElementById('rst'), $ = (s, r = root) => r.querySelector(s), $$ = (s, r = root) => [...r.querySelectorAll(s)];
  const csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
  const store = { get: k => { try { return localStorage.getItem(k) } catch (e) { return null } }, set: (k, v) => { try { localStorage.setItem(k, v) } catch (e) {} } };
  let token = store.get('aw_token') || '', visitor = '', card = null, last = null, busy = false, queuedRun = false;
  const views = { wait: $('#vWait'), lead: $('#vLead'), otp: $('#vOtp'), tool: $('#vTool'), load: $('#vLoad'), res: $('#vRes') };
  const show = v => { Object.entries(views).forEach(([k, n]) => n.classList.toggle('show', k === v)); };
  const api = async (url, body) => {
    const r = await fetch(url, { method: body ? 'POST' : 'GET', credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(token ? { 'X-Assistant-Token': token } : {}) } });
    let data = {}; try { data = await r.json(); } catch (e) {}
    // Never show a raw server error: only messages we wrote ourselves (they carry a `reason`) or form-validation text.
    if (!r.ok && !data.errors && ((r.status >= 500 && !data.reason) || r.status === 404 || r.status === 405 || r.status === 419 || (r.status === 429 && !data.reason))) {
      data = { message: r.status === 419 ? 'Session expire ho gaya. Page refresh karke dobara try karo.' : r.status === 429 ? 'Bahut zyada requests. Thoda ruk kar dobara try karo.' : 'Hamari taraf se kuch gadbad ho gayi. Thodi der baad dobara try karo.' };
    }
    return { ok: r.ok, status: r.status, data };
  };
  const firstError = d => (d.errors && Object.values(d.errors)[0] && Object.values(d.errors)[0][0]) || d.message || 'Kuch gadbad ho gayi, dobara try karo.';

  /* ---------- who is this visitor? Verified visitors never see the form again. ---------- */
  function enter(d) {
    if (d && d.token) { token = d.token; store.set('aw_token', token); }
    visitor = ((d && d.name) || '').split(' ')[0];
    $('#rHi').textContent = visitor ? 'Chalo ' + visitor + ', gaadi ka challan kaatte hain 🚓' : 'Chalo, gaadi ka challan kaatte hain 🚓';
    if (visitor && !$('#rName').value) $('#rName').value = visitor.slice(0, 24);
    const c = store.get('rst_car'); if (c && !$('#rCar').value) $('#rCar').value = c;
    const pl = store.get('rst_plate'); if (pl && !$('#rPlate').value) $('#rPlate').value = pl;
    show('tool');
    if (queuedRun) { queuedRun = false; setTimeout(() => run(), 300); }   // the roast that triggered the details form
  }
  (async () => {
    const r = await api(root.dataset.me).catch(() => null);
    if (r && r.ok && r.data.gate) show('lead');   // free roasts already used
    else enter((r && r.ok && r.data) || {});                                                                              // verified, or still within the free roasts
  })();

  /* ---------- lead form + email OTP (same endpoints as the assistant) ---------- */
  const fLead = views.lead, eLead = $('#eLead');
  fLead.addEventListener('submit', async e => {
    e.preventDefault(); eLead.textContent = '';
    const v = Object.fromEntries(new FormData(fLead).entries());
    v.phone = (v.phone || '').replace(/\D/g, '').replace(/^91(?=\d{10}$)/, '');
    $$('.rst-f', fLead).forEach(x => x.classList.remove('bad'));
    const bad = (n, m) => { const i = fLead.elements[n]; i.closest('.rst-f').classList.add('bad'); i.focus(); eLead.textContent = m; };
    if (!/^[\p{L}\p{M}\s.'\-]{2,60}$/u.test((v.name || '').trim())) return bad('name', 'Apna sahi naam likho.');
    if (!/^[6-9]\d{9}$/.test(v.phone)) return bad('phone', '10 ank ka sahi mobile number daalo.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test((v.email || '').trim())) return bad('email', 'Sahi email daalo.');
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

  /* ---------- the tool ---------- */
  const val = id => { const g = $(id + ' .on'); return g ? g.dataset.v : ''; };
  $('#rOwned').addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; $$('#rOwned button').forEach(x => x.classList.toggle('on', x === b)); });
  $('#rLevel').addEventListener('click', e => { const b = e.target.closest('button'); if (!b) return; $$('#rLevel button').forEach(x => x.classList.toggle('on', x === b)); });
  $('#rHabits').addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (!b.classList.contains('on') && $$('#rHabits .on').length >= 3) { b.classList.add('shake'); setTimeout(() => b.classList.remove('shake'), 400); return; }
    b.classList.toggle('on');
  });
  $('#rPlate').addEventListener('input', e => { const i = e.target, p = i.selectionStart; i.value = i.value.toUpperCase().replace(/[^A-Z0-9 \-]/g, ''); try { i.setSelectionRange(p, p); } catch (x) {} });
  const msgs = ['Challan book bhar rahe hain…', 'Traffic uncle se baat ho rahi hai…', 'Parking ka CCTV dekh rahe hain…', 'EMI ki date check ho rahi hai…', 'Stamp pe syahi lag rahi hai…'];
  let mi = 0, mt = null;
  views.tool.addEventListener('submit', async e => { e.preventDefault(); await run(); });
  $('#rAgain').addEventListener('click', () => run());
  $('#rOther').addEventListener('click', () => { $('#rCar').value = ''; $('#rPlate').value = ''; show('tool'); $('#rCar').focus(); });

  async function run() {
    if (busy) return;
    const car = $('#rCar').value.trim(), eTool = $('#eTool'); eTool.textContent = '';
    if (car.length < 2) { show('tool'); eTool.textContent = 'Pehle gaadi ka naam batao.'; $('#rCar').focus(); return; }
    busy = true; store.set('rst_car', car); store.set('rst_plate', $('#rPlate').value.trim()); show('load'); mi = 0; $('#rLoadTxt').textContent = msgs[0];
    mt = setInterval(() => { mi = (mi + 1) % msgs.length; $('#rLoadTxt').textContent = msgs[mi]; }, 1100);
    const t0 = Date.now();
    const r = await api(root.dataset.roast, { car, plate: $('#rPlate').value.trim(), owned: val('#rOwned') || 'y1', level: val('#rLevel') || 'medium', habits: $$('#rHabits .on').map(b => b.dataset.v), name: $('#rName').value.trim() }).catch(() => ({ ok: false, status: 0, data: { message: 'Connection problem. Dobara try karo.' } }));
    await new Promise(res => setTimeout(res, Math.max(0, 1800 - (Date.now() - t0))));   // a short beat so the "printing" feels real
    clearInterval(mt); busy = false;
    if (r.status === 401 && r.data.gate) { queuedRun = true; show('lead'); return; }   // free roasts used: take the details once, then roast again
    if (!r.ok) { show('tool'); eTool.textContent = firstError(r.data); return; }
    if (r.data.token && !token) { token = r.data.token; store.set('aw_token', token); }
    card = r.data.card; await draw(card);
    $('#rLeft').textContent = typeof r.data.left === 'number' ? 'Aaj ke ' + r.data.left + ' roast aur baaki.' : '';
    $('#rNote').textContent = ''; show('res');
    const w = $('.rst-cardwrap'); w.classList.remove('pop'); void w.offsetWidth; w.classList.add('pop');
    w.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
  }

  /* ---------- share card (canvas, 1080 x 1350) ---------- */
  const cv = $('#rCard'), cx = cv.getContext('2d'), W = 1080, H = 1350;
  const FONT = '"Archivo","Segoe UI",Roboto,Arial,sans-serif';
  const f = (w, s) => w + ' ' + s + 'px ' + FONT;
  function rr(x, y, w, h, r) { cx.beginPath(); cx.roundRect ? cx.roundRect(x, y, w, h, r) : cx.rect(x, y, w, h); }
  function wrap(t, maxW) { const out = []; let line = ''; (t || '').split(' ').forEach(wd => { const tr = line ? line + ' ' + wd : wd; if (cx.measureText(tr).width > maxW && line) { out.push(line); line = wd; } else line = tr; }); if (line) out.push(line); return out; }
  function seed(s) { let h = 7; for (const c of s) h = (h * 31 + c.charCodeAt(0)) % 100000; return h; }
  async function draw(c) {
    try { await Promise.all([document.fonts.load(f(800, 40)), document.fonts.load(f(600, 30))]); } catch (e) {}
    const aura = c.mode === 'aura';
    const P = aura ? { bg: '#0d0f14', bg2: '#161a22', ink: '#f4ead0', mute: '#b8a77a', ac: '#e0b24a', ac2: '#8a6a1c', box: '#1b2029', line: '#e0b24a', stamp: '#e0b24a', plate: '#111', plateInk: '#e0b24a', head: '#000', headInk: '#e0b24a' }
                  : { bg: '#f7f0da', bg2: '#efe5c3', ink: '#1b1b1b', mute: '#6b6249', ac: '#c1121f', ac2: '#7d0b14', box: '#fffaf0', line: '#1b1b1b', stamp: '#c1121f', plate: '#fff', plateInk: '#111', head: '#c1121f', headInk: '#fff' };
    cx.clearRect(0, 0, W, H);
    const g = cx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, P.bg); g.addColorStop(1, P.bg2); cx.fillStyle = g; cx.fillRect(0, 0, W, H);
    cx.globalAlpha = .06; cx.fillStyle = P.ink; for (let y = 200; y < H; y += 36) cx.fillRect(40, y, W - 80, 1); cx.globalAlpha = 1;   // ruled paper lines
    // header band + caution tape
    cx.fillStyle = P.head; cx.fillRect(0, 0, W, 170);
    cx.fillStyle = aura ? '#e0b24a' : '#fff'; for (let x = -40; x < W + 40; x += 60) { cx.beginPath(); cx.moveTo(x, 150); cx.lineTo(x + 30, 150); cx.lineTo(x + 50, 170); cx.lineTo(x + 20, 170); cx.fill(); }
    cx.fillStyle = P.headInk; cx.textBaseline = 'alphabetic'; cx.textAlign = 'left';
    cx.font = f(800, 26); cx.fillText(aura ? 'AUTOINDIA · MASS ENTRY DESK' : 'AUTOINDIA · TRAFFIC ROAST BRANCH', 54, 62);
    cx.font = f(800, 62); cx.fillText(aura ? 'AURA CERTIFICATE' : 'E-CHALLAN', 54, 124);
    const no = (aura ? 'AC-' : 'RC-') + String(seed((c.plate || c.car) + c.title)).padStart(5, '0'), dt = new Date().toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).toUpperCase();
    cx.textAlign = 'right'; cx.font = f(700, 26); cx.fillText(no, W - 54, 62); cx.fillText(dt, W - 54, 100);
    // number plate with the car's name
    const px = 54, py = 200, pw = W - 108, ph = 124;
    rr(px, py, pw, ph, 20); cx.fillStyle = P.plate; cx.fill(); cx.lineWidth = 6; cx.strokeStyle = aura ? P.plateInk : '#111'; cx.stroke();
    cx.save(); rr(px, py, 96, ph, 20); cx.clip(); cx.fillStyle = '#0a3d91'; cx.fillRect(px, py, 96, ph); cx.restore();
    cx.fillStyle = '#fff'; cx.textAlign = 'center'; cx.font = f(800, 30); cx.fillText('IND', px + 48, py + 84); cx.fillStyle = '#f2a900'; cx.beginPath(); cx.arc(px + 48, py + 42, 11, 0, 7); cx.fill();
    const name = (c.plate || c.car || '').toUpperCase(); let fs = 78; cx.font = f(800, fs); while (cx.measureText(name).width > pw - 150 && fs > 30) { fs -= 2; cx.font = f(800, fs); }
    cx.fillStyle = P.plateInk; cx.fillText(name, px + 96 + (pw - 96) / 2, py + ph / 2 + fs * .36);
    // car name under the plate, then the title
    cx.textAlign = 'left'; cx.fillStyle = P.mute; cx.font = f(700, 28); cx.fillText(c.plate ? (c.car || '').toUpperCase() : 'GAADI REGISTERED IN INDIA', 54, 362);
    cx.fillStyle = P.ac; cx.font = f(800, 58); cx.fillText(c.title, 54, 424);
    cx.fillStyle = P.mute; cx.font = f(700, 26); cx.fillText(aura ? 'ROAD PRESENCE REPORT' : 'OFFENCES NOTED' + (c.name ? ' · ' + c.name.toUpperCase() : ''), 54, 458);
    // offences
    let y = 486; cx.font = f(600, 36);
    c.lines.forEach((ln, i) => {
      const ls = wrap(ln, W - 108 - 120), h = ls.length * 46 + 40;
      rr(54, y, W - 108, h, 16); cx.fillStyle = P.box; cx.fill(); cx.lineWidth = 3; cx.strokeStyle = P.line; cx.stroke();
      cx.fillStyle = P.ac; cx.beginPath(); cx.arc(54 + 46, y + h / 2, 28, 0, 7); cx.fill();
      cx.fillStyle = aura ? '#000' : '#fff'; cx.textAlign = 'center'; cx.font = f(800, 32); cx.fillText(String(i + 1), 54 + 46, y + h / 2 + 11);
      cx.textAlign = 'left'; cx.fillStyle = P.ink; cx.font = f(600, 36); ls.forEach((l, j) => cx.fillText(l, 54 + 96, y + 52 + j * 46));
      y += h + 18;
    });
    // fine + meter
    y = Math.max(y + 10, 1010); cx.fillStyle = P.mute; cx.font = f(700, 24); cx.fillText(aura ? 'SALAMI / JURMANA' : 'JURMANA', 54, y);
    cx.fillStyle = P.ac; cx.font = f(800, 50); let fine = c.fine, fsz = 50; while (cx.measureText(fine).width > 470 && fsz > 28) { fsz -= 2; cx.font = f(800, fsz); } cx.fillText(fine, 54, y + 62);
    const mx = 560, mw = W - 54 - mx; cx.fillStyle = P.mute; cx.font = f(700, 24); cx.fillText(c.score.label.toUpperCase(), mx, y);
    rr(mx, y + 18, mw, 36, 18); cx.fillStyle = aura ? '#262c38' : '#e5dbb8'; cx.fill();
    rr(mx, y + 18, Math.max(36, mw * c.score.value / 100), 36, 18); cx.fillStyle = P.ac; cx.fill();
    cx.fillStyle = P.ink; cx.font = f(800, 40); cx.fillText(c.score.value + '%', mx, y + 100);
    // stamp
    cx.save(); cx.translate(W - 250, y + 150); cx.rotate(-.2); cx.lineWidth = 8; cx.strokeStyle = P.stamp; cx.fillStyle = P.stamp; cx.globalAlpha = .92;
    let sf = 52; cx.font = f(800, sf); while (cx.measureText(c.verdict).width > 360 && sf > 24) { sf -= 2; cx.font = f(800, sf); }
    const sw = cx.measureText(c.verdict).width + 60; rr(-sw / 2, -50, sw, 100, 14); cx.stroke(); rr(-sw / 2 + 9, -41, sw - 18, 82, 8); cx.lineWidth = 3; cx.stroke();
    cx.textAlign = 'center'; cx.fillText(c.verdict, 0, 18); cx.restore(); cx.globalAlpha = 1;
    // footer
    cx.textAlign = 'left'; cx.fillStyle = P.mute; cx.font = f(700, 26); cx.fillText((c.tag || '#RoastMyCar') + '  ·  AI ka mazaak hai, dil pe mat lena', 54, H - 70);
    cx.fillStyle = P.ink; cx.font = f(800, 32); cx.fillText(root.dataset.site + '/roast-my-car', 54, H - 28);
    cv.setAttribute('aria-label', (aura ? 'Aura certificate: ' : 'Roast challan: ') + (c.plate ? c.plate + ', ' : '') + c.car + '. ' + c.lines.join(' '));
  }

  /* ---------- share / download ---------- */
  const note = t => { $('#rNote').textContent = t; };
  const blob = () => new Promise(res => cv.toBlob(res, 'image/png'));
  const text = () => card ? (card.mode === 'aura' ? '😎 ' : '🚓 ') + card.car + ' - ' + card.title + '\n"' + card.lines[0] + '"\n\nApni gaadi ka roast karwao: ' + root.dataset.url + ' ' + (card.tag || '') : '';
  const slug = () => 'roast-my-car-' + (card ? card.car.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') : 'card') + '.png';
  async function download() { const b = await blob(); const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = slug(); document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000); note('Card download ho gaya — status ya story pe laga do! 📲'); }
  $('#rDl').addEventListener('click', download);
  $('#rShare').addEventListener('click', async () => {
    try {
      const b = await blob(), file = new File([b], slug(), { type: 'image/png' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) { await navigator.share({ files: [file], text: text(), url: root.dataset.url }); note('Share ho gaya! Doston ko bhi roast karwao 😄'); return; }
      if (navigator.share) { await navigator.share({ text: text(), url: root.dataset.url }); return; }
    } catch (e) { if (e && e.name === 'AbortError') return; }
    await download();
  });
  $('#rWa').addEventListener('click', () => { window.open('https://wa.me/?text=' + encodeURIComponent(text()), '_blank', 'noopener'); note('WhatsApp mein card ke saath screenshot/PNG bhi laga dena 😉'); });
})();
</script>
@endsection
