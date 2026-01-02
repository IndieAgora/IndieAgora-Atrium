"use strict";
      return { kind: "file", url, embedUrl: url, thumbUrl: "" };
    }

    return { kind: "iframe", url, embedUrl: url, thumbUrl: "" };
  }

  function openVideoModal(meta, titleText) {
    if (!meta) return;

    var m = ensureVideoModal();
    var title = m.querySelector("[data-iad-videomodal-title]");
    var body = m.querySelector("[data-iad-videomodal-body]");
    var openLink = m.querySelector("[data-iad-videomodal-open]");

    title.textContent = titleText ? titleText : "Video";
    if (openLink) openLink.href = meta.url;

    if (meta.kind === "file") {
      body.innerHTML = `
        <div class="iad-video-stage">
          <div class="iad-video-frame">
            <video class="iad-video-el" controls playsinline>
              <source src="${esc(meta.embedUrl)}" />
            </video>
          </div>
        </div>
      `;
    } else {
      body.innerHTML = `
        <div class="iad-video-stage">
          <div class="iad-video-frame">
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
    var mime = String((a && a.mime) || "").toLowerCase();
    var url = String((a && a.url) || "").toLowerCase();
    if (mime.startsWith("image/")) return true;
    return /\.(png|jpe?g|gif|webp|bmp|svg)(\?|#|$)/i.test(url);
  }

  function isVideoAtt(a) {
    var mime = String((a && a.mime) || "").toLowerCase();
    var url = String((a && a.url) || "").toLowerCase();
    if (mime.startsWith("video/")) return true;
    return /\.(mp4|webm|mov|m4v|ogg)(\?|#|$)/i.test(url);
  }

  function attachmentInlineMediaHTML(item) {
    // Show uploaded media inline WITHOUT inserting into body:
    // - first video (if present)
    // - then first image (if present)
    var atts = (item && item.media && item.media.attachments) ? item.media.attachments : [];
    if (!atts || !atts.length) return "";

    var firstVideo = null;
    var firstImage = null;

    for (var a of atts) {
      if (!firstVideo && isVideoAtt(a)) firstVideo = a;
      if (!firstImage && isImageAtt(a)) firstImage = a;
      if (firstVideo && firstImage) break;
    }

    if (!firstVideo && !firstImage) return "";

    var parts = [];
    if (firstVideo && firstVideo.url) {
      parts.push(`
        <div class="iad-att-media">
          <video class="iad-att-video" controls playsinline preload="none">
            <source src="${esc(String(firstVideo.url))}" />
          </video>
;
