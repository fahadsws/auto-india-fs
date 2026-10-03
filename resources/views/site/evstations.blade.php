@extends('site.layout')
@section('title', 'EV Charging Stations Near You — India Charging Station Finder | ' . \App\Models\Setting::get('site.name'))
@section('description', 'Find electric car and bike charging stations near you or in any Indian city. See the map, distance, connector types and get directions. Free, no sign-up.')

@push('head')
@php($faq = ($seo?->faqItems()) ?: [
  ['q' => 'How do I find an EV charging station near me?', 'a' => 'Tap "Use my location" or type your city or area and press Search. The map and list show charging stations around you, nearest first, with connector types and a directions link.'],
  ['q' => 'Where does the charging station data come from?', 'a' => 'From OpenStreetMap, the open community map. Operators and contributors keep it updated, so a few stations may be missing or out of date. Confirm availability with the operator app before you travel.'],
  ['q' => 'What do Type 2, CCS2 and CHAdeMO mean?', 'a' => 'They are connector types. Type 2 is the common AC connector for slower charging. CCS2 is the DC fast-charging connector used by most new Indian electric cars. CHAdeMO is an older DC fast-charging standard. Check which one your car supports.'],
  ['q' => 'Is it free to use?', 'a' => 'Yes. Finding stations is free. Charging itself is paid or free depending on the station; the list shows this when the data is available.'],
])
@unless ($seo?->faqItems())
  <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => collect($faq)->map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]])->values()->all()], JSON_UNESCAPED_SLASHES) !!}</script>
