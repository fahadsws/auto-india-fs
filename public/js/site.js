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
  }
  function close(force) {
    if (!force && sent >= 2 && !rated && state === 'chat') { stopAll(); show('fb'); return; }
    stopAll(); el.panel.classList.remove('open'); el.panel.classList.remove('big'); ag.classList.remove('is-open');
    if (state === 'fb') show('chat');
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
    visitor = (d.name || '').split(' ')[0];
    el.hello.textContent = visitor ? 'Hello, ' + visitor + '!' : 'Hello there!';
    if (typeof d.left === 'number') leftHint(d.left);
    show('chat'); setTimeout(() => el.text.focus(), 350);
    if (speakOn && !greeted) { greeted = true; setTimeout(() => speak(ag.dataset.greet), 500); }
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

  /* ---------- chat ---------- */
  function add(role, text, extra, animate) {
    el.hero.classList.add('gone');
    const d = document.createElement('div'); d.className = 'aw-msg ' + (role === 'user' ? 'me' : role === 'err' ? 'bot err' : 'bot');
    el.msgs.appendChild(d);
    const tail = extra ? '<span class="aw-src">' + esc(extra) + '</span>' : '';
    if (animate && !reduce && text.length < 700) {                       // word-by-word reveal
      const words = text.split(/(\s+)/); let i = 0, buf = '';
      const step = () => { buf += words.slice(i, i + 3).join(''); i += 3; d.innerHTML = fmt(buf); down(); if (i < words.length) setTimeout(step, 28); else d.innerHTML = fmt(text) + tail; };
      step();
    } else d.innerHTML = fmt(text) + tail;
    down(); return d;
  }
  function addLinks(links) {
    if (!links || !links.length) return;
    const w = document.createElement('div'); w.className = 'aw-links';
    links.forEach(l => { const a = document.createElement('a'); a.className = 'aw-link'; a.href = l.url;
      a.innerHTML = (l.image ? '<img src="' + esc(l.image) + '" alt="" loading="lazy">' : '') + '<span>' + esc(l.title) + '</span><i class="ti ti-arrow-right"></i>'; w.appendChild(a); });
    el.msgs.appendChild(w); down();
  }
  const typing = () => { const d = document.createElement('div'); d.className = 'aw-msg bot aw-typing'; d.innerHTML = '<i></i><i></i><i></i>'; el.msgs.appendChild(d); down(); return d; };

  async function send(text, viaVoice) {
    text = (text || '').trim().slice(0, 300);
    if (!text || busy) return;
    if (Date.now() < cooldownUntil) { status('Please wait ' + Math.ceil((cooldownUntil - Date.now()) / 1000) + 's…'); return; }
    busy = true; ag.classList.add('busy'); el.form.classList.add('busy'); add('user', text); const t = typing(); status(viaVoice ? 'Thinking…' : '');
    let r;
    try { r = await api(ag.dataset.chat, { message: text, history: history.slice(-4), voice: !!(viaVoice || voiceMode) }); }
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
    history.push({ role: 'user', content: text }, { role: 'assistant', content: d.answer.slice(0, 400) });
    add('bot', d.answer, d.source === 'kb' ? 'From our site' : '', true);
    addLinks(d.links); if (typeof d.left === 'number') leftHint(d.left);
    if (speakOn || viaVoice || voiceMode) await speak(d.answer);
    if (voiceMode) listen();
  }
  el.form.addEventListener('submit', e => { e.preventDefault(); const v = el.text.value.trim(); if (v) { el.text.value = ''; send(v); } });
  $('#agSuggest').addEventListener('click', e => { const b = e.target.closest('button[data-q]'); if (b) send(b.dataset.q); });
  $('#agReset').addEventListener('click', () => {
    stopAll(); history = []; sent = 0; busy = false; $$('.aw-msg, .aw-links', el.msgs).forEach(n => n.remove()); el.hero.classList.remove('gone'); el.text.value = '';
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
  const ask = new URLSearchParams(location.search).get('ask');
  if (ask && page) setTimeout(() => window.openAssistant(ask), 300);
  document.querySelectorAll('[data-ask]').forEach(b => b.addEventListener('click', () => window.openAssistant(b.dataset.ask)));
})();
