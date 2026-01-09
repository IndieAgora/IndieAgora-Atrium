(function () {
  "use strict";

  const U = window.IA_DISCUSS_TOPIC_UTILS || {};
  const esc = U.esc || ((s) => String(s || ""));
  const qs  = U.qs  || ((sel, root) => (root || document).querySelector(sel));

  function isImageMime(m) { return /^image\//i.test(String(m || "")); }
  function isVideoMime(m) { return /^video\//i.test(String(m || "")); }

  function parseYouTubeId(url) {
    try {
      const u = new URL(url);
      if (u.hostname.includes("youtu.be")) return (u.pathname.replace(/^\/+/, "").split("/")[0] || null);
      if (u.hostname.includes("youtube.com")) return (u.searchParams.get("v") || null);
      return null;
    } catch (e) { return null; }
  }

  function parsePeerTubeUuid(url) {
    try {
      const u = new URL(url);
      const m = u.pathname.match(/\/videos\/watch\/([^\/\?\#]+)/i);
      return (m && m[1]) ? m[1] : null;
    } catch (e) { return null; }
  }

  function isLikelyVideoUrl(url) {
    const u = String(url || "").trim();
    if (!u) return false;
    const lu = u.toLowerCase();
    if (lu.includes("youtube.com/watch")) return true;
    if (lu.includes("youtu.be/")) return true;
    if (lu.includes("/videos/watch/")) return true;
    if (/\.(mp4|webm|mov)(\?|$)/i.test(lu)) return true;
    return false;
  }

  function buildVideoEmbedHtml(url) {
    const raw = String(url || "").trim();
    if (!raw) return "";

    if (/\.(mp4|webm|mov)(\?|$)/i.test(raw.toLowerCase())) {
      return `
        <div class="iad-att-media">
          <video class="iad-att-video" controls playsinline preload="metadata">
            <source src="${esc(raw)}" />
          </video>
        </div>
      `;
    }

    const yid = parseYouTubeId(raw);
    if (yid) {
      const embed = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(yid)}`;
      return `
        <div class="iad-att-media">
          <iframe class="iad-att-iframe" src="${esc(embed)}" title="Video"
            frameborder="0" loading="lazy" referrerpolicy="origin-when-cross-origin"
            allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
            allowfullscreen></iframe>
        </div>
      `;
    }

    const uuid = parsePeerTubeUuid(raw);
    if (uuid) {
      try {
        const u = new URL(raw);
        const embed = u.origin + "/videos/embed/" + encodeURIComponent(uuid);
        return `
          <div class="iad-att-media">
            <iframe class="iad-att-iframe" src="${esc(embed)}" title="Video"
              frameborder="0" loading="lazy" referrerpolicy="origin-when-cross-origin"
              allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen"
              allowfullscreen></iframe>
          </div>
        `;
      } catch (e) {}
    }

    return `
      <div class="iad-att-media">
        <iframe class="iad-att-iframe" src="${esc(raw)}" title="Video"
          frameborder="0" loading="lazy" referrerpolicy="origin-when-cross-origin"
          allowfullscreen></iframe>
      </div>
    `;
  }

  function attachmentPillsHTML(media) {
    const atts = (media && media.attachments) ? media.attachments : [];
    if (!atts || !atts.length) return "";

    const items = atts.map((a) => {
      const url = a && a.url ? String(a.url) : "";
      const filename = a && a.filename ? String(a.filename) : "attachment";
      return { url: url, label: filename };
    }).filter((x) => !!x.url);

    const count = items.length;
    if (!count) return "";

    // Single pill that opens a modal listing all attachments
    return `
      <div class="iad-attachrow">
        <button type="button" class="iad-attachpill" data-iad-open-attachments data-attachments-json="${esc(JSON.stringify(items))}">
          Attachments (${count})
        </button>
      </div>
    `;
  }

  function inlineMediaHTML(media) {
    const atts = (media && media.attachments) ? media.attachments : [];
    const blocks = [];

    if (atts && atts.length) {
      const vids = atts.filter((a) => isVideoMime(a && a.mime));
      const imgs = atts.filter((a) => isImageMime(a && a.mime));

      vids.slice(0, 4).forEach((v) => {
        const url = v && v.url ? String(v.url) : "";
        if (!url) return;
        blocks.push(`
          <div class="iad-att-media">
            <video class="iad-att-video" controls playsinline preload="metadata">
              <source src="${esc(url)}" />
            </video>
          </div>
        `);
      });

      imgs.slice(0, 8).forEach((im) => {
        const url = im && im.url ? String(im.url) : "";
        if (!url) return;
        const alt = im && im.filename ? String(im.filename) : "";
        blocks.push(`
          <div class="iad-att-media">
            <img class="iad-att-img" src="${esc(url)}" alt="${esc(alt)}" loading="lazy" />
          </div>
        `);
      });
    }

    const urls = [];
    const primary = (media && media.video_url) ? String(media.video_url) : "";
    if (primary) urls.push(primary);

    const urlList = (media && Array.isArray(media.urls)) ? media.urls : [];
    urlList.forEach((u) => {
      const s = String(u || "").trim();
      if (!s) return;
      if (primary && s === primary) return;
      urls.push(s);
    });

    const seen = new Set();
    urls.forEach((u) => {
      if (seen.has(u)) return;
      seen.add(u);
      if (!isLikelyVideoUrl(u)) return;
      blocks.push(buildVideoEmbedHtml(u));
    });

    if (!blocks.length) return "";
    return `<div class="iad-attwrap">${blocks.join("")}</div>`;
  }

  window.IA_DISCUSS_TOPIC_MEDIA = {
    inlineMediaHTML,
    attachmentPillsHTML
  };
})();
