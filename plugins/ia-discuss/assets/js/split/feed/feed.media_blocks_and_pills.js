"use strict";
        </div>
      `);
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
  function mediaBlockHTML(item, view) {
    if (view !== "new") return "";

    var media = (item && item.media) ? item.media : {};
    var urls = Array.isArray(media.urls) ? media.urls.filter(Boolean).map(String) : [];

    var videoUrl = media.video_url ? String(media.video_url) : "";
    var videoMeta = videoUrl ? buildVideoMeta(videoUrl) : null;

    var linkUrls = urls.filter((u) => u && u !== videoUrl);

    if (!videoMeta && (!linkUrls || !linkUrls.length)) return "";

    var thumb = videoMeta && videoMeta.thumbUrl ? videoMeta.thumbUrl : "";

    var host = (function () {
      try { return new URL(videoMeta ? videoMeta.url : linkUrls[0]).hostname.replace(/^www\./, ""); }
      catch (e) { return ""; }
    })();

    return `
      <div class="iad-mediawrap">
        <div class="iad-media-row">
          ${videoMeta ? `
            <button
              type="button"
              class="iad-vthumb is-compact"
              data-iad-open-video
              data-video-url="${esc(videoMeta.url)}"
              aria-label="Open video">
              <div class="iad-vthumb-inner">
                ${thumb
                  ? `<img class="iad-vthumb-img" src="${esc(thumb)}" alt="" loading="lazy" />`
                  : `<div class="iad-vthumb-fallback"></div>`
                }
                <div class="iad-vthumb-overlay">
                  <span class="iad-vthumb-play">▶</span>
                </div>
              </div>
            </button>
          ` : ""}

          <div class="iad-media-meta">
            ${videoMeta ? `<div class="iad-media-line"><span class="iad-media-tag">video</span><span class="iad-media-host">${esc(host || "video")}</span></div>` : ""}
            ${linkUrls.length ? `
              <div class="iad-mediastrip">
                <button
                  type="button"
                  class="iad-pill is-muted"
                  data-iad-open-links
                  data-links="${esc(JSON.stringify(linkUrls))}">
                  Links (${linkUrls.length})
                </button>
              </div>
            ` : ""}
          </div>
        </div>
      </div>
    `;
  }

  function attachmentPillsHTML(item) {
    var atts = (item.media && item.media.attachments) ? item.media.attachments : [];
    if (!atts || !atts.length) return "";

    var pills = atts.slice(0, 4).map((a) => {
      var url = a.url ? String(a.url) : "";
      var filename = a.filename ? String(a.filename) : "attachment";
      if (!url) {
        return `<span class="iad-attachpill is-error" title="Missing URL">${esc(filename)}</span>`;
      }
      return `
        <a
;
