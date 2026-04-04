  function mediaBlockHTML(item, view) {
    const media = (item && item.media) ? item.media : {};
    const urls = Array.isArray(media.urls) ? media.urls.filter(Boolean).map(String) : [];

    const videoUrl = media.video_url ? String(media.video_url) : "";
    const videoMeta = videoUrl ? buildVideoMeta(videoUrl) : null;

    const linkUrls = urls.filter((u) => u && u !== videoUrl);

    // In non-"new" feed views, we still show the first detected video (if any),
    // but keep the link strip/modal reserved for the main New feed.
    if (view !== "new") {
      if (!videoMeta) return "";
      const thumb = videoMeta && videoMeta.thumbUrl ? videoMeta.thumbUrl : "";
      const host = (function () {
        try { return new URL(videoMeta.url).hostname.replace(/^www\./, ""); }
        catch (e) { return ""; }
      })();
      return `
        <div class="iad-mediawrap">
          <div class="iad-media-row">
            <button
              type="button"
              class="iad-vthumb is-compact${videoMeta && videoMeta.isShort ? ' is-vertical' : ''}"
              data-iad-open-video
              data-video-url="${esc(videoMeta.url)}"
              aria-label="${videoMeta && videoMeta.isPlaylist ? 'Open playlist on YouTube' : 'Open video'}">
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
            <div class="iad-media-meta">
              <div class="iad-media-line"><span class="iad-media-tag">${videoMeta && videoMeta.isPlaylist ? 'playlist' : 'video'}</span><span class="iad-media-host">${esc(host || (videoMeta && videoMeta.isPlaylist ? "youtube.com" : "video"))}</span></div>
            </div>
          </div>
        </div>
      `;
    }

    if (!videoMeta && (!linkUrls || !linkUrls.length)) return "";

    const thumb = videoMeta && videoMeta.thumbUrl ? videoMeta.thumbUrl : "";

    const host = (function () {
      try { return new URL(videoMeta ? videoMeta.url : linkUrls[0]).hostname.replace(/^www\./, ""); }
      catch (e) { return ""; }
    })();

    function linkLabel(u) {
      try {
        const U = new URL(u);
        const h = U.hostname.replace(/^www\./, "");
        const p = (U.pathname && U.pathname !== "/") ? U.pathname.replace(/\/$/, "") : "";
        return (p && p.length <= 18) ? (h + p) : h;
      } catch (e) {
        return String(u || "link").slice(0, 24);
      }
    }

    const linkCount = linkUrls.length;

    return `
      <div class="iad-mediawrap">
        <div class="iad-media-row">
          ${videoMeta ? `
              <button
                type="button"
                class="iad-vthumb is-compact${videoMeta && videoMeta.isShort ? ' is-vertical' : ''}"
                data-iad-open-video
                data-video-url="${esc(videoMeta.url)}"
                aria-label="${videoMeta && videoMeta.isPlaylist ? 'Open playlist on YouTube' : 'Open video'}">
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
            ${videoMeta ? `<div class="iad-media-line"><span class="iad-media-tag">${videoMeta && videoMeta.isPlaylist ? 'playlist' : 'video'}</span><span class="iad-media-host">${esc(host || (videoMeta && videoMeta.isPlaylist ? "youtube.com" : "video"))}</span></div>` : ""}
            ${linkCount ? `
              <div class="iad-mediastrip">
                <button
                  type="button"
                  class="iad-pill is-muted"
                  data-iad-open-links
                  data-links-json="${esc(JSON.stringify(linkUrls))}">
                  Links (${linkCount})
                </button>
              </div>
            ` : ""}
          </div>
        </div>
      </div>
    `;
  }

  function attachmentPillsHTML(item) {
    const atts = (item.media && item.media.attachments) ? item.media.attachments : [];
    if (!atts || !atts.length) return "";

    const urls = atts.map((a) => (a && a.url ? String(a.url) : "")).filter(Boolean);
    const count = urls.length;

    // Single pill that opens a modal listing all attachments.
    return `
      <div class="iad-attachrow">
        <button
          type="button"
          class="iad-attachpill"
          data-iad-open-attachments
          data-attachments-json="${esc(JSON.stringify(urls))}">
          Attachments (${count})
        </button>
      </div>
    `;
  }

