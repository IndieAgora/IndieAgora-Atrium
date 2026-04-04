  function openVideoModal(meta, titleText) {
    if (!meta) return;
    if (meta.kind === "youtube-playlist") {
      const targetUrl = meta.openUrl || meta.url || "";
      if (targetUrl) window.open(targetUrl, "_blank", "noopener,noreferrer");
      return;
    }

    const m = ensureVideoModal();
    const title = m.querySelector("[data-iad-videomodal-title]");
    const body = m.querySelector("[data-iad-videomodal-body]");
    const openLink = m.querySelector("[data-iad-videomodal-open]");

    title.textContent = titleText ? titleText : "Video";
    if (openLink) openLink.href = meta.url;

    if (meta.kind === "file") {
      body.innerHTML = `
        <div class="iad-video-stage">
          <div class="iad-video-frame${meta.isShort ? ' is-vertical' : ''}">
            <video class="iad-video-el" controls playsinline>
              <source src="${esc(meta.embedUrl)}" />
            </video>
          </div>
        </div>
      `;
    } else {
      body.innerHTML = `
        <div class="iad-video-stage">
          <div class="iad-video-frame${meta.isShort ? ' is-vertical' : ''}">
            <iframe
              class="iad-video-iframe"
              src="${esc(meta.embedUrl)}"
              title="Video"
              frameborder="0"
              allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen"
              allowfullscreen
              referrerpolicy="origin-when-cross-origin"></iframe>
          </div>
        </div>
      `;
    }

    m.removeAttribute("hidden");
    lockPageScroll(true);
  }

  // -----------------------------
  // Attachment media helpers (ADDED)
  // -----------------------------
  function isImageAtt(a) {
    const mime = String((a && a.mime) || "").toLowerCase();
    const url = String((a && a.url) || "").toLowerCase();
    if (mime.startsWith("image/")) return true;
    return /\.(png|jpe?g|gif|webp|bmp|svg)(\?|#|$)/i.test(url);
  }

  function isVideoAtt(a) {
    const mime = String((a && a.mime) || "").toLowerCase();
    const url = String((a && a.url) || "").toLowerCase();
    if (mime.startsWith("video/")) return true;
    return /\.(mp4|webm|mov|m4v|ogg)(\?|#|$)/i.test(url);
  }

  function isAudioAtt(a) {
    const mime = String((a && a.mime) || "").toLowerCase();
    const url = String((a && a.url) || "").toLowerCase();
    if (mime.startsWith("audio/")) return true;
    return /\.(mp3|m4a|aac|wav|wave|flac|oga|ogg|opus|weba)(\?|#|$)/i.test(url);
  }

  function agoraPlayerHTML(att) {
    const src = att && att.url ? String(att.url) : "";
    if (!src) return "";
    const filename = att && att.filename ? String(att.filename) : "audio";
    const logo = (window.IA_DISCUSS && IA_DISCUSS.assets && IA_DISCUSS.assets.agoraPlayerLogo)
      ? String(IA_DISCUSS.assets.agoraPlayerLogo)
      : "";
    return `
      <div class="iad-att-media">
        <div class="iad-audio-player" data-audio-src="${esc(src)}" data-audio-title="${esc(filename)}">
          <div class="iad-ap-head">
            ${logo ? `<img class="iad-ap-logo" src="${esc(logo)}" alt="" />` : ""}
            <div class="iad-ap-brand">Agora Player</div>
            <div class="iad-ap-file">${esc(filename)}</div>
          </div>
          <div class="iad-ap-main">
            <button type="button" class="iad-ap-play" data-ap-play aria-label="Play/Pause">▶</button>
            <div class="iad-ap-wave" data-ap-wave aria-hidden="true"></div>
            <div class="iad-ap-time"><span data-ap-cur>0:00</span><span class="iad-ap-sep">/</span><span data-ap-dur>0:00</span></div>
          </div>
          <input class="iad-ap-seek" data-ap-seek type="range" min="0" max="100" value="0" step="0.1" aria-label="Seek" />
          <audio class="iad-ap-audio" preload="metadata" src="${esc(src)}"></audio>
        </div>
      </div>
    `;
  }

  function attachmentInlineMediaHTML(item) {
    // Show uploaded media inline WITHOUT inserting into body:
    // - first video (if present)
    // - first audio (if present)
    // - then first image (if present)
    const atts = (item && item.media && item.media.attachments) ? item.media.attachments : [];
    if (!atts || !atts.length) return "";

    let firstVideo = null;
    let firstAudio = null;
    let firstImage = null;

    for (const a of atts) {
      if (!firstVideo && isVideoAtt(a)) firstVideo = a;
      if (!firstAudio && isAudioAtt(a)) firstAudio = a;
      if (!firstImage && isImageAtt(a)) firstImage = a;
      if (firstVideo && firstImage) break;
    }

    if (!firstVideo && !firstAudio && !firstImage) return "";

    const parts = [];
    if (firstVideo && firstVideo.url) {
      parts.push(`
        <div class="iad-att-media">
          <video class="iad-att-video" controls playsinline preload="none">
            <source src="${esc(String(firstVideo.url))}" />
          </video>
        </div>
      `);
    }
    if (firstAudio && firstAudio.url) {
      parts.push(agoraPlayerHTML(firstAudio));
    }
    if (firstImage && firstImage.url) {
      parts.push(`
        <div class="iad-att-media">
          <img class="iad-att-img" src="${esc(String(firstImage.url))}" alt="" loading="lazy" decoding="async" />
        </div>
      `);
    }

    return parts.join("");
  }

  // -----------------------------
  // Media UI (New feed only)
  // -----------------------------
