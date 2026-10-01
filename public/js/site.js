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

  /* ---------- assistant ---------- */
  const ag = $('#ag'); if (!ag) return;
  const $$ = (s, r = ag) => Array.from(r.querySelectorAll(s));
  const csrf = ($('meta[name=csrf-token]') || {}).content || '';
  const name = ag.dataset.name, page = ag.dataset.mode === 'page';
  const el = { panel: $('#agPanel'), msgs: $('#agMsgs'), hero: $('#agHero'), form: $('#agForm'), text: $('#agText'), mic: $('#agMic'), status: $('#agStatus'), speak: $('#agSpeak'),
    fab: $('#agFab'), tip: $('#agTip'), left: $('#agLeft'), hello: $('#agHello') };
  const views = { load: $('#vLoad'), lead: $('#vLead'), otp: $('#vOtp'), chat: $('#vChat'), fb: $('#vFb') };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let token = '', visitor = '', history = [], sent = 0, rated = false, state = 'load', booted = false;
  let speakOn = false, voiceMode = false, rec = null, audio = null, busy = false, cooldownUntil = 0;
  const store = { get: k => { try { return localStorage.getItem(k) } catch (e) { return null } }, set: (k, v) => { try { localStorage.setItem(k, v) } catch (e) {} }, del: k => { try { localStorage.removeItem(k) } catch (e) {} } };
  token = store.get('aw_token') || '';
  const savedSpeak = store.get('ag_speak'); speakOn = savedSpeak === null ? ag.dataset.voice === '1' : savedSpeak === '1';
  let greeted = false;
  const paintSpeak = () => { el.speak.innerHTML = '<i class="ti ti-volume' + (speakOn ? '' : '-off') + '"></i>'; el.speak.classList.toggle('on', speakOn); };
  paintSpeak();

  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const fmt = s => esc(s).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\n/g, '<br>');
  const down = () => { el.msgs.scrollTop = el.msgs.scrollHeight; };
  const status = t => el.status.textContent = t || '';
  const show = v => { state = v; Object.entries(views).forEach(([k, n]) => n.classList.toggle('show', k === v)); };
  const headers = () => ({ 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(token ? { 'X-Assistant-Token': token } : {}) });
  const api = async (url, body) => {
    const r = await fetch(url, { method: body ? 'POST' : 'GET', headers: headers(), credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined });
    let data = {}; try { data = await r.json(); } catch (e) {}
    return { ok: r.ok, status: r.status, data };
  };
  const firstError = d => (d.errors && Object.values(d.errors)[0] && Object.values(d.errors)[0][0]) || d.message || 'Something went wrong. Please try again.';

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

  /* ---------- boot: who is this visitor? ---------- */
  async function boot() {
    booted = true; show('load');
    const r = await api(ag.dataset.me).catch(() => null);
    if (r && r.ok && r.data.verified) { enter(r.data); }
    else if (r && r.ok && r.data.gate) { store.del('aw_token'); token = ''; show('lead'); }
    else if (r && r.ok) { enter(r.data); }
    else { show('lead'); }
  }
  function enter(d) {
    if (d.token) { token = d.token; store.set('aw_token', token); }
    visitor = (d.name || '').split(' ')[0]; visitorPhone = d.phone || ''; visitorCity = d.city || '';
    el.hello.textContent = visitor ? 'Hello, ' + visitor + '!' : 'Hello there!';
    if (typeof d.left === 'number') leftHint(d.left);
    show('chat'); setTimeout(() => el.text.focus(), 350);
    const st = loadState(), restored = !!(st && st.items && st.items.length);
    if (restored && !items.length) restore(st);
    ready = true;
    // The spoken greeting plays once per visitor per day - never on re-open, refresh or when moving between pages.
    const today = new Date().toISOString().slice(0, 10);
    if (speakOn && !restored && store.get('aw_greeted') !== today) { store.set('aw_greeted', today); setTimeout(() => speak(ag.dataset.greet), 500); }
    if (!restored) store.set('aw_greeted', today);
  }
  const leftHint = n => { el.left.textContent = n > 0 ? n + ' message' + (n === 1 ? '' : 's') + ' left today' : 'Daily limit reached'; };

  /* ---------- step 1: lead form ---------- */
  const fLead = views.lead, eLead = $('#eLead');
  fLead.addEventListener('submit', async e => {
    e.preventDefault(); eLead.textContent = '';
    const f = new FormData(fLead), v = Object.fromEntries(f.entries());
    v.phone = (v.phone || '').replace(/\D/g, '').replace(/^91(?=\d{10}$)/, '');
    $$('.aw-f', fLead).forEach(x => x.classList.remove('bad'));
    const bad = (n, m) => { const i = fLead.elements[n]; i.closest('.aw-f').classList.add('bad'); i.focus(); eLead.textContent = m; return true; };
    if (!/^[\p{L}\s.'\-]{2,60}$/u.test((v.name || '').trim())) return bad('name', 'Please enter your name.');
    if (!/^[6-9]\d{9}$/.test(v.phone)) return bad('phone', 'Enter a valid 10-digit Indian mobile number.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test((v.email || '').trim())) return bad('email', 'Enter a valid email address.');
    const btn = $('button[type=submit]', fLead); btn.disabled = true;
    const r = await api(ag.dataset.lead, v).catch(() => ({ ok: false, data: { message: 'Connection problem. Please try again.' } }));
    btn.disabled = false;
    if (!r.ok) { eLead.textContent = firstError(r.data); return; }
    if (r.data.verified) return enter(r.data);
    $('#otpMail').textContent = r.data.email || v.email;
    show('otp'); startResend(60); $('#otpBoxes input').focus();
  });

  /* ---------- step 2: email OTP ---------- */
  const boxes = $$('#otpBoxes input'), eOtp = $('#eOtp'), resend = $('#otpResend');
  boxes.forEach((b, i) => {
    b.addEventListener('input', () => { b.value = b.value.replace(/\D/g, '').slice(-1); if (b.value && boxes[i + 1]) boxes[i + 1].focus(); if (boxes.every(x => x.value)) views.otp.requestSubmit(); });
    b.addEventListener('keydown', e => { if (e.key === 'Backspace' && !b.value && boxes[i - 1]) boxes[i - 1].focus(); });
    b.addEventListener('paste', e => { const t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (!t) return; e.preventDefault(); t.split('').forEach((c, j) => boxes[j] && (boxes[j].value = c)); (boxes[Math.min(t.length, 5)]).focus(); if (t.length === 6) views.otp.requestSubmit(); });
  });
  let rt = null;
  function startResend(sec) { clearInterval(rt); resend.disabled = true; const tick = () => { resend.textContent = sec > 0 ? 'Resend code in ' + sec + 's' : 'Resend code'; if (sec-- <= 0) { clearInterval(rt); resend.disabled = false; } }; tick(); rt = setInterval(tick, 1000); }
  views.otp.addEventListener('submit', async e => {
    e.preventDefault(); eOtp.textContent = '';
    const code = boxes.map(b => b.value).join(''); if (code.length < 6) { eOtp.textContent = 'Enter the 6-digit code.'; return; }
    const btn = $('button[type=submit]', views.otp); btn.disabled = true;
    const r = await api(ag.dataset.verify, { code }).catch(() => ({ ok: false, data: { message: 'Connection problem. Please try again.' } }));
    btn.disabled = false;
    if (!r.ok) {
      eOtp.textContent = firstError(r.data); const w = $('#otpBoxes'); w.classList.remove('shake'); void w.offsetWidth; w.classList.add('shake');
      boxes.forEach(b => b.value = ''); boxes[0].focus(); if (r.data.restart) show('lead'); return;
    }
    enter(r.data);
  });
  resend.addEventListener('click', async () => {
    const v = Object.fromEntries(new FormData(fLead).entries()); v.phone = (v.phone || '').replace(/\D/g, '');
    resend.disabled = true; const r = await api(ag.dataset.lead, v).catch(() => null);
    if (r && r.ok) { eOtp.textContent = ''; boxes.forEach(b => b.value = ''); boxes[0].focus(); startResend(60); } else { eOtp.textContent = r ? firstError(r.data) : 'Connection problem.'; startResend(r && r.data.retry_after || 30); }
  });
  $('#otpBack').addEventListener('click', () => show('lead'));

  /* ---------- chat: everything visible is kept in `items` (sessionStorage) so the conversation survives refresh and page changes ---------- */
  let items = [], visitorPhone = '', visitorCity = '';
  const SKEY = 'aw_state';
  let ready = false;   // do not overwrite the saved conversation before it has been restored
  const persist = () => { if (!ready) return; try { sessionStorage.setItem(SKEY, JSON.stringify({ open: ag.classList.contains('is-open'), items: items.slice(-40), history: history.slice(-6), sent, rated })); } catch (e) {} };
  const loadState = () => { try { return JSON.parse(sessionStorage.getItem(SKEY) || 'null'); } catch (e) { return null; } };
  const typing = () => { const d = document.createElement('div'); d.className = 'aw-msg bot aw-typing'; d.innerHTML = '<i></i><i></i><i></i>'; el.msgs.appendChild(d); down(); return d; };
  const isoDay = n => { const d = new Date(Date.now() + n * 864e5); return d.toISOString().slice(0, 10); };
  const label = { test_drive: 'Test drive', inspection: 'Inspection' };

  function push(it, animate) { items.push(it); render(it, animate); persist(); return it; }
  function restore(st) { items = st.items || []; history = st.history || []; sent = st.sent || 0; rated = !!st.rated; items.forEach((it, i) => render(it, false, i === items.length - 1)); }

  function render(it, animate, lastOnly) {
    el.hero.classList.add('gone');
    if (it.k === 'msg') return bubble(it, animate);
    if (it.k === 'links') return cards(it);
    if (it.k === 'quick') { $$('.aw-quick', el.msgs).forEach(n => n.remove()); if (lastOnly === undefined || lastOnly) return chips(it); return; }
    if (it.k === 'book') { if (!it.done) return bookCard(it); return; }
    if (it.k === 'act') return actionBtn(it);
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

  function cards(it) {
    const w = document.createElement('div'); w.className = 'aw-cars';
    it.links.forEach(l => {
      const img = l.image ? '<img src="' + esc(l.image) + '" alt="" loading="lazy">' : '';
      const body = img + '<span>' + esc(l.title) + (l.price ? '<small class="aw-price">' + esc(l.price) + '</small>' : '') + '</span><i class="ti ti-arrow-up-right"></i>';
      if (l.k && l.id) {
        const c = document.createElement('div'); c.className = 'aw-car'; c.dataset.k = l.k; c.dataset.id = l.id; c.dataset.t = l.title; c.dataset.p = l.price || ''; c.dataset.u = l.url; c.dataset.img = l.image || '';
        c.innerHTML = '<a class="aw-car-top" href="' + esc(l.url) + '" target="_blank" rel="noopener">' + body + '</a><div class="aw-car-act"><button type="button" data-a="select">Select</button><button type="button" data-a="test_drive">Test drive</button><button type="button" data-a="inspection">Inspection</button></div>';
        w.appendChild(c);
      } else { const a = document.createElement('a'); a.className = 'aw-link'; a.href = l.url; a.innerHTML = body; w.appendChild(a); }
    });
    el.msgs.appendChild(w); down();
  }

  function chips(it) {
    const w = document.createElement('div'); w.className = 'aw-quick';
    it.q.forEach(c => { const b = document.createElement('button'); b.type = 'button'; b.textContent = c.label; b.dataset.text = c.text; w.appendChild(b); });
    el.msgs.appendChild(w); down();
  }

  function actionBtn(it) {
    const a = document.createElement('a'); a.className = 'aw-go'; a.href = it.url; a.innerHTML = esc(it.label) + ' <i class="ti ti-arrow-right"></i>';
    a.addEventListener('click', () => { persist(); });
    el.msgs.appendChild(a); down();
  }

  /* booking card: creates a real test-drive / inspection lead on the server */
  function bookCard(it) {
    const car = it.car, who = it.lead || {};
    const n = document.createElement('div'); n.className = 'aw-book';
    n.innerHTML = '<b>' + label[it.kind] + ' - ' + esc(car.t) + '</b>'
      + '<label>Date<input type="date" name="date" min="' + isoDay(0) + '" max="' + isoDay(30) + '" value="' + isoDay(1) + '"></label>'
      + '<div class="aw-pills" data-n="slot"><label><input type="radio" name="slot" value="morning"><span>Morning<small>9-12</small></span></label><label><input type="radio" name="slot" value="afternoon" checked><span>Afternoon<small>12-4</small></span></label><label><input type="radio" name="slot" value="evening"><span>Evening<small>4-8</small></span></label></div>'
      + '<div class="aw-pills" data-n="place"><label><input type="radio" name="place" value="showroom" checked><span>At showroom</span></label><label><input type="radio" name="place" value="home"><span>At my address</span></label></div>'
      + '<input type="text" name="address" maxlength="200" placeholder="Your address" hidden>'
      + '<input type="text" name="note" maxlength="300" placeholder="Anything we should know? (optional)">'
      + '<div class="aw-err" role="alert"></div>'
      + '<button type="button" class="aw-btn"><span>Confirm ' + label[it.kind].toLowerCase() + '</span></button>'
      + '<small class="aw-fine">We will call ' + (who.phone ? '+91 ' + esc(who.phone) : 'you') + ' to confirm.</small>';
    const addr = n.querySelector('[name=address]'), err = n.querySelector('.aw-err'), btn = n.querySelector('.aw-btn');
    n.querySelectorAll('[name=place]').forEach(r => r.addEventListener('change', () => { addr.hidden = n.querySelector('[name=place]:checked').value !== 'home'; if (!addr.hidden) addr.focus(); }));
    btn.addEventListener('click', async () => {
      err.textContent = ''; btn.disabled = true;
      const f = k => (n.querySelector('[name=' + k + ']:checked') || n.querySelector('[name=' + k + ']')).value;
      const r = await api(ag.dataset.book, { kind: it.kind, k: car.k, id: car.id, date: f('date'), slot: f('slot'), place: f('place'), address: addr.value || null, note: n.querySelector('[name=note]').value || null }).catch(() => ({ ok: false, data: { message: 'Connection problem. Please try again.' } }));
      btn.disabled = false;
      if (!r.ok) { err.textContent = firstError(r.data); return; }
      it.done = true; n.remove();
      const d = r.data;
      history.push({ role: 'assistant', content: 'Booked ' + d.kind.toLowerCase() + ' for ' + d.car + ' on ' + d.when + ' (ref ' + d.ref + ')' });
      add('bot', '✅ ' + d.kind + ' booked for ' + d.car + ' on ' + d.when + '.\nReference: ' + d.ref + '. Our team will call you to confirm.', '', true);
      push({ k: 'quick', q: [{ label: 'Show similar cars', text: 'show me similar cars' }, { label: 'Book another car', text: 'show me more cars' }] });
    });
    el.msgs.appendChild(n); down();
  }

  /* card buttons: Select / Test drive / Inspection */
  el.msgs.addEventListener('click', async e => {
    const b = e.target.closest('.aw-car-act button'); if (!b) return;
    const c = b.closest('.aw-car'), car = { k: c.dataset.k, id: +c.dataset.id, t: c.dataset.t, p: c.dataset.p, u: c.dataset.u, img: c.dataset.img };
    $$('.aw-car.sel', el.msgs).forEach(x => x.classList.remove('sel')); c.classList.add('sel');
    const r = await api(ag.dataset.select, { k: car.k, id: car.id }).catch(() => null);
    if (!r || !r.ok) { add('err', (r && r.data.message) || 'That car is not available any more.'); return; }
    const a = b.dataset.a;
    if (a === 'select') {
      add('bot', 'Got it - ' + car.t + (car.p ? ' (' + car.p + ')' : '') + ' selected. Would you like a test drive or an inspection?');
      push({ k: 'quick', q: [{ label: 'Test drive', text: 'Test drive: ' + car.t }, { label: 'Inspection', text: 'Inspection: ' + car.t }, { label: 'Show more cars', text: 'show me more cars' }] });
    } else {
      $$('.aw-book', el.msgs).forEach(x => x.remove()); items.forEach(i => { if (i.k === 'book') i.done = true; });
      push({ k: 'book', kind: a, car, lead: { phone: visitorPhone, city: visitorCity } });
    }
  });
  el.msgs.addEventListener('click', e => { const q = e.target.closest('.aw-quick button'); if (q) { e.target.closest('.aw-quick').remove(); send(q.dataset.text); } });

  async function send(text, viaVoice) {
    text = (text || '').trim().slice(0, 300);
    if (!text || busy) return;
    if (Date.now() < cooldownUntil) { status('Please wait ' + Math.ceil((cooldownUntil - Date.now()) / 1000) + 's…'); return; }
    busy = true; ag.classList.add('busy'); el.form.classList.add('busy'); $$('.aw-quick', el.msgs).forEach(n => n.remove()); add('user', text); const t = typing(); status(viaVoice ? 'Thinking…' : '');
    let r;
    try { r = await api(ag.dataset.chat, { message: text, history: history.slice(-3), voice: !!(viaVoice || voiceMode) }); }
    catch (err) { r = { ok: false, status: 0, data: { message: 'Connection problem. Please try again.' } }; }
    t.remove(); busy = false; ag.classList.remove('busy'); status('');
    if (r.status === 401 && r.data.gate) { store.del('aw_token'); token = ''; show('lead'); return; }
    if (!r.ok) {
      if (r.data.retry_after) cooldownUntil = Date.now() + Math.min(r.data.retry_after, 60) * 1000;
      add('err', r.data.message || 'Something went wrong. Please try again.');
      if (['daily_messages', 'daily_tokens', 'lifetime_tokens', 'ip_tokens', 'blocked'].includes(r.data.reason)) { leftHint(0); el.text.disabled = true; }
      if (voiceMode) listen(); return;
    }
    const d = r.data; sent++;
    history.push({ role: 'user', content: text }, { role: 'assistant', content: d.answer.slice(0, 300) });
    add('bot', d.answer, d.source === 'kb' ? 'From our site' : '', true);
    if (d.links && d.links.length) push({ k: 'links', links: d.links });
    let nav = null;
    (d.actions || []).forEach(a => {
      if (a.type === 'book') push({ k: 'book', kind: a.kind, car: a.car, lead: a.lead });
      else if (a.type === 'link') push({ k: 'act', url: a.url, label: a.label });
      else if (a.type === 'navigate') { push({ k: 'act', url: a.url, label: a.label }); nav = a; }
    });
    if (d.quick && d.quick.length) push({ k: 'quick', q: d.quick });
    if (typeof d.left === 'number') leftHint(d.left);
    if (speakOn || viaVoice || voiceMode) await speak(d.answer);
    if (voiceMode) listen();
    if (nav && nav.auto) { persist(); setTimeout(() => { persist(); location.href = nav.url; }, reduce ? 300 : 1500); }   // open the real page; the chat comes along
  }
  el.form.addEventListener('submit', e => { e.preventDefault(); const v = el.text.value.trim(); if (v) { el.text.value = ''; send(v); } });
  $('#agSuggest').addEventListener('click', e => { const b = e.target.closest('button[data-q]'); if (b) send(b.dataset.q); });
  $('#agReset').addEventListener('click', () => {
    stopAll(); history = []; sent = 0; busy = false; items = []; try { sessionStorage.removeItem(SKEY); } catch (e) {}
    Array.from(el.msgs.children).forEach(n => { if (n !== el.hero) n.remove(); }); el.hero.classList.remove('gone'); el.text.value = ''; el.text.disabled = false;
    if (token) api(ag.dataset.reset, {}).catch(() => {});
    if (state !== 'chat' && token) show('chat'); else if (!token) { show('lead'); }
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
  function stopAudio() { if (audio) { audio.pause(); audio = null; } window.speechSynthesis && speechSynthesis.cancel(); }
  async function speak(text) {
    stopAudio(); status('Speaking…');
    const clean = text.replace(/\*\*/g, '').replace(/https?:\/\/\S+/g, '').slice(0, 600);
    const browser = async () => {
      if (!window.speechSynthesis) return;
      await new Promise(res => { const u = new SpeechSynthesisUtterance(clean); u.lang = 'en-IN'; u.rate = 1.02; u.onend = u.onerror = res; speechSynthesis.speak(u); });
    };
    try {
      const r = await fetch(ag.dataset.tts, { method: 'POST', headers: headers(), body: JSON.stringify({ text: clean }) });
      if (r.status === 200) {                                  // the voice saved in Settings
        const url = URL.createObjectURL(await r.blob()); audio = new Audio(url);
        await new Promise(res => { audio.onended = audio.onerror = res; audio.play().catch(res); });
        status(''); return;
      }
      if (r.status === 204) { await browser(); status(''); return; }   // no ElevenLabs key saved: browser voice is all there is
      let m = 'Voice is unavailable right now.'; try { m = (await r.json()).message || m; } catch (e) {}
      if (ag.dataset.fallback === '1') { await browser(); status(''); return; }
      status(m); setTimeout(() => status(''), 4000); return;    // stay silent rather than sound like a different voice
    } catch (e) {}
    if (ag.dataset.voice !== '1' || ag.dataset.fallback === '1') await browser();
    status('');
  }
  el.speak.addEventListener('click', () => { speakOn = !speakOn; store.set('ag_speak', speakOn ? '1' : '0'); paintSpeak(); if (!speakOn) stopAudio(); });

  /* ---------- voice in: browser speech recognition (free) ---------- */
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  function stopAll() { voiceMode = false; stopAudio(); try { rec && rec.abort(); } catch (e) {} el.mic.classList.remove('live'); status(''); }
  function listen() {
    if (!SR || !voiceMode) return;
    rec = new SR(); rec.lang = 'en-IN'; rec.interimResults = true; rec.maxAlternatives = 1;
    let finalText = '';
    rec.onstart = () => { el.mic.classList.add('live'); status('Listening… speak now'); };
    rec.onresult = e => { let s = ''; for (const r of e.results) s += r[0].transcript; finalText = s; el.text.value = s; };
    rec.onerror = e => { if (e.error === 'not-allowed') { alert('Please allow microphone access to talk to ' + name + '.'); stopAll(); } };
    rec.onend = () => {
      el.mic.classList.remove('live');
      const v = finalText.trim(); el.text.value = '';
      if (v && voiceMode) send(v, true);
      else if (voiceMode) { status("Didn't catch that — tap the mic to stop, or keep talking"); setTimeout(listen, 400); }
    };
    try { rec.start(); } catch (e) {}
  }
  el.mic.addEventListener('click', () => {
    if (!SR) { alert('Voice input is not supported in this browser. Please use Chrome, Edge or Safari, or type your question.'); return; }
    if (voiceMode) { stopAll(); return; }
    voiceMode = true; stopAudio(); listen();
  });

  /* ---------- page mode + deep links ---------- */
  if (page) { el.panel.classList.add('open'); ag.classList.add('is-open'); boot(); }
  else { const st = loadState(); if (st && st.open) setTimeout(open, 200); }   // came here from an assistant link: reopen with the conversation
  const ask = new URLSearchParams(location.search).get('ask');
  if (ask && page) setTimeout(() => window.openAssistant(ask), 300);
  document.querySelectorAll('[data-ask]').forEach(b => b.addEventListener('click', () => window.openAssistant(b.dataset.ask)));
})();
