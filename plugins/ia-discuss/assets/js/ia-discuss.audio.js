(function(){
  "use strict";

  const D = document;
  const qs  = (sel, root) => (root || D).querySelector(sel);
  const qsa = (sel, root) => Array.from((root || D).querySelectorAll(sel));

  function fmtTime(sec){
    sec = Math.max(0, Number(sec || 0));
    if (!isFinite(sec)) sec = 0;
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    return m + ":" + String(s).padStart(2, "0");
  }

  function ensureWaves(player){
    const wave = qs('[data-ap-wave]', player);
    if (!wave) return;
    if (wave.__iadWavesBuilt) return;
    wave.__iadWavesBuilt = true;

    const bars = 34;
    const frag = D.createDocumentFragment();
    for (let i = 0; i < bars; i++){
      const b = D.createElement('span');
      b.className = 'iad-ap-bar';
      // stable pseudo-random per instance for a "waveform" look
      const h = 0.35 + (Math.sin((i + 1) * 1.7) + 1) * 0.25 + (Math.cos((i + 1) * 0.9) + 1) * 0.08;
      const d = (i % 9) * 0.03;
      b.style.setProperty('--h', String(Math.max(0.2, Math.min(1.0, h))));
      b.style.setProperty('--d', d + 's');
      frag.appendChild(b);
    }
    wave.appendChild(frag);
  }

  function pauseOthers(exceptAudio){
    try {
      qsa('.iad-audio-player .iad-ap-audio').forEach((a) => {
        if (a && a !== exceptAudio && !a.paused) a.pause();
      });
    } catch (e) {}
  }

  function initPlayer(player){
    if (!player || player.__iadAudioInit) return;
    player.__iadAudioInit = true;

    const audio = qs('.iad-ap-audio', player);
    const btn   = qs('[data-ap-play]', player);
    const seek  = qs('[data-ap-seek]', player);
    const elCur = qs('[data-ap-cur]', player);
    const elDur = qs('[data-ap-dur]', player);

    ensureWaves(player);

    if (!audio) return;

    function syncPlayState(){
      const playing = !audio.paused && !audio.ended;
      player.classList.toggle('is-playing', playing);
      if (btn) btn.textContent = playing ? '❚❚' : '▶';
    }

    function syncTime(){
      if (elCur) elCur.textContent = fmtTime(audio.currentTime || 0);
      if (elDur) elDur.textContent = fmtTime(audio.duration || 0);
      if (seek && audio.duration && isFinite(audio.duration)){
        const pct = (audio.currentTime / audio.duration) * 100;
        if (isFinite(pct)) seek.value = String(Math.max(0, Math.min(100, pct)));
      }
    }

    audio.addEventListener('loadedmetadata', syncTime);
    audio.addEventListener('timeupdate', syncTime);
    audio.addEventListener('play', () => { pauseOthers(audio); syncPlayState(); });
    audio.addEventListener('pause', syncPlayState);
    audio.addEventListener('ended', () => { syncPlayState(); syncTime(); });

    if (btn){
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        if (audio.paused) {
          pauseOthers(audio);
          audio.play().catch(() => {});
        } else {
          audio.pause();
        }
      });
    }

    if (seek){
      let seeking = false;
      const onSeek = () => {
        if (!audio.duration || !isFinite(audio.duration)) return;
        const pct = Number(seek.value || 0) / 100;
        audio.currentTime = Math.max(0, Math.min(audio.duration, pct * audio.duration));
      };

      seek.addEventListener('input', () => {
        seeking = true;
        onSeek();
      });
      seek.addEventListener('change', () => {
        seeking = false;
        onSeek();
      });

      // Prevent scroll-jank on mobile while scrubbing
      seek.addEventListener('touchstart', () => { seeking = true; }, { passive: true });
      seek.addEventListener('touchend', () => { seeking = false; }, { passive: true });
    }

    // Initial paint
    syncPlayState();
    syncTime();
  }

  function init(root){
    const r = root || D;
    qsa('.iad-audio-player', r).forEach(initPlayer);
  }

  // SPA-safe: init on mutations + key IA Discuss events
  function boot(){
    init(D);

    try {
      const mo = new MutationObserver((muts) => {
        for (const m of muts){
          for (const n of Array.from(m.addedNodes || [])){
            if (!n || n.nodeType !== 1) continue;
            if (n.matches && n.matches('.iad-audio-player')) initPlayer(n);
            else if (n.querySelector) init(n);
          }
        }
      });
      mo.observe(D.body || D.documentElement, { childList: true, subtree: true });
    } catch (e) {}

    // When IA Discuss appends feed pages or opens topics, ensure init runs.
    try {
      window.addEventListener('iad:feed_loaded', (e) => { init(D); });
      window.addEventListener('iad:feed_page_appended', (e) => {
        const root = e && e.detail && e.detail.root ? e.detail.root : null;
        init(root || D);
      });
      window.addEventListener('iad:open_topic_page', () => { setTimeout(() => init(D), 0); });
    } catch (e) {}
  }

  if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', boot);
  else boot();

  window.IA_DISCUSS_AUDIO = { init };
})();