@endunless
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
  .evf{display:grid;gap:16px}
  .evf-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:stretch}
  .evf-bar input{flex:1 1 220px;min-width:0;height:48px;border:1.5px solid var(--line);border-radius:10px;padding:0 14px;font-size:16px;background:#fff;color:#14161c}
  .evf-bar select{height:48px;border:1.5px solid var(--line);border-radius:10px;padding:0 10px;background:#fff;color:#14161c}
  .evf-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:48px;padding:0 18px;border-radius:10px;border:0;background:var(--red,#d90000);color:#fff;font:700 15px 'Archivo',sans-serif;cursor:pointer;text-decoration:none}
  .evf-btn.alt{background:transparent;color:var(--ink);border:1.5px solid var(--line)}
  .evf-btn:disabled{opacity:.6;cursor:wait}
  .evf-cities{display:flex;flex-wrap:wrap;gap:8px}
  .evf-cities button{padding:7px 14px;border:1px solid var(--line);border-radius:99px;background:transparent;color:var(--ink);font:600 13.5px 'Archivo',sans-serif;cursor:pointer}
  .evf-cities button:hover{border-color:var(--red,#d90000);color:var(--red,#d90000)}
  .evf-status{min-height:22px;font-size:15px;color:var(--ink2,#5b5f69)}
  .evf-status.err{color:#c0141c}
  .evf-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.15fr);gap:16px;align-items:start}
  #evMap{height:520px;border-radius:14px;border:1px solid var(--line);background:#eef0f3}
  .evf-list{max-height:520px;overflow:auto;display:grid;gap:10px;padding-right:2px}
  .evf-item{border:1px solid var(--line);border-radius:12px;padding:12px 14px;background:#fff;color:#14161c;cursor:pointer}
  html[data-theme=dark] .evf-item{background:var(--wash,#1b1e26);color:inherit}
  .evf-item:hover,.evf-item.on{border-color:var(--red,#d90000)}
  .evf-item b{display:block;font-size:15.5px}
  .evf-item .km{float:right;font-weight:700;color:#0b6b3a}
  .evf-item small{display:block;color:var(--ink2,#5b5f69);margin-top:3px;line-height:1.4}
  .evf-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
  .evf-tags span{padding:3px 9px;border-radius:99px;background:rgba(11,107,58,.1);color:#0b6b3a;font-size:12.5px;font-weight:600}
  .evf-item a{display:inline-block;margin-top:8px;font-weight:700;font-size:13.5px;color:var(--red,#d90000);text-decoration:none}
  .evf-empty{padding:22px;border:1px dashed var(--line);border-radius:12px;color:var(--ink2,#5b5f69);line-height:1.5}
  .evf-note{font-size:13px;color:var(--ink2,#5b5f69)}
  .evf-ops{display:flex;flex-wrap:wrap;gap:8px 16px;margin:6px 0 0;padding:0;list-style:none}
  @media (max-width:900px){.evf-grid{grid-template-columns:1fr}#evMap{height:340px}.evf-list{max-height:none}}
</style>
@endpush

@section('content')
@include('site.partials.crumb', ['title' => 'EV Charging Stations'])
@include('site.partials.ad-horizontal', ['ads' => $homeSettings->adsFor('horizontal')])

<div class="w">
  <div class="article-wrap emi-wrap">
    <div class="evf">
      <section>
        <form class="evf-bar" id="evForm" autocomplete="off" novalidate>
          <input id="evQ" type="search" placeholder="Enter a city or area, e.g. Pune, Andheri, Sector 62 Noida" aria-label="City or area" maxlength="80">
          <select id="evR" aria-label="Search radius"><option value="5">5 km</option><option value="10" selected>10 km</option><option value="20">20 km</option></select>
          <button class="evf-btn" type="submit" id="evGo">Search</button>
          <button class="evf-btn alt" type="button" id="evLoc">📍 Use my location</button>
        </form>
        <div class="evf-cities" id="evCities" style="margin-top:12px">@foreach ($cities as $c)<button type="button" data-city="{{ $c }}">{{ $c }}</button>@endforeach</div>
      </section>

      <div class="evf-status" id="evStatus" role="status" aria-live="polite">Search a city or use your location to see nearby charging stations.</div>

      <div class="evf-grid">
        <div class="evf-list" id="evList"><div class="evf-empty">Charging stations will appear here, nearest first.</div></div>
        <div id="evMap" aria-label="Map of charging stations"></div>
      </div>
      <p class="evf-note" id="evMore"></p>
      <p class="evf-note">Station data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap contributors</a>. It is community-maintained, so please confirm availability and pricing with the operator before you travel.</p>

      <div class="prose">
        <h2>Charging networks and apps</h2>
        <p>Live availability and payment usually happen in the operator's own app. Popular networks in India include Tata Power EZ Charge, Statiq, ChargeZone, Jio-bp pulse and Ather Grid. The government's <a href="https://evyatra.beeindia.gov.in/" target="_blank" rel="noopener noreferrer">EV Yatra</a> portal also lists public charging points.</p>
        <p>Thinking of switching to electric? Compare <a href="{{ route('newcars.index') }}">new cars</a> including EVs, check running costs with the <a href="{{ route('costperkm') }}">cost per km calculator</a>, or ask our <a href="{{ route('assistant') }}">AI assistant</a>.</p>
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

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(() => {
  const $ = s => document.querySelector(s);
  const URLS = { geo: @json(route('evstations.geocode')), search: @json(route('evstations.search')) };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const status = (t, err) => { const el = $('#evStatus'); el.textContent = t; el.classList.toggle('err', !!err); };
  const gmaps = (lat, lng) => 'https://www.google.com/maps/search/EV+charging+station/@' + lat + ',' + lng + ',13z';
  let map = null, layer = null, markers = [], busy = false;

  function ensureMap(lat, lng) {
    if (!window.L) return null;
    if (!map) {
      map = L.map('evMap', { scrollWheelZoom: false }).setView([lat, lng], 12);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
      layer = L.layerGroup().addTo(map);
    }
    return map;
  }
  const getJson = async url => { const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); let d = {}; try { d = await r.json(); } catch (e) {} return { ok: r.ok, status: r.status, d }; };

  async function lookup(lat, lng, label) {
    if (busy) return; busy = true; $('#evGo').disabled = true;
    status('Finding charging stations' + (label ? ' near ' + label : '') + '…');
    let r; try { r = await getJson(URLS.search + '?lat=' + lat + '&lng=' + lng + '&radius=' + $('#evR').value); } catch (e) { r = { ok: false, d: {} }; }
    busy = false; $('#evGo').disabled = false;
    const m = ensureMap(lat, lng); if (m) { m.setView([lat, lng], 12); layer.clearLayers(); markers = []; }
    if (!r.ok) {
      status((r.d && r.d.message) || 'We could not load charging stations just now. Please try again in a moment.', true);
      $('#evList').innerHTML = '<div class="evf-empty">You can also <a href="' + gmaps(lat, lng) + '" target="_blank" rel="noopener">open this area in Google Maps ↗</a>.</div>';
      return;
    }
    const st = r.d.stations || [];
    if (!st.length) {
      status('No charging stations found within ' + r.d.radius + ' km' + (label ? ' of ' + label : '') + '.');
      $('#evList').innerHTML = '<div class="evf-empty">Try a larger radius or a nearby city, or <a href="' + gmaps(lat, lng) + '" target="_blank" rel="noopener">search this area in Google Maps ↗</a>.</div>';
      return;
    }
    status(st.length + ' charging station' + (st.length === 1 ? '' : 's') + ' found' + (label ? ' near ' + label : '') + ', nearest first.');
    $('#evList').innerHTML = st.map((s, i) => {
      const dir = 'https://www.google.com/maps/dir/?api=1&destination=' + s.lat + ',' + s.lng;
      const tags = [].concat(s.sockets || [], s.fee ? [s.fee] : [], s.capacity ? [s.capacity + ' points'] : [], s.access ? [s.access] : []).map(t => '<span>' + esc(t) + '</span>').join('');
      return '<div class="evf-item" data-i="' + i + '"><span class="km">' + s.km + ' km</span><b>' + esc(s.name) + '</b>'
        + (s.operator && s.operator !== s.name ? '<small>' + esc(s.operator) + '</small>' : '') + (s.address ? '<small>' + esc(s.address) + '</small>' : '') + (s.hours ? '<small>🕒 ' + esc(s.hours) + '</small>' : '')
        + (tags ? '<div class="evf-tags">' + tags + '</div>' : '') + '<a href="' + dir + '" target="_blank" rel="noopener">Get directions ↗</a></div>';
    }).join('');
    if (m) {
      L.circleMarker([lat, lng], { radius: 7, color: '#0b3d91', fillColor: '#0b3d91', fillOpacity: .9 }).addTo(layer).bindTooltip('Search centre');
      st.forEach((s, i) => { const mk = L.marker([s.lat, s.lng]).addTo(layer).bindPopup('<b>' + esc(s.name) + '</b><br>' + s.km + ' km' + ((s.sockets || []).length ? '<br>' + esc(s.sockets.join(', ')) : '')); mk.on('click', () => mark(i, false)); markers.push(mk); });
      const b = L.latLngBounds(st.slice(0, 25).map(s => [s.lat, s.lng]).concat([[lat, lng]])); m.fitBounds(b, { padding: [30, 30], maxZoom: 14 });
      setTimeout(() => m.invalidateSize(), 150);
    }
  }
  function mark(i, pan) {
    document.querySelectorAll('.evf-item').forEach(n => n.classList.toggle('on', +n.dataset.i === i));
    const it = document.querySelector('.evf-item[data-i="' + i + '"]'); if (it && !pan) it.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    if (pan && markers[i]) { map.setView(markers[i].getLatLng(), Math.max(map.getZoom(), 14)); markers[i].openPopup(); }
  }
  $('#evList').addEventListener('click', e => { const it = e.target.closest('.evf-item'); if (it && !e.target.closest('a')) mark(+it.dataset.i, true); });

  async function byName(q) {
    q = (q || '').trim(); if (q.length < 2) { status('Please enter a city or area name.', true); return; }
    status('Locating ' + q + '…');
    let r; try { r = await getJson(URLS.geo + '?q=' + encodeURIComponent(q)); } catch (e) { r = { ok: false, d: {} }; }
    if (!r.ok) { status((r.d && r.d.message) || 'We could not find that place. Try a nearby city name.', true); return; }
    $('#evQ').value = r.d.name; lookup(r.d.lat, r.d.lng, r.d.name);
  }
  $('#evForm').addEventListener('submit', e => { e.preventDefault(); byName($('#evQ').value); });
  $('#evCities').addEventListener('click', e => { const b = e.target.closest('button[data-city]'); if (b) { $('#evQ').value = b.dataset.city; byName(b.dataset.city); } });
  $('#evLoc').addEventListener('click', () => {
    if (!navigator.geolocation) { status('Your browser does not support location. Please search by city instead.', true); return; }
    status('Getting your location…');
    navigator.geolocation.getCurrentPosition(p => lookup(+p.coords.latitude.toFixed(4), +p.coords.longitude.toFixed(4), 'you'),
      () => status('Location permission was denied. Please search by city instead.', true), { enableHighAccuracy: false, timeout: 12000, maximumAge: 300000 });
  });
  ensureMap(20.59, 78.96); if (map) map.setView([22.5, 79], 4);
  // Start from the visitor's detected / chosen location (site header): search around it automatically, once.
  let auto = false;
  const useSaved = l => { if (auto || !l || busy) return; auto = true; $('#evQ').value = l.city || ''; if (l.lat && l.lng) lookup(l.lat, l.lng, l.city); else if (l.city) byName(l.city); };
  if (window.AIC && AIC.loc) useSaved(AIC.loc);
  document.addEventListener('aic:loc', e => useSaved(e.detail));
})();
</script>
@endsection
