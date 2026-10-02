@extends('site.layout')
@section('title', 'Car Cost Per KM Calculator — Fuel, EMI, Service & Insurance | ' . \App\Models\Setting::get('site.name'))
@section('description', 'Find out what your car really costs per km. Enter monthly driving, mileage, fuel price, EMI, service and insurance — see cost per km, per day, per month and how it compares with an auto, cab and bike.')

@push('head')
<script
  type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => 'Car Cost Per KM Calculator', 'url' => route('costperkm'), 'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'INR']], JSON_UNESCAPED_SLASHES) !!}</script>
@php($faq = ($seo?->faqItems()) ?: [
  ['q' => 'How is the cost per km of a car calculated?', 'a' => 'Fuel cost for the month = monthly km × fuel price ÷ average (km per litre). Add your EMI, yearly service ÷ 12 and yearly insurance ÷ 12. Divide the total by your monthly km to get the cost per km.'],
  ['q' => 'Why does driving less make each km costlier?', 'a' => 'EMI, insurance and a good part of service are fixed whatever you drive. Spread over fewer km, they raise the cost of every km. Fuel is the only cost that grows with km.'],
  ['q' => 'What mileage should I enter?', 'a' => 'Use the real-world average you get (or expect), not the company claim. City driving is usually 20–30% lower than the claimed figure. For CNG enter km per kg and for electric cars km per kWh.'],
  ['q' => 'Is a car cheaper than an auto or cab?', 'a' => 'For regular daily use with a family, often yes. For occasional trips, a cab or auto can cost less overall because you do not pay EMI, insurance and service. The comparison on this page shows your own numbers.'],
])
@unless ($seo?->faqItems())
  <script
    type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()], JSON_UNESCAPED_SLASHES) !!}</script>
@endunless
@endpush

@section('content')
@include('site.partials.ad-horizontal', ['ads' => $homeSettings->adsFor('horizontal')])

@php($fields = [
  ['km', 'Monthly driving', 'km / month', 100, 6000, 50, 1000],
  ['avg', 'Average (mileage)', null, 3, 40, 0.5, 16],
  ['price', 'Fuel price', null, 1, 250, 0.5, 105],
  ['emi', 'Car EMI', '₹ / month', 0, 100000, 500, 0],
  ['service', 'Service & maintenance', '₹ / year', 0, 60000, 500, 8000],
  ['ins', 'Insurance', '₹ / year', 0, 100000, 500, 25000],
])

