/* Automobil India — public site behaviour: theme, mobile nav, AI voice/chat assistant. No dependencies. */
(function () {
  const $ = (s, r = document) => r.querySelector(s);

  /* ---------- theme + nav ---------- */
  const root = document.documentElement, themeBtn = $('#themeBtn');
  const paint = () => { if (themeBtn) themeBtn.innerHTML = '<i class="ti ti-' + (root.dataset.theme === 'dark' ? 'sun' : 'moon') + '"></i>'; };
  paint();
  themeBtn && themeBtn.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('theme', root.dataset.theme); } catch (e) {}
    paint();
  });
  const burger = $('#burger'), nav = $('#nav');
  burger && burger.addEventListener('click', () => nav.classList.toggle('open'));

  /* ---------- mobile menu ---------- */
  const mb = $('#mb'), menu = $('#menu');
  mb && menu && mb.addEventListener('click', () => { const open = menu.hidden; menu.hidden = !open; mb.setAttribute('aria-expanded', open ? 'true' : 'false'); });

  /* ---------- shared helpers: reCAPTCHA token + the visitor's current location ---------- */
  const lsx = { get: k => { try { return localStorage.getItem(k) } catch (e) { return null } }, set: (k, v) => { try { localStorage.setItem(k, v) } catch (e) {} } };
  const rcKey = (($('meta[name=recaptcha-site-key]') || {}).content || '');
  const AIC = window.AIC = window.AIC || {};
  // Invisible reCAPTCHA v3: resolves to a token, or '' when no key is configured / it could not load (the server decides what to do).
  AIC.recaptcha = action => new Promise(res => {
    if (!rcKey) return res('');
    let done = false; const fin = t => { if (!done) { done = true; res(t || ''); } };
    setTimeout(() => fin(''), 6000);
    const go = () => { try { grecaptcha.ready(() => grecaptcha.execute(rcKey, { action }).then(fin, () => fin(''))); } catch (e) { fin(''); } };
    if (window.grecaptcha && grecaptcha.ready) go(); else { let n = 0; const t = setInterval(() => { if (window.grecaptcha && grecaptcha.ready) { clearInterval(t); go(); } else if (++n > 40) { clearInterval(t); fin(''); } }, 150); }
  });

  // One saved location for the whole site: { city, lat, lng, src: 'gps' | 'manual', ts }.
  const LKEY = 'aic_loc', cityEl = $('#city'), locBox = $('#loc'), locList = $('#cl'), modal = $('#cm');
  const readLoc = () => { try { return JSON.parse(lsx.get(LKEY) || 'null'); } catch (e) { return null; } };
  AIC.loc = readLoc();
  AIC.city = () => (AIC.loc && AIC.loc.city) || '';
  const norm = x => String(x || '').toLowerCase().replace(/[^a-z]/g, '');
  // Fill every place that asks for a city (anything marked data-loc-city, plus the city field of any form) unless the visitor already typed there.
  function fillCity() {
    const c = AIC.city(); if (!c) return;
    document.querySelectorAll('[data-loc-city], input[name=city], select[name=city]').forEach(el => {
      if (el.dataset.touched === '1' || el.dataset.locOff !== undefined) return;
      if (el.tagName === 'SELECT') {
        const o = Array.from(el.options).find(x => norm(x.value) === norm(c) || norm(x.textContent) === norm(c));
        if (o && el.value !== o.value) { el.value = o.value; el.dispatchEvent(new Event('change', { bubbles: true })); }
      } else if (!el.value || el.dataset.locFilled === '1') {
        if (el.value !== c) { el.value = c; el.dataset.locFilled = '1'; el.dispatchEvent(new Event('change', { bubbles: true })); }
      }
    });
  }
  document.addEventListener('input', e => { const t = e.target; if (t && t.matches && t.matches('[data-loc-city], input[name=city], select[name=city]')) { t.dataset.touched = '1'; t.dataset.locFilled = ''; } }, true);
  document.addEventListener('change', e => { const t = e.target; if (t && t.matches && t.matches('select[name=city]') && e.isTrusted) t.dataset.touched = '1'; }, true);
  function setLoc(l, src) {
    AIC.loc = Object.assign({}, l, { src: src || l.src || 'manual', ts: Date.now() });
    lsx.set(LKEY, JSON.stringify(AIC.loc));
    if (cityEl) cityEl.textContent = AIC.loc.city;
    fillCity();
    document.dispatchEvent(new CustomEvent('aic:loc', { detail: AIC.loc }));
  }
  AIC.setLoc = setLoc; AIC.fillCity = fillCity;
  // Browser coordinates -> city (server maps it to the nearest listed city or asks OpenStreetMap).
  AIC.detect = () => new Promise((resolve, reject) => {
    if (!navigator.geolocation) return reject(new Error('unsupported'));
    navigator.geolocation.getCurrentPosition(async p => {
      try {
        const r = await fetch('/location/resolve?lat=' + p.coords.latitude.toFixed(4) + '&lng=' + p.coords.longitude.toFixed(4), { headers: { Accept: 'application/json' } });
        if (!r.ok) throw new Error('resolve'); const d = await r.json();
        setLoc({ city: d.city, lat: d.lat, lng: d.lng }, 'gps'); resolve(AIC.loc);
      } catch (e) { reject(e); }
    }, err => reject(err), { enableHighAccuracy: false, timeout: 12000, maximumAge: 600000 });
  });

  let cityList = null;
  async function cities() { if (cityList) return cityList; try { cityList = await (await fetch('/location/cities')).json(); } catch (e) { cityList = ['Delhi', 'Mumbai', 'Bengaluru', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad']; } return cityList; }
  const pick = city => { setLoc({ city }, 'manual'); closeUi(); };
  function closeUi() { locBox && locBox.classList.remove('open'); lb && lb.setAttribute('aria-expanded', 'false'); modal && (modal.classList.remove('show'), modal.hidden = true); }
  const lb = $('#lb');
  async function paintList(box, withCur) {
    const list = await cities(), cur = AIC.city();
    box.innerHTML = (withCur ? '<button type="button" class="cur" data-act="detect">📍 Use my current location' + (cur ? ' <small style="font-weight:500;color:var(--ink2)">(' + cur + ')</small>' : '') + '</button>' : '')
      + list.map(c => '<button type="button" data-city="' + c.replace(/"/g, '') + '">' + c + '</button>').join('');
  }
  lb && lb.addEventListener('click', async e => { e.stopPropagation(); const open = !locBox.classList.contains('open'); if (open) await paintList(locList, true); locBox.classList.toggle('open', open); lb.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  document.addEventListener('click', e => { if (locBox && !locBox.contains(e.target)) locBox.classList.remove('open'); });
  locList && locList.addEventListener('click', async e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.city) return pick(b.dataset.city);
    if (b.dataset.act === 'detect') { b.textContent = 'Detecting…'; try { await AIC.detect(); closeUi(); } catch (er) { b.textContent = 'Could not detect - pick a city below'; } }
  });
  // First-visit dialog: only shown when automatic detection is not possible (permission denied / unsupported).
  function openModal(msg) {
    if (!modal) return; try { if (sessionStorage.getItem('aic_modal')) return; sessionStorage.setItem('aic_modal', '1'); } catch (e) {}
    modal.hidden = false; requestAnimationFrame(() => modal.classList.add('show'));
    const er = $('#cm-err'); if (er) er.textContent = msg || '';
  }
  if (modal) {
    modal.hidden = true;
    $('#cm-x') && $('#cm-x').addEventListener('click', closeUi);
    modal.addEventListener('click', e => { if (e.target === modal) closeUi(); });
    $('#cm-go') && $('#cm-go').addEventListener('click', async () => { const er = $('#cm-err'); er.textContent = ''; try { await AIC.detect(); closeUi(); } catch (e) { er.textContent = 'We could not detect your location. Please pick your city below.'; $('#cm-man').click(); } });
    $('#cm-man') && $('#cm-man').addEventListener('click', async () => { const box = $('#cm-cities'); await paintList(box, false); box.hidden = false; $('#cm-man').setAttribute('aria-expanded', 'true'); });
    $('#cm-cities') && $('#cm-cities').addEventListener('click', e => { const b = e.target.closest('button[data-city]'); if (b) pick(b.dataset.city); });
  }
  // Automatic: use the saved place; otherwise ask the browser for the current location once and keep it.
  (async function autoLocate() {
    if (AIC.loc && AIC.loc.city) { if (cityEl) cityEl.textContent = AIC.loc.city; fillCity(); document.dispatchEvent(new CustomEvent('aic:loc', { detail: AIC.loc })); }
    const stale = AIC.loc && AIC.loc.src === 'gps' && Date.now() - (AIC.loc.ts || 0) > 7 * 864e5;
    if (AIC.loc && AIC.loc.city && !stale) return;
    let state = 'prompt'; try { state = (await navigator.permissions.query({ name: 'geolocation' })).state; } catch (e) {}
    if (AIC.loc && AIC.loc.city && state !== 'granted') return;       // stale and would need a prompt: keep what we have
    if (state === 'denied') return openModal('');
    try { await AIC.detect(); } catch (e) { if (!(AIC.loc && AIC.loc.city)) openModal(''); }
  })();
  // A form field the visitor focuses late (widgets built after load) still gets the city.
  document.addEventListener('focusin', e => { if (AIC.city() && e.target && e.target.matches && e.target.matches('[data-loc-city], input[name=city], select[name=city]')) fillCity(); });

  /* ---------- assistant ---------- */
  const ag = $('#ag'); if (!ag) return;
  const $$ = (s, r = ag) => Array.from(r.querySelectorAll(s));
  const csrf = ($('meta[name=csrf-token]') || {}).content || '';
  const name = ag.dataset.name, page = ag.dataset.mode === 'page';
  const el = { panel: $('#agPanel'), msgs: $('#agMsgs'), hero: $('#agHero'), form: $('#agForm'), text: $('#agText'), mic: $('#agMic'), status: $('#agStatus'), speak: $('#agSpeak'),
    fab: $('#agFab'), tip: $('#agTip'), left: $('#agLeft'), hello: $('#agHello') };
  const views = { load: $('#vLoad'), lead: $('#vLead'), chat: $('#vChat'), fb: $('#vFb') };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let token = '', visitor = '', history = [], sent = 0, rated = false, state = 'load', booted = false, queued = null, freeLeft = null;
  let speakOn = false, voiceMode = false, rec = null, audio = null, busy = false, cooldownUntil = 0, lang = 'en', cid = '';
  const store = { get: k => { try { return localStorage.getItem(k) } catch (e) { return null } }, set: (k, v) => { try { localStorage.setItem(k, v) } catch (e) {} }, del: k => { try { localStorage.removeItem(k) } catch (e) {} } };
  token = store.get('aw_token') || '';
  const savedSpeak = store.get('ag_speak'); speakOn = savedSpeak === null ? ag.dataset.voice === '1' : savedSpeak === '1';
  const paintSpeak = () => { el.speak.innerHTML = '<i class="ti ti-volume' + (speakOn ? '' : '-off') + '"></i>'; el.speak.classList.toggle('on', speakOn); };
  paintSpeak();
  // Widget text is English; the assistant itself mirrors the visitor's language (English / Hindi / Hinglish).
  const T = { en: { left: n => n > 0 ? n + ' message' + (n === 1 ? '' : 's') + ' left today' : 'Daily limit reached', conn: 'Connection problem. Please check your internet and try again.', wrong: 'Something went wrong on our side. Please try again in a moment.', expired: 'Your session has expired. Please refresh the page and try again.', toofast: 'You are sending messages too quickly. Please wait a moment.', free: n => n > 0 ? n + ' free message' + (n === 1 ? '' : 's') + ' left' : '', gateNote: 'To continue chatting, please share your details once. It takes less than a minute.', tabNew: 'New car', tabUsed: 'Used car', tabSell: 'Sell my car', wait: 'Please wait ', sec: 's…', think: 'Thinking…', speaking: 'Speaking…', listening: 'Listening… speak now', nocatch: "Didn't catch that — tap the mic to stop, or keep talking", mic: 'Please allow microphone access to talk to ' + name + '.', nosr: 'Voice input is not supported in this browser. Please use Chrome, Edge or Safari, or type your question.', hello: 'Hello', hello2: 'Hello there!', novoice: 'Voice is unavailable right now.', tap: 'Tap the mic or speaker to enable voice', resume: 'Tap the mic to continue talking', name: 'Please enter your name.', phone: 'Enter a valid 10-digit Indian mobile number.', email: 'Enter a valid email address.', src: 'From our site' } };
  const tr = k => T.en[k];

  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const fmt = s => esc(s).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/(https?:\/\/[^\s<)]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>').replace(/\n/g, '<br>');
  const down = () => { el.msgs.scrollTop = el.msgs.scrollHeight; };
  const status = t => el.status.textContent = t || '';
  const show = v => { state = v; Object.entries(views).forEach(([k, n]) => n.classList.toggle('show', k === v)); };
  const headers = () => ({ 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(token ? { 'X-Assistant-Token': token } : {}) });
  let pending = Promise.resolve();   // a session switch in flight: other calls wait for it so they never use the old token
  const api = async (url, body) => {
    await pending;
    const r = await fetch(url, { method: body ? 'POST' : 'GET', headers: headers(), credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined });
    let data = {}; try { data = await r.json(); } catch (e) {}
    return { ok: r.ok, status: r.status, data };
  };
  const firstError = d => (d.errors && Object.values(d.errors)[0] && Object.values(d.errors)[0][0]) || d.message || tr('wrong');
  // Never show a raw server / framework error to a visitor: only messages we wrote ourselves (they carry a `reason`) or validation text.
  const errText = r => {
    const d = r.data || {};
    if (r.status === 0) return tr('conn');
    if (r.status === 419) return tr('expired');
    if (r.status === 429 && !d.reason) return tr('toofast');
    if (r.status >= 500 && !d.reason) return tr('wrong');
    if (r.status === 404 || r.status === 405) return tr('wrong');
    return firstError(d);
  };

  /* ---------- open / close ---------- */
  function open() {
    el.panel.classList.add('open'); ag.classList.add('is-open'); el.tip && el.tip.classList.remove('show');
    if (!booted) boot(); else if (state === 'chat') setTimeout(() => el.text.focus(), 300);
    persist();
  }
  function close(force) {
    if (!force && sent >= 2 && !rated && state === 'chat') { stopAll(); show('fb'); return; }
    stopAll(); el.panel.classList.remove('open'); el.panel.classList.remove('big'); ag.classList.remove('is-open');
    if (state === 'fb') show('chat');
    persist();
  }
  el.fab && el.fab.addEventListener('click', () => { el.fab.classList.add('splash'); setTimeout(() => el.fab.classList.remove('splash'), 700); open(); });
  $('#agClose') && $('#agClose').addEventListener('click', () => close());
  $('#agExpand') && $('#agExpand').addEventListener('click', e => { const big = el.panel.classList.toggle('big'); e.currentTarget.innerHTML = '<i class="ti ti-arrows-' + (big ? 'minimize' : 'diagonal') + '"></i>'; });
  window.openAssistant = q => { open(); if (q) { const go = () => state === 'chat' ? send(q) : setTimeout(go, 400); go(); } };
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && el.panel.classList.contains('open') && !page) close(); });
  if (el.tip) { setTimeout(() => { if (!ag.classList.contains('is-open')) { el.tip.classList.add('show'); setTimeout(() => el.tip.classList.remove('show'), 6000); } }, 3500); }
  // Leaving the page must never leave a voice talking in the background.
  addEventListener('pagehide', () => { try { persist(); stopAudio(); rec && rec.abort(); } catch (e) {} });

  /* ---------- boot: who is this visitor? ---------- */
  async function boot() {
    booted = true; show('load');
    const r = await api(ag.dataset.me).catch(() => null);
    if (r && r.ok && r.data.gate) { showLead(); }
    else if (r && r.ok) { enter(r.data); }       // verified, or still within the free messages
    else { enter({}); }                           // could not reach the server: let them try, errors are handled per message
  }
  // The details form appears only once the free messages are used (or when the site owner sets 0 free messages).
  function showLead(withNote) {
    const n = $('#leadNote'); if (n) { n.textContent = withNote ? tr('gateNote') : ''; n.hidden = !withNote; }
    show('lead');
  }
  // Clears every old conversation of this visitor on the server and switches to the fresh session token it returns.
  const newSession = () => (pending = api(ag.dataset.reset, { wipe: 1 }).then(r => { if (r && r.ok && r.data.token) { token = r.data.token; store.set('aw_token', token); } }).catch(() => {}));
  function enter(d) {
    if (d.token) { token = d.token; store.set('aw_token', token); }
    visitor = (d.name || '').split(' ')[0]; visitorPhone = d.phone || ''; visitorCity = d.city || '';
    el.hello.textContent = visitor ? tr('hello') + ', ' + visitor + '!' : tr('hello2');
    freeLeft = typeof d.free_left === 'number' ? d.free_left : null;
    if (typeof d.left === 'number') leftHint(d.left);
    show('chat'); setTimeout(() => el.text.focus(), 350);
    const st = loadState(), restored = !!(st && st.items && st.items.length);
    if (restored && !items.length) restore(st);
    ready = true;
    // The spoken greeting plays once per visitor per day - not on every re-open or page change. A refreshed page cannot
    // play audio or listen until the visitor taps once (browser rule), so we say so instead of staying silently mute.
    const today = new Date().toISOString().slice(0, 10);
    if (speakOn && !restored && store.get('aw_greeted') !== today) { store.set('aw_greeted', today); setTimeout(() => speak(ag.dataset.greet), 500); }
    if (!restored) store.set('aw_greeted', today);
    if (restored && st.voice) { el.mic.classList.add('resume'); status(tr('resume')); setTimeout(() => { if (!voiceMode) status(''); }, 6000); }
    if (queued) { const p = queued; queued = null; setTimeout(() => send(p), 400); }   // the message that triggered the details form
  }
  let lastLeft = null;
  const leftHint = n => { lastLeft = n; el.left.textContent = freeLeft !== null && freeLeft > 0 && n > 0 ? tr('free')(freeLeft) : tr('left')(n); };

  /* ---------- step 1: lead form ---------- */
  const fLead = views.lead, eLead = $('#eLead');
  fLead.addEventListener('submit', async e => {
    e.preventDefault(); eLead.textContent = '';
    const f = new FormData(fLead), v = Object.fromEntries(f.entries());
    v.phone = (v.phone || '').replace(/\D/g, '').replace(/^91(?=\d{10}$)/, '');
    $$('.aw-f', fLead).forEach(x => x.classList.remove('bad'));
    const bad = (n, m) => { const i = fLead.elements[n]; i.closest('.aw-f').classList.add('bad'); i.focus(); eLead.textContent = m; return true; };
    if (!/^[\p{L}\p{M}\s.'\-]{2,60}$/u.test((v.name || '').trim())) return bad('name', tr('name'));
    if (!/^[6-9]\d{9}$/.test(v.phone)) return bad('phone', tr('phone'));
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test((v.email || '').trim())) return bad('email', tr('email'));
    const btn = $('button[type=submit]', fLead); btn.disabled = true;
    v.recaptcha = await AIC.recaptcha('assistant_lead');          // invisible check - nothing for the visitor to solve
    const r = await api(ag.dataset.lead, v).catch(() => ({ ok: false, status: 0, data: {} }));
    btn.disabled = false;
    if (!r.ok) { eLead.textContent = errText(r); return; }
    enter(r.data);
  });

  /* ---------- chat: everything visible is kept in `items` (sessionStorage) so the conversation survives refresh and page changes ---------- */
  let items = [], visitorPhone = '', visitorCity = '';
  const SKEY = 'aw_state', CKEY = 'aw_cid';
  // One id per conversation; the new-chat button creates a fresh one.
  const newCid = () => Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
  // A page refresh or moving between pages keeps the same chat, session and open panel (sessionStorage survives both).
  // Only the widget's own refresh / new-chat button starts over.
  try { cid = sessionStorage.getItem(CKEY) || ''; } catch (e) {}
  if (!cid) { cid = newCid(); try { sessionStorage.setItem(CKEY, cid); } catch (e) {} }
  let ready = false;   // do not overwrite the saved conversation before it has been restored
  const persist = () => { if (!ready) return; try { sessionStorage.setItem(SKEY, JSON.stringify({ open: ag.classList.contains('is-open'), items: items.slice(-40), history: history.slice(-6), sent, rated, voice: voiceMode, lang })); } catch (e) {} };
  const loadState = () => { try { return JSON.parse(sessionStorage.getItem(SKEY) || 'null'); } catch (e) { return null; } };
  const typing = () => { const d = document.createElement('div'); d.className = 'aw-msg bot aw-typing'; d.innerHTML = '<i></i><i></i><i></i>'; el.msgs.appendChild(d); down(); return d; };

  function push(it, animate) { items.push(it); render(it, animate); persist(); return it; }
  function restore(st) { items = (st.items || []).filter(i => ['msg', 'links', 'act', 'chips'].includes(i.k)); history = st.history || []; sent = st.sent || 0; rated = !!st.rated; if (st.lang) lang = st.lang; items.forEach(it => render(it, false)); }

  function render(it, animate) {
    el.hero.classList.add('gone');
    if (it.k === 'msg') return bubble(it, animate);
    if (it.k === 'links') return cards(it);
    if (it.k === 'act') return actionBtn(it);
    if (it.k === 'chips') return chipRow(it);
  }

  /* tap-able answers (New / Used tabs, budget, city): each one is sent as the visitor's next message */
  function clearChips() { items = items.filter(i => i.k !== 'chips'); $$('.aw-quick', el.msgs).forEach(n => n.remove()); }
  function chipRow(it) {
    const w = document.createElement('div'); w.className = 'aw-quick';
    it.chips.forEach(c => { const b = document.createElement('button'); b.type = 'button'; b.textContent = c.label; b.addEventListener('click', () => send(c.q, false, c.label)); w.appendChild(b); });
    el.msgs.appendChild(w); down();
  }

  function bubble(it, animate) {
    const d = document.createElement('div'); d.className = 'aw-msg ' + (it.r === 'user' ? 'me' : it.r === 'err' ? 'bot err' : 'bot');
    el.msgs.appendChild(d);
    const tail = it.x ? '<span class="aw-src">' + esc(it.x) + '</span>' : '';
    if (animate && !reduce && it.t.length < 700) {                       // word-by-word reveal
      const words = it.t.split(/(\s+)/); let i = 0, buf = '';
      const step = () => { buf += words.slice(i, i + 3).join(''); i += 3; d.innerHTML = fmt(buf); down(); if (i < words.length) setTimeout(step, 28); else d.innerHTML = fmt(it.t) + tail; };
      step();
    } else d.innerHTML = fmt(it.t) + tail;
    down();
  }
  const add = (role, text, extra, animate) => push({ k: 'msg', r: role, t: text, x: extra || '' }, animate);

  /* link cards: just open the car / page (no buttons - the AI takes the enquiry in conversation) */
  function cards(it) {
    const w = document.createElement('div'); w.className = 'aw-links';
    it.links.forEach(l => {
      const img = l.image ? '<img src="' + esc(l.image) + '" alt="" loading="lazy">' : '';
      const a = document.createElement('a'); a.className = 'aw-link'; a.href = l.url; a.target = '_blank'; a.rel = 'noopener';
      a.innerHTML = img + '<span>' + esc(l.title) + (l.price ? '<small class="aw-price">' + esc(l.price) + '</small>' : '') + '</span><i class="ti ti-arrow-up-right"></i>';
      w.appendChild(a);
    });
    el.msgs.appendChild(w); down();
  }

  function actionBtn(it) {
    const a = document.createElement('a'); a.className = 'aw-go'; a.href = it.url; a.innerHTML = esc(it.label) + ' <i class="ti ti-arrow-right"></i>';
    a.addEventListener('click', () => { persist(); });
    el.msgs.appendChild(a); down();
  }

  async function send(text, viaVoice, label) {
    text = (text || '').trim().slice(0, 300);
    if (!text || busy) return;
    clearChips();
    if (Date.now() < cooldownUntil) { status(tr('wait') + Math.ceil((cooldownUntil - Date.now()) / 1000) + tr('sec')); return; }
    busy = true; ag.classList.add('busy'); el.form.classList.add('busy'); const mine = add('user', label || text); const t = typing(); status(viaVoice ? tr('think') : '');
    let r;
    try { r = await api(ag.dataset.chat, { message: text, history: history.slice(-6), voice: !!(viaVoice || voiceMode), cid }); }
    catch (err) { r = { ok: false, status: 0, data: { message: tr('conn') } }; }
    t.remove(); busy = false; ag.classList.remove('busy'); status('');
    if (r.status === 401 && r.data.gate) {      // free messages used: take the details, then send this message again
      items = items.filter(i => i !== mine); if (el.msgs.lastElementChild && el.msgs.lastElementChild.classList.contains('me')) el.msgs.lastElementChild.remove();
      queued = text; persist(); showLead(true); return;
    }
    if (!r.ok) {
      if (r.data.retry_after) cooldownUntil = Date.now() + Math.min(r.data.retry_after, 60) * 1000;
      add('err', errText(r));
      if (['daily_messages', 'daily_tokens', 'lifetime_tokens', 'ip_tokens', 'blocked'].includes(r.data.reason)) { leftHint(0); el.text.disabled = true; }
      if (voiceMode) listen(); return;
    }
    const d = r.data; sent++;
    if (d.token && !token) { token = d.token; store.set('aw_token', token); }   // keeps the free-message count with this visitor
    if (freeLeft !== null && freeLeft > 0) freeLeft--;
    if (d.lang === 'hi' || d.lang === 'en') { lang = d.lang; if (lastLeft !== null) leftHint(lastLeft); }
    history.push({ role: 'user', content: text }, { role: 'assistant', content: d.answer.slice(0, 300) });
    add('bot', d.answer, d.source === 'kb' ? tr('src') : '', true);
    if (d.links && d.links.length) push({ k: 'links', links: d.links });
    let nav = null;
    (d.actions || []).forEach(a => {
      if (a.type === 'link') push({ k: 'act', url: a.url, label: a.label });
      else if (a.type === 'navigate') { push({ k: 'act', url: a.url, label: a.label }); nav = a; }
    });
    if (d.chips && d.chips.length) push({ k: 'chips', chips: d.chips });
    if (typeof d.left === 'number') leftHint(d.left);
    if (speakOn || viaVoice || voiceMode) await speak(d.answer);
    if (voiceMode) listen();
    if (nav && nav.auto) { persist(); setTimeout(() => { persist(); location.href = nav.url; }, reduce ? 300 : 1500); }   // open the real page; the chat comes along
  }
  el.form.addEventListener('submit', e => { e.preventDefault(); const v = el.text.value.trim(); if (v) { el.text.value = ''; send(v); } });
  /* First-step tabs: New car / Used car / Sell my car. Answered by the server without the AI, then the visitor is guided with tap-able options. */
  async function choose(intent) {
    if (busy) return;
    clearChips(); busy = true; ag.classList.add('busy');
    add('user', { new: tr('tabNew'), used: tr('tabUsed'), sell: tr('tabSell') }[intent]); const t = typing();
    let r; try { r = await api(ag.dataset.chat, { intent, cid, city: AIC.city() }); } catch (err) { r = { ok: false, status: 0, data: {} }; }
    t.remove(); busy = false; ag.classList.remove('busy');
    if (!r.ok) { add('err', errText(r)); return; }
    const d = r.data; if (d.token && !token) { token = d.token; store.set('aw_token', token); }
    history.push({ role: 'assistant', content: d.answer.slice(0, 300) });
    add('bot', d.answer, '', true);
    (d.actions || []).forEach(a => push({ k: 'act', url: a.url, label: a.label }));
    if (d.chips && d.chips.length) push({ k: 'chips', chips: d.chips });
    if (typeof d.left === 'number') leftHint(d.left);
    if (speakOn) speak(d.answer);
  }
  $('#agTabs') && $('#agTabs').addEventListener('click', e => { const b = e.target.closest('button[data-intent]'); if (b) choose(b.dataset.intent); });
  $('#agSuggest') && $('#agSuggest').addEventListener('click', e => { const b = e.target.closest('button[data-q]'); if (b) send(b.dataset.q); });

  /* New chat: clear the conversation but keep the voice working - the assistant greets again and, if the visitor was
     talking by voice, goes straight back to listening. (Before, a refresh left the voice silent until the page was reloaded.) */
  $('#agReset') && $('#agReset').addEventListener('click', () => {
    const wasVoice = voiceMode;
    stopAll(); history = []; sent = 0; busy = false; items = []; try { sessionStorage.removeItem(SKEY); } catch (e) {}
    Array.from(el.msgs.children).forEach(n => { if (n !== el.hero) n.remove(); }); el.hero.classList.remove('gone'); el.text.value = ''; el.text.disabled = false; el.mic.classList.remove('resume');
    cid = newCid(); try { sessionStorage.setItem(CKEY, cid); } catch (e) {}
    if (token) newSession();
    if (state !== 'chat') show('chat');
    ready = true; persist();
    if (speakOn || wasVoice) {
      if (wasVoice) voiceMode = true;     // keep the mic state while the greeting is spoken
      speak(ag.dataset.greet).then(() => { if (voiceMode && state === 'chat' && ag.classList.contains('is-open')) listen(); });
    }
    setTimeout(() => el.text.focus(), 200);
  });

  /* promo carousel */
  (function () { const slides = $$('.aw-slide'), dots = $$('.aw-dots i'); let n = 0;
    if (slides.length > 1) setInterval(() => { if (!ag.classList.contains('is-open') && !page) return; slides[n].classList.remove('on'); dots[n].classList.remove('on'); n = (n + 1) % slides.length; slides[n].classList.add('on'); dots[n].classList.add('on'); }, 4500); })();

  /* ---------- feedback ---------- */
  let stars = 0;
  $$('#fbStars button').forEach(b => b.addEventListener('click', () => { stars = +b.dataset.v; $$('#fbStars button').forEach(x => x.classList.toggle('on', +x.dataset.v <= stars)); }));
  views.fb.addEventListener('submit', async e => {
    e.preventDefault(); rated = true;
    const f = new FormData(views.fb); const body = { rating: stars || null, reason: f.get('reason') || null, comment: (f.get('comment') || '').trim() || null };
    api(ag.dataset.feedback, body).catch(() => {});
    show('chat'); close(true);
  });

  /* ---------- voice out: ElevenLabs via our server (only when the speaker is on), else the browser's own voice ---------- */
  // Every call to speak() gets a generation number. stopAudio() bumps it and releases whoever is waiting for the clip to end, so a
  // stopped / replaced / reset voice can never leave the conversation hanging (and a late-arriving clip is thrown away, not played).
  let gen = 0, release = null;
  function stopAudio() {
    gen++;
    if (audio) { try { audio.pause(); } catch (e) {} audio = null; }
    window.speechSynthesis && speechSynthesis.cancel();
    if (release) { const f = release; release = null; f(); }
  }
  async function speak(text) {
    stopAudio(); const my = gen; status(tr('speaking'));
    const clean = text.replace(/\*\*/g, '').replace(/https?:\/\/\S+/g, '').replace(/[\u{1F300}-\u{1FAFF}☀-➿✅]/gu, '').replace(/\s+/g, ' ').trim().slice(0, 900);
    const done = () => { if (my === gen) status(''); };
    if (!clean) return done();
    const wait = start => new Promise(res => { release = res; start(res); }).then(() => { if (my === gen) release = null; });
    const browser = async () => {
      if (!window.speechSynthesis || my !== gen) return;
      await wait(res => { const u = new SpeechSynthesisUtterance(clean); u.lang = lang === 'hi' ? 'hi-IN' : 'en-IN'; u.rate = 1.0; u.onend = u.onerror = res; speechSynthesis.speak(u); });
    };
    try {
      const r = await fetch(ag.dataset.tts, { method: 'POST', headers: headers(), credentials: 'same-origin', body: JSON.stringify({ text: clean }) });
      if (my !== gen) return;                                  // stopped / reset while the clip was being made
      if (r.status === 200) {                                  // the voice saved in Settings
        const blob = await r.blob(); if (my !== gen) return;
        const url = URL.createObjectURL(blob); const a = new Audio(url); audio = a; let blocked = false;
        await wait(res => { a.onended = a.onerror = res; a.play().catch(() => { blocked = true; res(); }); });
        URL.revokeObjectURL(url);
        if (blocked && my === gen) { status(tr('tap')); setTimeout(() => { if (my === gen) status(''); }, 4000); return; }
        return done();
      }
      if (r.status === 204) { await browser(); return done(); }   // no ElevenLabs key saved: browser voice is all there is
      let m = tr('novoice'); try { m = (await r.json()).message || m; } catch (e) {}
      if (ag.dataset.fallback === '1') { await browser(); return done(); }
      if (my === gen) { status(m); setTimeout(() => { if (my === gen) status(''); }, 4000); }    // stay silent rather than sound like a different voice
      return;
    } catch (e) {}
    if (my === gen && (ag.dataset.voice !== '1' || ag.dataset.fallback === '1')) await browser();
    done();
  }
  el.speak.addEventListener('click', () => { speakOn = !speakOn; store.set('ag_speak', speakOn ? '1' : '0'); paintSpeak(); if (!speakOn) { stopAudio(); status(''); } });

  /* ---------- voice in: browser speech recognition (free) ---------- */
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  function stopAll() { voiceMode = false; stopAudio(); try { rec && rec.abort(); } catch (e) {} rec = null; el.mic.classList.remove('live'); status(''); }
  function listen() {
    if (!SR || !voiceMode) return;
    try { rec && rec.abort(); } catch (e) {}
    // continuous: the browser would otherwise stop at the first short pause and send a half sentence. We wait for a real silence instead.
    const r = rec = new SR(); r.lang = lang === 'hi' ? 'hi-IN' : 'en-IN'; r.interimResults = true; r.continuous = true; r.maxAlternatives = 1;
    let finalText = '', silence = null, sent = false;
    const SILENCE_MS = 2500;                                      // quiet this long after the last words = the visitor is done
    const finish = () => {
      clearTimeout(silence);
      if (sent || rec !== r) return;
      el.mic.classList.remove('live');
      const v = finalText.trim(); el.text.value = '';
      if (v && voiceMode) { sent = true; try { r.abort(); } catch (e) {} send(v, true); }
      else if (voiceMode) { status(tr('nocatch')); setTimeout(() => { if (voiceMode && !busy) listen(); }, 400); }
    };
    r.onstart = () => { el.mic.classList.add('live'); status(tr('listening')); };
    r.onresult = e => {
      let s = ''; for (const x of e.results) s += x[0].transcript + ' ';
      finalText = s.trim(); el.text.value = finalText;
      clearTimeout(silence); silence = setTimeout(() => { try { r.stop(); } catch (er) {} setTimeout(finish, 300); }, SILENCE_MS);
    };
    r.onerror = e => { if (e.error === 'not-allowed') { alert(tr('mic')); stopAll(); } };
    r.onend = finish;                                             // the browser ended it by itself (long session / network): send what we have
    try { r.start(); } catch (e) {}
  }
  el.mic.addEventListener('click', () => {
    if (!SR) { alert(tr('nosr')); return; }
    if (voiceMode) { stopAll(); return; }
    el.mic.classList.remove('resume'); voiceMode = true; stopAudio(); listen();
  });

  /* ---------- page mode + deep links ---------- */
  if (page) { el.panel.classList.add('open'); ag.classList.add('is-open'); boot(); }
  else { const st = loadState(); if (st && st.open) setTimeout(open, 200); }   // came here from an assistant link: reopen with the conversation
  const ask = new URLSearchParams(location.search).get('ask');
  if (ask && page) setTimeout(() => window.openAssistant(ask), 300);
  document.querySelectorAll('[data-ask]').forEach(b => b.addEventListener('click', () => window.openAssistant(b.dataset.ask)));
})();
