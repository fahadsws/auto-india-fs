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
  let speakOn = false, voiceMode = false, rec = null, audio = null, busy = false, cooldownUntil = 0, lang = 'en', cid = '', flushVoice = null;
  const store = { get: k => { try { return localStorage.getItem(k) } catch (e) { return null } }, set: (k, v) => { try { localStorage.setItem(k, v) } catch (e) {} }, del: k => { try { localStorage.removeItem(k) } catch (e) {} } };
  token = store.get('aw_token') || '';
  const savedSpeak = store.get('ag_speak'); speakOn = savedSpeak === null ? ag.dataset.voice === '1' : savedSpeak === '1';
  const paintSpeak = () => { el.speak.innerHTML = '<i class="ti ti-volume' + (speakOn ? '' : '-off') + '"></i>'; el.speak.classList.toggle('on', speakOn); };
  paintSpeak();
  // Widget text is English; the assistant itself mirrors the visitor's language (English / Hindi / Hinglish).
  const T = { en: { left: n => n > 0 ? n + ' message' + (n === 1 ? '' : 's') + ' left today' : 'Daily limit reached', resend: s => s > 0 ? 'Resend code in ' + s + 's' : 'Resend code', conn: 'Connection problem. Please try again.', wrong: 'Something went wrong. Please try again.', wait: 'Please wait ', sec: 's…', think: 'Thinking…', speaking: 'Speaking…', listening: "Listening… take your time, I'll send when you pause (or tap the mic)", nocatch: "Didn't catch that — tap the mic to stop, or keep talking", mic: 'Please allow microphone access to talk to ' + name + '.', nosr: 'Voice input is not supported in this browser. Please use Chrome, Edge or Safari, or type your question.', hello: 'Hello', hello2: 'Hello there!', novoice: 'Voice is unavailable right now.', tap: 'Tap the mic or speaker to enable voice', resume: 'Tap the mic to continue talking', code6: 'Enter the 6-digit code.', name: 'Please enter your name.', phone: 'Enter a valid 10-digit Indian mobile number.', email: 'Enter a valid email address.', src: 'From our site' } };
  const tr = k => T.en[k];

  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const fmt = s => esc(s).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/(https?:\/\/[^\s<)]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>').replace(/\n/g, '<br>');
  const down = () => { el.msgs.scrollTop = el.msgs.scrollHeight; };
  const status = t => el.status.textContent = t || '';
  const show = v => { state = v; Object.entries(views).forEach(([k, n]) => n.classList.toggle('show', k === v)); };
  const headers = () => ({ 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, ...(token ? { 'X-Assistant-Token': token } : {}) });
  const api = async (url, body) => {
    const r = await fetch(url, { method: body ? 'POST' : 'GET', headers: headers(), credentials: 'same-origin', body: body ? JSON.stringify(body) : undefined });
    let data = {}; try { data = await r.json(); } catch (e) {}
    return { ok: r.ok, status: r.status, data };
  };
  const firstError = d => (d.errors && Object.values(d.errors)[0] && Object.values(d.errors)[0][0]) || d.message || tr('wrong');

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
    if (r && r.ok && r.data.verified) { enter(r.data); }
    else if (r && r.ok && r.data.gate) { store.del('aw_token'); token = ''; show('lead'); }
    else if (r && r.ok) { enter(r.data); }
    else { show('lead'); }
  }
  function enter(d) {
    if (d.token) { token = d.token; store.set('aw_token', token); }
    visitor = (d.name || '').split(' ')[0]; visitorPhone = d.phone || ''; visitorCity = d.city || '';
    el.hello.textContent = visitor ? tr('hello') + ', ' + visitor + '!' : tr('hello2');
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
  }
  let lastLeft = null;
  const leftHint = n => { lastLeft = n; el.left.textContent = tr('left')(n); };

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
    const r = await api(ag.dataset.lead, v).catch(() => ({ ok: false, data: { message: tr('conn') } }));
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
  function startResend(sec) { clearInterval(rt); resend.disabled = true; const tick = () => { resend.textContent = tr('resend')(sec); if (sec-- <= 0) { clearInterval(rt); resend.disabled = false; } }; tick(); rt = setInterval(tick, 1000); }
  views.otp.addEventListener('submit', async e => {
    e.preventDefault(); eOtp.textContent = '';
    const code = boxes.map(b => b.value).join(''); if (code.length < 6) { eOtp.textContent = tr('code6'); return; }
    const btn = $('button[type=submit]', views.otp); btn.disabled = true;
    const r = await api(ag.dataset.verify, { code }).catch(() => ({ ok: false, data: { message: tr('conn') } }));
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
    if (r && r.ok) { eOtp.textContent = ''; boxes.forEach(b => b.value = ''); boxes[0].focus(); startResend(60); } else { eOtp.textContent = r ? firstError(r.data) : tr('conn'); startResend(r && r.data.retry_after || 30); }
  });
  $('#otpBack').addEventListener('click', () => show('lead'));

  /* ---------- chat: everything visible is kept in `items` (sessionStorage) so the conversation survives refresh and page changes ---------- */
  let items = [], visitorPhone = '', visitorCity = '';
  const SKEY = 'aw_state', CKEY = 'aw_cid';
  // One id per conversation. The server keeps its memory under this id, so a page refresh or the new-chat button always
  // starts a clean conversation (nothing from the old one can leak back), while moving between pages keeps the same chat.
  const newCid = () => Math.random().toString(36).slice(2, 12) + Date.now().toString(36);
  const nav0 = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};
  if (nav0.type === 'reload') { try { sessionStorage.removeItem(SKEY); sessionStorage.removeItem(CKEY); } catch (e) {} }
  try { cid = sessionStorage.getItem(CKEY) || ''; } catch (e) {}
  if (!cid) { cid = newCid(); try { sessionStorage.setItem(CKEY, cid); } catch (e) {} }
  let ready = false;   // do not overwrite the saved conversation before it has been restored
  const persist = () => { if (!ready) return; try { sessionStorage.setItem(SKEY, JSON.stringify({ open: ag.classList.contains('is-open'), items: items.slice(-40), history: history.slice(-6), sent, rated, voice: voiceMode, lang })); } catch (e) {} };
  const loadState = () => { try { return JSON.parse(sessionStorage.getItem(SKEY) || 'null'); } catch (e) { return null; } };
  const typing = () => { const d = document.createElement('div'); d.className = 'aw-msg bot aw-typing'; d.innerHTML = '<i></i><i></i><i></i>'; el.msgs.appendChild(d); down(); return d; };

  function push(it, animate) { items.push(it); render(it, animate); persist(); return it; }
  function restore(st) { items = (st.items || []).filter(i => ['msg', 'links', 'act'].includes(i.k)); history = st.history || []; sent = st.sent || 0; rated = !!st.rated; if (st.lang) lang = st.lang; items.forEach(it => render(it, false)); }

  function render(it, animate) {
    el.hero.classList.add('gone');
    if (it.k === 'msg') return bubble(it, animate);
    if (it.k === 'links') return cards(it);
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

  async function send(text, viaVoice) {
    text = (text || '').trim().slice(0, 300);
    if (!text || busy) return;
    if (Date.now() < cooldownUntil) { status(tr('wait') + Math.ceil((cooldownUntil - Date.now()) / 1000) + tr('sec')); return; }
    busy = true; ag.classList.add('busy'); el.form.classList.add('busy'); add('user', text); const t = typing(); status(viaVoice ? tr('think') : '');
    let r;
    try { r = await api(ag.dataset.chat, { message: text, history: history.slice(-6), voice: !!(viaVoice || voiceMode), cid }); }
    catch (err) { r = { ok: false, status: 0, data: { message: tr('conn') } }; }
    t.remove(); busy = false; ag.classList.remove('busy'); status('');
    if (r.status === 401 && r.data.gate) { store.del('aw_token'); token = ''; show('lead'); return; }
    if (!r.ok) {
      if (r.data.retry_after) cooldownUntil = Date.now() + Math.min(r.data.retry_after, 60) * 1000;
      add('err', r.data.message || tr('wrong'));
      if (['daily_messages', 'daily_tokens', 'lifetime_tokens', 'ip_tokens', 'blocked'].includes(r.data.reason)) { leftHint(0); el.text.disabled = true; }
      if (voiceMode) listen(); return;
    }
    const d = r.data; sent++;
    if (d.lang === 'hi' || d.lang === 'en') { lang = d.lang; if (lastLeft !== null) leftHint(lastLeft); }
    history.push({ role: 'user', content: text }, { role: 'assistant', content: d.answer.slice(0, 300) });
    add('bot', d.answer, d.source === 'kb' ? tr('src') : '', true);
    if (d.links && d.links.length) push({ k: 'links', links: d.links });
    let nav = null;
    (d.actions || []).forEach(a => {
      if (a.type === 'link') push({ k: 'act', url: a.url, label: a.label });
      else if (a.type === 'navigate') { push({ k: 'act', url: a.url, label: a.label }); nav = a; }
    });
    if (typeof d.left === 'number') leftHint(d.left);
    if (speakOn || viaVoice || voiceMode) await speak(d.answer);
    if (voiceMode) listen();
    if (nav && nav.auto) { persist(); setTimeout(() => { persist(); location.href = nav.url; }, reduce ? 300 : 1500); }   // open the real page; the chat comes along
  }
  el.form.addEventListener('submit', e => { e.preventDefault(); const v = el.text.value.trim(); if (v) { el.text.value = ''; send(v); } });
  $('#agSuggest').addEventListener('click', e => { const b = e.target.closest('button[data-q]'); if (b) send(b.dataset.q); });

  /* New chat: clear the conversation but keep the voice working - the assistant greets again and, if the visitor was
     talking by voice, goes straight back to listening. (Before, a refresh left the voice silent until the page was reloaded.) */
  $('#agReset').addEventListener('click', () => {
    const wasVoice = voiceMode;
    stopAll(); history = []; sent = 0; busy = false; items = []; try { sessionStorage.removeItem(SKEY); } catch (e) {}
    Array.from(el.msgs.children).forEach(n => { if (n !== el.hero) n.remove(); }); el.hero.classList.remove('gone'); el.text.value = ''; el.text.disabled = false; el.mic.classList.remove('resume');
    cid = newCid(); try { sessionStorage.setItem(CKEY, cid); } catch (e) {}
    if (token) api(ag.dataset.reset, {}).catch(() => {});
    if (state !== 'chat' && token) show('chat'); else if (!token) { show('lead'); return; }
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

  /* ---------- voice in: browser speech recognition (free), Hindi by default ---------- */
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  function stopAll() { voiceMode = false; flushVoice = null; stopAudio(); try { rec && rec.abort(); } catch (e) {} rec = null; el.mic.classList.remove('live'); status(''); }
  /* The visitor is NOT cut off mid-sentence: recognition keeps running, the words appear in the box as they speak, and the message is
     only sent after a real pause (SILENCE_MS with no new words), or when they tap the mic again to send right away. */
  const SILENCE_MS = 2200;
  function listen() {
    if (!SR || !voiceMode) return;
    try { rec && rec.abort(); } catch (e) {}
    const r = rec = new SR(); r.lang = lang === 'hi' ? 'hi-IN' : 'en-IN'; r.continuous = true; r.interimResults = true; r.maxAlternatives = 1;
    let text = '', timer = null, done = false;
    const finish = () => {                                       // the visitor paused long enough (or tapped): send what they said
      clearTimeout(timer); if (done) return;
      const v = text.trim(); if (!v) return;
      done = true; rec = null; flushVoice = null; try { r.abort(); } catch (e) {}
      el.mic.classList.remove('live'); el.text.value = ''; status('');
      if (voiceMode) send(v, true);
    };
    flushVoice = finish;
    r.onstart = () => { el.mic.classList.add('live'); status(tr('listening')); };
    r.onresult = e => {
      let s = ''; for (const x of e.results) s += x[0].transcript + ' ';
      text = s; el.text.value = s.trim();
      clearTimeout(timer); timer = setTimeout(finish, SILENCE_MS);      // every new word restarts the pause timer
    };
    r.onerror = e => { if (e.error === 'not-allowed') { alert(tr('mic')); stopAll(); } };
    r.onend = () => {
      if (rec !== r || done) return;                             // an older recogniser that was replaced or already sent
      clearTimeout(timer);
      if (text.trim()) finish();                                 // the browser ended the session: send what we have
      else { el.mic.classList.remove('live'); if (voiceMode) { status(tr('nocatch')); setTimeout(() => { if (voiceMode && !busy && rec === r) listen(); }, 400); } }
    };
    try { r.start(); } catch (e) {}
  }
  el.mic.addEventListener('click', () => {
    if (!SR) { alert(tr('nosr')); return; }
    if (voiceMode) {
      if (flushVoice && el.text.value.trim()) { flushVoice(); return; }          // tap while talking = "send now"
      if (audio || (window.speechSynthesis && speechSynthesis.speaking)) { stopAudio(); return; }   // tap while the assistant speaks = interrupt it
      stopAll(); return;
    }
    el.mic.classList.remove('resume'); voiceMode = true; stopAudio(); listen();
  });

  /* ---------- page mode + deep links ---------- */
  if (page) { el.panel.classList.add('open'); ag.classList.add('is-open'); boot(); }
  else { const st = loadState(); if (st && st.open) setTimeout(open, 200); }   // came here from an assistant link: reopen with the conversation
  const ask = new URLSearchParams(location.search).get('ask');
  if (ask && page) setTimeout(() => window.openAssistant(ask), 300);
  document.querySelectorAll('[data-ask]').forEach(b => b.addEventListener('click', () => window.openAssistant(b.dataset.ask)));
})();