<div class="w">
  <div class="article-wrap emi-wrap">
    <div>
      <div class="aside-box ckm" id="ckm" data-cfg="{{ json_encode($cfg) }}" data-cars="{{ json_encode($cars) }}">

        {{-- dashboard cluster --}}
        <div class="ckm-meter">
          <div class="ckm-cluster">
            <div class="ckm-taxi">
              <div class="ckm-lcd" aria-live="polite"><span class="ckm-rs">₹</span><b id="oKm">0</b><small>per
                  km</small></div>
              <div class="ckm-sbar" aria-hidden="true"><span id="sbFuel"></span></div>
              <div class="ckm-split">
                <span><i class="f"></i>Fuel <b>₹<em id="oFuelKm">0</em></b></span>
                <span><i class="r"></i>Baaki kharche <b>₹<em id="oRestKm">0</em></b></span>
              </div>
            </div>
            <div class="ckm-rail" role="img" aria-label="Your cost per km against bike, auto and cab">
              <div class="ckm-scale">
                <div id="ckmPins"></div>
                <div class="ckm-nums"><span style="left:0">0</span><span style="left:25%">10</span><span
                    style="left:50%">20</span><span style="left:75%">30</span><span style="left:100%">40</span></div>
                <div class="ckm-you" id="ckmYou"><i></i><b>Aap</b></div>
              </div>
            </div>
          </div>
          <div class="ckm-side">
            <ul class="ckm-times ckm-slip">
              <li><span>Per day</span><b>₹<i id="oDay">0</i></b></li>
              <li><span>Per month</span><b>₹<i id="oMon">0</i></b></li>
              <li><span>Per year</span><b>₹<i id="oYear">0</i></b></li>
              <li><span>5 years</span><b>₹<i id="oFive">0</i></b></li>
            </ul>
            <div class="ckm-msg" id="ckmLine" data-state="warn">
              <div>
                <p id="ckmMain">Numbers daalte hi yahan verdict aayega…</p>
                <p class="ckm-tip" id="ckmTip"></p>
              </div>
            </div>
          </div>
        </div>

        <div class="ckm-grid">
          {{-- inputs --}}
          <div class="ckm-in">
            <div class="emi-f"><label for="carPick">Gaadi (optional)</label>
              <select id="carPick" class="ckm-sel">
                <option value="">Other / used car — enter details below</option>@foreach ($cars as $c)
                <option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach
              </select>
            </div>
            <div class="emi-f"><label>Fuel</label>
              <div class="ckm-seg" id="fuelSeg">@foreach ($cfg['fuels'] as $k => $f)<button type="button"
              data-fuel="{{ $k }}" class="{{ $k === 'petrol' ? 'on' : '' }}">{{ $f['label'] }}</button>@endforeach
              </div>
            </div>
            @foreach ($fields as [$id, $label, $unit, $min, $max, $step, $val])
              <div class="emi-f"><label for="n_{{ $id }}">{{ $label }}: <b><span id="v_{{ $id }}"></span></b> <small
                    class="ckm-unit" id="u_{{ $id }}">{{ $unit }}</small></label>
                <input type="range" id="r_{{ $id }}" min="{{ $min }}" max="{{ $max }}" step="{{ $step }}"
                  value="{{ $val }}" aria-label="{{ $label }}">
                <input type="number" id="n_{{ $id }}" min="{{ $min }}" max="{{ $max * 10 }}" step="{{ $step }}"
                  value="{{ $val }}" inputmode="decimal">
              </div>
            @endforeach
            <p class="ckm-note" id="carNote" hidden></p>
          </div>

          {{-- breakdown + race --}}
          <div class="ckm-out">
            <h3 class="ckm-h">Paisa kahan ja raha hai</h3>
            <div class="ckm-bar" id="ckmBar" role="img" aria-label="Cost split"></div>
            <ul class="ckm-leg" id="ckmLeg"></ul>
            <h3 class="ckm-h" style="margin-top:26px">Kaun sasta? <small>per km</small></h3>
            <div class="ckm-race" id="ckmRace"></div>
          </div>
        </div>
      </div>

      <div class="prose">
        <h2>How to use the cost per km calculator</h2>
        <p>Pick your car (or enter its average yourself), then set how much you drive in a month. Fuel cost is worked
          out for you from your km and mileage — you only add your EMI, yearly service and yearly insurance. Everything
          updates live, so try driving more or less and watch what happens to the cost of every km.</p>
        <p>Prefer to plan the loan first? Use our <a href="{{ route('emi') }}">car loan EMI calculator</a>.</p>
      </div>
      <h2 style="margin-top:28px">Frequently asked questions</h2>
      @foreach ($faq as $f)
        <details class="aside-box" style="padding:14px 18px;margin-bottom:10px">
          <summary style="font-weight:700;cursor:pointer">{{ $f['q'] }}</summary>
          <p class="m" style="margin:10px 0 0">{{ $f['a'] }}</p>
      </details>@endforeach
    </div>

    <aside>
      <div class="aside-box emi-lead">
        @include('site.partials.lead-form', ['emiContext' => true, 'calcLabel' => 'Cost per km'])</div>
      @include('site.partials.ad-vertical', ['page' => 'emi'])
      @include('site.partials.latest-news', ['articles' => $latest])
    </aside>
  </div>
</div>

