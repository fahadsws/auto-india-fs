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
  const csrf = ($('meta[name=csrf-token]') || {}).content || '';
  const els = { panel: $('#agPanel'), msgs: $('#agMsgs'), form: $('#agForm'), text: $('#agText'), mic: $('#agMic'), status: $('#agStatus'), speak: $('#agSpeak'), fab: $('#agFab'), close: $('#agClose'), suggest: $('#agSuggest') };
  const name = ag.dataset.name, history = [];
  let speakOn = false, voiceMode = false, rec = null, audio = null, busy = false;
  try { speakOn = localStorage.getItem('ag_speak') === '1'; } catch (e) {}
  const paintSpeak = () => els.speak.innerHTML = '<i class="ti ti-volume' + (speakOn ? '' : '-off') + '"></i>';
  paintSpeak();

  const esc = s => s.replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const fmt = s => esc(s).replace(/\*\*(.+?)\*\*/g, '<b>$1</b>');
  const scroll = () => els.msgs.scrollTop = els.msgs.scrollHeight;
  const status = t => els.status.textContent = t || '';

  function add(role, text, extra) {
    const d = document.createElement('div'); d.className = 'ag-msg ' + (role === 'user' ? 'me' : 'bot');
    d.innerHTML = fmt(text) + (extra ? '<span class="ag-src">' + extra + '</span>' : '');
    els.msgs.appendChild(d); scroll(); return d;
  }
  function addLinks(links) {
    if (!links || !links.length) return;
    const w = document.createElement('div'); w.className = 'ag-links';
    links.forEach(l => {
      const a = document.createElement('a'); a.className = 'ag-link'; a.href = l.url;
      a.innerHTML = (l.image ? '<img src="' + esc(l.image) + '" alt="" loading="lazy">' : '') + '<span>' + esc(l.title) + '</span><i class="ti ti-arrow-right"></i>';
      w.appendChild(a);
    });
    els.msgs.appendChild(w); scroll();
  }
  function typing() {
    const d = document.createElement('div'); d.className = 'ag-msg bot ag-typing'; d.innerHTML = '<i></i><i></i><i></i>'; els.msgs.appendChild(d); scroll(); return d;
  }

  function open() { els.panel.classList.add('open'); els.fab && (els.fab.style.display = 'none'); if (!els.msgs.children.length) greet(); setTimeout(() => els.text.focus(), 50); }
  function close() { stopAll(); els.panel.classList.remove('open'); els.fab && (els.fab.style.display = ''); }
  function greet() {
    add('bot', "Hi! I'm " + name + " 👋 I can help you find a car, compare options or explain the latest news. What are you looking for today? You can type, or tap the mic and just talk to me.");
  }
  els.fab && els.fab.addEventListener('click', open);
  els.close && els.close.addEventListener('click', close);
  window.openAssistant = q => { open(); if (q) send(q); };
  if (ag.dataset.mode === 'page') greet();

  els.speak.addEventListener('click', () => {
    speakOn = !speakOn; try { localStorage.setItem('ag_speak', speakOn ? '1' : '0'); } catch (e) {} paintSpeak();
    if (!speakOn) stopAudio();
  });
  els.suggest.addEventListener('click', e => { if (e.target.tagName === 'BUTTON') send(e.target.textContent); });
  els.form.addEventListener('submit', e => { e.preventDefault(); const v = els.text.value.trim(); if (v) { els.text.value = ''; send(v); } });

  /* ---------- talking to the backend ---------- */
  async function send(text, viaVoice) {
    if (busy) return; busy = true;
    els.suggest.style.display = 'none';
    add('user', text); const t = typing(); status(viaVoice ? 'Thinking…' : '');
    let data;
    try {
      const r = await fetch(ag.dataset.chat, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ message: text, history: history.slice(-8), voice: !!(viaVoice || voiceMode) }) });
      if (!r.ok) throw new Error(r.status === 429 ? 'You are sending messages too fast. Give me a moment.' : 'Something went wrong.');
      data = await r.json();
    } catch (err) { t.remove(); add('bot', err.message || 'Connection problem. Please try again.'); busy = false; status(''); if (voiceMode) listen(); return; }
    t.remove();
    history.push({ role: 'user', content: text }, { role: 'assistant', content: data.answer });
    add('bot', data.answer, data.source === 'kb' ? 'From our site' : data.source === 'web' ? 'General knowledge — not from our site' : '');
    addLinks(data.links);
    busy = false; status('');
    if (speakOn || viaVoice || voiceMode) await speak(data.answer);
    if (voiceMode) listen();
  }

  /* ---------- voice out: ElevenLabs via our server, else the browser's own voice ---------- */
  function stopAudio() { if (audio) { audio.pause(); audio = null; } window.speechSynthesis && speechSynthesis.cancel(); }
  async function speak(text) {
    stopAudio(); status('Speaking…');
    const clean = text.replace(/\*\*/g, '').replace(/https?:\/\/\S+/g, '');
    try {
      const r = await fetch(ag.dataset.tts, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ text: clean }) });
      if (r.status === 200) {
        const url = URL.createObjectURL(await r.blob()); audio = new Audio(url);
        await new Promise(res => { audio.onended = audio.onerror = res; audio.play().catch(res); });
        status(''); return;
      }
    } catch (e) {}
    if (window.speechSynthesis) {
      await new Promise(res => {
        const u = new SpeechSynthesisUtterance(clean); u.lang = 'en-IN'; u.rate = 1.02; u.onend = u.onerror = res; speechSynthesis.speak(u);
      });
    }
    status('');
  }

  /* ---------- voice in: browser speech recognition (free) ---------- */
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  function stopAll() { voiceMode = false; stopAudio(); try { rec && rec.abort(); } catch (e) {} els.mic.classList.remove('live'); status(''); }
  function listen() {
    if (!SR || !voiceMode) return;
    rec = new SR(); rec.lang = 'en-IN'; rec.interimResults = true; rec.maxAlternatives = 1;
    let finalText = '';
    rec.onstart = () => { els.mic.classList.add('live'); status('Listening… speak now'); };
    rec.onresult = e => { let s = ''; for (const r of e.results) s += r[0].transcript; finalText = s; els.text.value = s; };
    rec.onerror = e => { if (e.error === 'not-allowed') { alert('Please allow microphone access to talk to ' + name + '.'); stopAll(); } };
    rec.onend = () => {
      els.mic.classList.remove('live');
      const v = finalText.trim(); els.text.value = '';
      if (v && voiceMode) send(v, true);
      else if (voiceMode) { status('Didn\'t catch that — tap the mic to stop, or keep talking'); setTimeout(listen, 400); }
    };
    try { rec.start(); } catch (e) {}
  }
  els.mic.addEventListener('click', () => {
    if (!SR) { alert('Voice input is not supported in this browser. Please use Chrome, Edge or Safari, or type your question.'); return; }
    if (voiceMode) { stopAll(); return; }
    voiceMode = true; stopAudio(); listen();
  });

  /* ---------- deep link: /assistant?ask=... or hero box ---------- */
  const ask = new URLSearchParams(location.search).get('ask');
  if (ask && ag.dataset.mode === 'page') setTimeout(() => send(ask), 300);
  document.querySelectorAll('[data-ask]').forEach(b => b.addEventListener('click', () => window.openAssistant(b.dataset.ask)));
})();