<script>
  (() => {
    const root = document.getElementById('ckm'), $ = id => document.getElementById(id);
    const cfg = JSON.parse(root.dataset.cfg), cars = JSON.parse(root.dataset.cars), money = n => Math.round(n).toLocaleString('en-IN');
    const one = n => (Math.round(n * 10) / 10).toFixed(1).replace(/\.0$/, '');
    const ids = ['km', 'avg', 'price', 'emi', 'service', 'ins'];
    const r = Object.fromEntries(ids.map(i => [i, $('r_' + i)])), n = Object.fromEntries(ids.map(i => [i, $('n_' + i)]));
    let fuel = 'petrol';
    const COLORS = { fuel: '#fb9e1e', emi: '#24a69a', service: '#6366f1', ins: '#94a3b8' };

    const val = i => { const x = parseFloat(n[i].value); return isNaN(x) ? parseFloat(r[i].value) : x; };
    ids.forEach(i => {
      r[i].addEventListener('input', () => { n[i].value = r[i].value; calc(); });
      n[i].addEventListener('input', () => { if (n[i].value !== '') { r[i].value = n[i].value; calc(); } });
    });

    function setFuel(k, keepAvg) {
      fuel = k; const f = cfg.fuels[k];
      document.querySelectorAll('#fuelSeg button').forEach(b => b.classList.toggle('on', b.dataset.fuel === k));
      $('u_avg').textContent = f.avg_unit; $('u_price').textContent = f.price_unit;
      r.avg.min = f.min; r.avg.max = f.max; r.price.max = Math.ceil(f.price * 2.5);
      n.price.value = r.price.value = f.price;
      if (!keepAvg) n.avg.value = r.avg.value = f.average;
      calc();
    }
    document.querySelectorAll('#fuelSeg button').forEach(b => b.addEventListener('click', () => { $('carPick').value = ''; $('carNote').hidden = true; setFuel(b.dataset.fuel, false); }));

    $('carPick').addEventListener('change', e => {
      const c = cars.find(x => String(x.id) === e.target.value), note = $('carNote');
      if (!c) { note.hidden = true; return; }
      setFuel(c.fuel, true);
      if (c.average) n.avg.value = r.avg.value = Math.min(Math.max(c.average, +r.avg.min), +r.avg.max);
      else n.avg.value = r.avg.value = cfg.fuels[c.fuel].average;
      if (c.emi) n.emi.value = r.emi.value = c.emi;
      note.hidden = false;
      note.textContent = (c.average ? 'Average ' + c.average + ' ' + cfg.fuels[c.fuel].avg_unit + ' from our specs. ' : 'We do not have a mileage for this car, so a typical figure is used. ') + (c.emi ? 'EMI is a rough estimate (90% loan, 5 years) — ' : '') + 'edit anything to match your case.';
      calc();
    });

    // Verdict written by code from the numbers on screen (no AI, no network): where you stand vs the Auto, plus one useful insight.
    function verdict(perKm, fuelKm, fixedM, km) {
      const a = cfg.bench.auto, d = Math.round(perKm - a.rate), state = d >= 2 ? 'bad' : (d <= -1 ? 'good' : 'warn');
      const main = d >= 1 ? a.label + ' se ye ₹' + d + ' per km zyada padta hai.'
        : d <= -1 ? a.label + ' se ye ₹' + Math.abs(d) + ' per km sasta hai — paisa vasool!'
          : 'Lagbhag ' + a.label + ' jitna hi: ₹' + one(perKm) + ' per km.';
      const share = Math.round(fixedM / (fixedM + fuelKm * km) * 100);
      let tip = '';
      if (perKm > a.rate && a.rate > fuelKm) {
        const be = Math.ceil(fixedM / (a.rate - fuelKm) / 50) * 50;
        if (be <= 20000) tip = 'Mahine me lagbhag ' + money(be) + ' km chalane par ye ' + a.label + ' ke barabar (₹' + one(a.rate) + '/km) ho jayegi.';
      }
      if (!tip && share >= 55) tip = 'Kharche ka ' + share + '% fix hai (EMI, service, insurance) — jitna zyada chalayenge, utna per km sasta.';
      if (!tip) tip = 'Fuel sirf ₹' + one(fuelKm) + ' per km hai; baaki fix kharcha mahine me ₹' + money(fixedM) + '.';
      return { state, main, tip };
    }

    function calc() {
      const f = cfg.fuels[fuel];
      const clampV = (i, lo, hi, d) => Math.min(Math.max(val(i) || d, lo), hi);
      const km = clampV('km', 50, 20000, 1000), avg = clampV('avg', 1, 100, f.average), price = clampV('price', 1, 500, f.price);
      const emi = clampV('emi', 0, 1e6, 0), service = clampV('service', 0, 1e6, 0), ins = clampV('ins', 0, 2e6, 0);
      const fuelM = km * price / avg, svcM = service / 12, insM = ins / 12, fixed = emi + svcM + insM, total = fuelM + fixed, perKm = total / km;

      ids.forEach(i => { $('v_' + i).textContent = i === 'avg' || i === 'price' ? one(val(i)) : money(val(i)); });
      $('sbFuel').style.width = (fuelM / total * 100).toFixed(1) + '%'; $('oKm').textContent = one(perKm); $('oFuelKm').textContent = one(fuelM / km); $('oRestKm').textContent = one(fixed / km);
      $('oDay').textContent = money(total / 30); $('oMon').textContent = money(total); $('oYear').textContent = money(total * 12); $('oFive').textContent = money(total * 60);
      const frac = Math.min(perKm / 40, 1);
      $('ckmYou').style.left = (frac * 100).toFixed(1) + '%';

      const parts = [['Fuel', fuelM, COLORS.fuel], ['EMI', emi, COLORS.emi], ['Service', svcM, COLORS.service], ['Insurance', insM, COLORS.ins]].filter(p => p[1] > 0.5);
      $('ckmBar').innerHTML = parts.map(p => `<span style="width:${(p[1] / total * 100).toFixed(2)}%;background:${p[2]}" title="${p[0]}"></span>`).join('');
      $('ckmLeg').innerHTML = parts.map(p => `<li><i style="background:${p[2]}"></i><span>${p[0]}</span><b>₹${money(p[1])}<small>/month</small></b><em>₹${one(p[1] / km)}/km</em></li>`).join('');

      const rows = [['Your car', perKm, true]].concat(Object.values(cfg.bench).map(b => [b.label, b.rate, false])), max = Math.max(...rows.map(x => x[1]), 1);
      $('ckmRace').innerHTML = rows.map(x => `<div class="${x[2] ? 'me' : ''}"><span>${x[0]}</span><i><u style="width:${(x[1] / max * 100).toFixed(1)}%"></u></i><b>₹${one(x[1])}</b></div>`).join('');

      const h = $('emiContext'); if (h) h.value = `${f.label}, ${money(km)} km/month, avg ${one(avg)} ${f.avg_unit}, fuel ₹${one(price)}, EMI ₹${money(emi)}, service ₹${money(service)}/yr, insurance ₹${money(ins)}/yr = ₹${one(perKm)}/km, ₹${money(total)}/month`;

      const v = verdict(perKm, fuelM / km, fixed, km);
      $('ckmLine').dataset.state = v.state; $('ckmMain').textContent = v.main; $('ckmTip').textContent = v.tip;
    }

    // Pins on the scale where a bike, an auto and a cab sit; every second label is raised so close rates do not overlap.
    $('ckmPins').innerHTML = Object.values(cfg.bench).map((b, i) =>
      `<div class="ckm-pin" style="left:${(Math.min(b.rate / 40, 1) * 100).toFixed(1)}%;--s:${i % 2 ? 36 : 10}px"><span>${b.label}<em>₹${one(b.rate)}</em></span><i></i></div>`).join('');

    setFuel('petrol', false);
  })();
</script>
@endsection