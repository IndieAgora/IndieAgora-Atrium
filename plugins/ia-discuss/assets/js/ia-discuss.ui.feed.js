(function () {
  "use strict";
  const CORE = window.IA_DISCUSS_CORE || {};
  const esc = CORE.esc || function (s) {
    return String(s || "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  };
  const timeAgo = CORE.timeAgo || function () { return ""; };

  const API = window.IA_DISCUSS_API;
  const STATE = window.IA_DISCUSS_STATE;

  function currentUserId() {
    // If your localized IA_DISCUSS includes userId later, we’ll pick it up.
    // Otherwise edit buttons just won’t show.
    try {
      const v = (window.IA_DISCUSS && (IA_DISCUSS.userId || IA_DISCUSS.user_id || IA_DISCUSS.wpUserId)) || 0;
      return parseInt(v, 10) || 0;
    } catch (e) {
      return 0;
    }
  }

  function makeTopicUrl(topicId, postId) {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set("iad_topic", String(topicId));
      if (postId) u.searchParams.set("iad_post", String(postId));
      else u.searchParams.delete("iad_post");
      return u.toString();
    } catch (e) {
      const base = String(window.location.origin || "") + String(window.location.pathname || "");
      let out = base + "?iad_topic=" + encodeURIComponent(String(topicId || ""));
      if (postId) out += "&iad_post=" + encodeURIComponent(String(postId || ""));
      return out;
    }
  }

  async function copyToClipboard(text) {
    const t = String(text || "");
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(t);
        return true;
      }
    } catch (e) {}
    try {
      const ta = document.createElement("textarea");
      ta.value = t;
      ta.setAttribute("readonly", "");
      ta.style.position = "fixed";
      ta.style.top = "-9999px";
      document.body.appendChild(ta);
      ta.select();
      document.execCommand("copy");
      ta.remove();
      return true;
    } catch (e) {}
    return false;
  }

  function openConnectProfile(payload) {
    const p = payload || {};
    const username = (p.username || "").trim();
    const user_id = parseInt(p.user_id || "0", 10) || 0;

    try {
      localStorage.setItem("ia_connect_last_profile", JSON.stringify({
        username,
        user_id,
        ts: Math.floor(Date.now() / 1000)
      }));
    } catch (e) {}

    try {
      window.dispatchEvent(new CustomEvent("ia:open_profile", { detail: { username, user_id } }));
    } catch (e) {}

    const tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  // -----------------------------
  // Icons (inline SVG)
  // -----------------------------
  function ico(name) {
    const common = `width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"`;
    if (name === "reply") return `<svg ${common}><path d="M9 17l-4-4 4-4"/><path d="M20 19v-2a4 4 0 0 0-4-4H5"/></svg>`;
    if (name === "link")  return `<svg ${common}><path d="M10 13a5 5 0 0 1 0-7l1-1a5 5 0 0 1 7 7l-1 1"/><path d="M14 11a5 5 0 0 1 0 7l-1 1a5 5 0 0 1-7-7l1-1"/></svg>`;
    if (name === "share") return `<svg ${common}><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v13"/></svg>`;
    if (name === "edit")  return `<svg ${common}><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>`;
    if (name === "dots")  return `<svg ${common}><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>`;
    return "";
  }

  // -----------------------------
  // Links modal (New feed only)
  // -----------------------------
  function ensureLinksModal() {
    let m = document.querySelector("[data-iad-linksmodal]");
    if (m) return m;

    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="iad-linksmodal" data-iad-linksmodal hidden>
        <div class="iad-linksmodal-backdrop" data-iad-linksmodal-close></div>
        <div class="iad-linksmodal-sheet" role="dialog" aria-modal="true" aria-label="Links">
          <div class="iad-linksmodal-top">
            <div class="iad-linksmodal-title" data-iad-linksmodal-title>Links</div>
            <button class="iad-x" type="button" data-iad-linksmodal-close aria-label="Close">×</button>
          </div>
          <div class="iad-linksmodal-body" data-iad-linksmodal-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap.firstElementChild);
    m = document.querySelector("[data-iad-linksmodal]");

    m.querySelectorAll("[data-iad-linksmodal-close]").forEach((x) => {
      x.addEventListener("click", () => m.setAttribute("hidden", ""));
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && m && !m.hasAttribute("hidden")) {
        m.setAttribute("hidden", "");
      }
    });

    return m;
  }

  function openLinksModal(urls, topicTitle) {
    const m = ensureLinksModal();
    const title = m.querySelector("[data-iad-linksmodal-title]");
    const body = m.querySelector("[data-iad-linksmodal-body]");

    const safe = Array.isArray(urls) ? urls.filter(Boolean).map(String) : [];
    title.textContent = topicTitle ? `Links — ${topicTitle}` : "Links";

    if (!safe.length) {
      body.innerHTML = `<div class="iad-empty">No links found.</div>`;
    } else {
      body.innerHTML = `
        <div class="iad-linkslist">
          ${safe.map((u) => {
            const label = u.length > 80 ? (u.slice(0, 80) + "…") : u;
            return `
              <a class="iad-linksitem" href="${esc(u)}" target="_blank" rel="noopener noreferrer">
                <span class="iad-linksitem-ico">↗</span>
                <span class="iad-linksitem-txt">${esc(label)}</span>
              </a>
            `;
          }).join("")}
        </div>
      `;
    }

    m.removeAttribute("hidden");
  }

  // -----------------------------
  // Video viewer (Atrium-styled)
  // -----------------------------
  function lockPageScroll(lock) {
    const cls = "iad-modal-open";
    if (lock) document.documentElement.classList.add(cls);
    else document.documentElement.classList.remove(cls);
  }

  
  function openAttachmentsModal(urls, topicTitle) {
    const m = ensureLinksModal();
    const title = m.querySelector("[data-iad-linksmodal-title]");
    const body = m.querySelector("[data-iad-linksmodal-body]");

    const safe = Array.isArray(urls) ? urls.filter(Boolean).map(String) : [];
    title.textContent = topicTitle ? `Attachments — ${topicTitle}` : "Attachments";

    if (!safe.length) {
      body.innerHTML = `<div class="iad-empty">No attachments found.</div>`;
    } else {
      body.innerHTML = `
        <div class="iad-linkslist">
          ${safe.map((u) => {
            const label = u.length > 80 ? (u.slice(0, 80) + "…") : u;
            return `
              <a class="iad-linksitem" href="${esc(u)}" target="_blank" rel="noopener noreferrer">
                <span class="iad-linksitem-ico">↗</span>
                <span class="iad-linksitem-txt">${esc(label)}</span>
              </a>
            `;
          }).join("")}
        </div>
      `;
    }

    m.removeAttribute("hidden");
    lockPageScroll(true);
  }

function ensureVideoModal() {
    let m = document.querySelector("[data-iad-videomodal]");
    if (m) return m;

    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="iad-videomodal" data-iad-videomodal hidden>
        <div class="iad-videomodal-backdrop" data-iad-videomodal-close></div>

        <div class="iad-videomodal-sheet" role="dialog" aria-modal="true" aria-label="Video viewer">
          <div class="iad-videomodal-top">
            <div class="iad-videomodal-title" data-iad-videomodal-title>Video</div>
            <div class="iad-videomodal-actions">
              <a class="iad-videomodal-open" data-iad-videomodal-open target="_blank" rel="noopener noreferrer">Open ↗</a>
              <button class="iad-x" type="button" data-iad-videomodal-close aria-label="Close">×</button>
            </div>
          </div>

          <div class="iad-videomodal-body" data-iad-videomodal-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap.firstElementChild);
    m = document.querySelector("[data-iad-videomodal]");

    m.querySelectorAll("[data-iad-videomodal-close]").forEach((x) => {
      x.addEventListener("click", () => closeVideoModal());
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && m && !m.hasAttribute("hidden")) {
        closeVideoModal();
      }
    });

    return m;
  }

  function closeVideoModal() {
    const m = document.querySelector("[data-iad-videomodal]");
    if (!m) return;

    const body = m.querySelector("[data-iad-videomodal-body]");
    if (body) body.innerHTML = ""; // stop playback
    m.setAttribute("hidden", "");
    lockPageScroll(false);
  }

  function parseYouTubeId(url) {
    try {
      const u = new URL(url);
      if (u.hostname.includes("youtu.be")) {
        const id = u.pathname.replace(/^\/+/, "").split("/")[0];
        return id || null;
      }
      if (u.hostname.includes("youtube.com")) {
        const id = u.searchParams.get("v");
        return id || null;
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  function parsePeerTubeUuid(url) {
    try {
      const u = new URL(url);
      const m = u.pathname.match(/\/videos\/watch\/([^\/\?\#]+)/i);
      if (m && m[1]) return m[1];
      return null;
    } catch (e) {
      return null;
    }
  }

  function buildVideoMeta(videoUrl) {
    const url = String(videoUrl || "").trim();
    if (!url) return null;

    const yid = parseYouTubeId(url);
    if (yid) {
      return {
        kind: "youtube",
        url,
        embedUrl: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(yid)}`,
        thumbUrl: `https://img.youtube.com/vi/${encodeURIComponent(yid)}/hqdefault.jpg`
      };
    }

    const uuid = parsePeerTubeUuid(url);
    if (uuid) {
      try {
        const u = new URL(url);
        const origin = u.origin;
        return {
          kind: "peertube",
          url,
          embedUrl: origin + "/videos/embed/" + encodeURIComponent(uuid),
          thumbUrl: origin + "/lazy-static/previews/" + encodeURIComponent(uuid) + ".jpg"
        };
      } catch (e) {}
    }

    const lu = url.toLowerCase();
    if (/\.(mp4|webm|mov)(\?|$)/i.test(lu)) {
      return { kind: "file", url, embedUrl: url, thumbUrl: "" };
    }

    return { kind: "iframe", url, embedUrl: url, thumbUrl: "" };
  }

  // Back-compat alias (older handler name)
  function detectVideoMeta(videoUrl) {
    return buildVideoMeta(videoUrl);
  }

  function openVideoModal(meta, titleText) {
    if (!meta) return;

    const m = ensureVideoModal();
    const title = m.querySelector("[data-iad-videomodal-title]");
    const body = m.querySelector("[data-iad-videomodal-body]");
    const openLink = m.querySelector("[data-iad-videomodal-open]");

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

  function attachmentInlineMediaHTML(item) {
    // Show uploaded media inline WITHOUT inserting into body:
    // - first video (if present)
    // - then first image (if present)
    const atts = (item && item.media && item.media.attachments) ? item.media.attachments : [];
    if (!atts || !atts.length) return "";

    let firstVideo = null;
    let firstImage = null;

    for (const a of atts) {
      if (!firstVideo && isVideoAtt(a)) firstVideo = a;
      if (!firstImage && isImageAtt(a)) firstImage = a;
      if (firstVideo && firstImage) break;
    }

    if (!firstVideo && !firstImage) return "";

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

    const media = (item && item.media) ? item.media : {};
    const urls = Array.isArray(media.urls) ? media.urls.filter(Boolean).map(String) : [];

    const videoUrl = media.video_url ? String(media.video_url) : "";
    const videoMeta = videoUrl ? buildVideoMeta(videoUrl) : null;

    const linkUrls = urls.filter((u) => u && u !== videoUrl);

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

  function feedCard(item, view) {
    const author = item.topic_poster_username || ("user#" + (item.topic_poster_id || 0));
    const authorId = parseInt(item.topic_poster_id || "0", 10) || 0;
    const me = currentUserId();

    const ago = timeAgo(item.last_post_time || item.topic_time);

    const forumId = parseInt(item.forum_id || "0", 10) || 0;
    const forumName = item.forum_name || "agora";

    const canEdit = !!(me && authorId && me === authorId);

    // ✅ ADDED: inline uploaded media (video first, then image)
    // This does NOT replace your link-based media block; it only renders attachments.
    const inlineAttMedia = attachmentInlineMediaHTML(item);

    return `
      <article class="iad-card"
        data-topic-id="${item.topic_id}"
        data-first-post-id="${esc(String(item.first_post_id || 0))}"
        data-forum-id="${forumId}"
        data-forum-name="${esc(forumName)}"
        data-author-id="${esc(String(authorId))}">

        <div class="iad-card-main">
          <div class="iad-card-meta">
            <button
              type="button"
              class="iad-sub iad-agora-link"
              data-open-agora
              data-forum-id="${forumId}"
              data-forum-name="${esc(forumName)}"
              aria-label="Open agora ${esc(forumName)}"
              title="Open agora">
              agora/${esc(forumName)}
            </button>

            <span class="iad-dotsep">•</span>

            <button
              type="button"
              class="iad-user-link"
              data-open-user
              data-username="${esc(author)}"
              data-user-id="${esc(String(authorId))}"
              aria-label="Open profile ${esc(author)}"
              title="Open profile">
              ${esc(author)}
            </button>

            <span class="iad-dotsep">•</span>
            <span class="iad-time">${esc(ago)}</span>
          </div>

          <h3 class="iad-card-title" data-open-topic-title>${esc(item.topic_title || "")}</h3>

          ${inlineAttMedia ? `<div class="iad-attwrap">${inlineAttMedia}</div>` : ""}

          <div class="iad-card-excerpt" data-open-topic-excerpt>${item.excerpt_html || ""}</div>

          ${mediaBlockHTML(item, view)}

          ${attachmentPillsHTML(item)}

          <div class="iad-card-actions">
            <!-- Reply/comments icon (scrolls to comments) -->
            <button type="button" class="iad-iconbtn" data-open-topic-comments title="Open comments" aria-label="Open comments">
              ${ico("reply")}
            </button>

            <!-- Copy link -->
            <button type="button" class="iad-iconbtn" data-copy-topic-link title="Copy link" aria-label="Copy link">
              ${ico("link")}
            </button>

            <!-- Share to Connect -->
            <button type="button" class="iad-iconbtn" data-share-topic title="Share to Connect" aria-label="Share to Connect">
              ${ico("share")}
            </button>

            ${canEdit ? `
              <button type="button" class="iad-iconbtn" data-edit-topic title="Edit (coming soon)" aria-label="Edit">
                ${ico("edit")}
              </button>
            ` : ""}

            <span class="iad-muted">${esc(String(item.views || 0))} views</span>
          </div>
        </div>
      </article>
    `;
  }

  async function loadFeed(view, forumId, offset) {
    const tab = "new_posts";
    const res = await API.post("ia_discuss_feed", {
      tab,
      offset: offset || 0,
      forum_id: forumId || 0
    });

    if (!res || !res.success) return { items: [], has_more: false, next_offset: (offset || 0), error: true };
    return res.data || {};
  }

  function renderFeedInto(mount, view, forumId) {
    if (!mount) return;

    const pageSize = 20;
    let serverOffset = 0;
    let hasMore = false;
    let loading = false;

    function renderShell() {
      mount.innerHTML = `
        <div class="iad-feed">
          <div class="iad-feed-list"></div>
          <div class="iad-feed-more"></div>
        </div>
      `;
    }

    function setMoreButton() {
      const moreWrap = mount.querySelector(".iad-feed-more");
      if (!moreWrap) return;
      if (!hasMore) {
        moreWrap.innerHTML = "";
        return;
      }
      moreWrap.innerHTML = `<button type="button" class="iad-more" data-iad-feed-more>Load more</button>`;
    }

    function appendItems(items, feedView) {
      const list = mount.querySelector(".iad-feed-list");
      if (!list) return;

      if (feedView === "unread") {
        items = items.filter((it) => !STATE.isRead(it.topic_id));
      }

      if (!items.length && !list.children.length) {
        list.innerHTML = `<div class="iad-empty">Nothing here yet.</div>`;
        return;
      }

      // If first append and empty placeholder exists, clear it
      if (list.querySelector(".iad-empty")) list.innerHTML = "";

      list.insertAdjacentHTML("beforeend", items.map((it) => feedCard(it, feedView)).join(""));
    }

    async function loadNext() {
      if (loading) return;
      loading = true;

      const moreBtn = mount.querySelector("[data-iad-feed-more]");
      if (moreBtn) { moreBtn.disabled = true; moreBtn.textContent = "Loading…"; }

      const data = await loadFeed(view, forumId, serverOffset);
      const items = Array.isArray(data.items) ? data.items : [];

      hasMore = !!data.has_more || (items.length === pageSize);
      serverOffset = (typeof data.next_offset === "number") ? data.next_offset : (serverOffset + items.length);

      // Initial render shell if needed
      if (!mount.querySelector(".iad-feed-list")) renderShell();

      appendItems(items, view);
      setMoreButton();

      loading = false;
    }

    // Event delegation (works for appended items too)
    mount.onclick = function (e) {
      const t = e.target;

      // Never hijack real hyperlinks (attachments, external anchors, etc.)
      const a = t.closest && t.closest('a[href]');
      if (a) return;

      // Load more
      const more = t.closest && t.closest("[data-iad-feed-more]");
      if (more) {
        e.preventDefault();
        e.stopPropagation();
        loadNext();
        return;
      }

      // Copy link
      const copyBtn = t.closest && t.closest("[data-copy-topic-link]");
      if (copyBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = copyBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        const pid = card ? parseInt(card.getAttribute("data-first-post-id") || "0", 10) : 0;
        if (tid) {
          copyToClipboard(makeTopicUrl(tid, pid || 0)).then(() => {
            copyBtn.classList.add("is-pressed");
            setTimeout(() => copyBtn.classList.remove("is-pressed"), 450);
          });
        }
        return;
      }

      // Share to Connect (UI hook)
      const shareBtn = t.closest && t.closest("[data-share-topic]");
      if (shareBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = shareBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        const title = card ? (card.querySelector("[data-open-topic-title]")?.textContent || "") : "";
        // For now: open Connect tab and drop a lightweight share payload into localStorage.
        try {
          localStorage.setItem("ia_connect_share_draft", JSON.stringify({
            kind: "discuss_topic",
            topic_id: tid,
            title: String(title || "").trim(),
            url: tid ? makeTopicUrl(tid) : "",
            ts: Math.floor(Date.now() / 1000)
          }));
        } catch (e2) {}

        const tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
        if (tabBtn) tabBtn.click();
        return;
      }

      // Feed reply icon (open topic + open composer)
      const openComments = t.closest && t.closest("[data-open-topic-comments]");
      if (openComments) {
        e.preventDefault();
        e.stopPropagation();
        const card = openComments.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) {
          window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, open_reply: 1 } }));
        }
        return;
      }

      // Open user profile
      const userBtn = t.closest && t.closest("[data-open-user]");
      if (userBtn) {
        e.preventDefault();
        e.stopPropagation();
        openConnectProfile({
          username: userBtn.getAttribute("data-username") || "",
          user_id: userBtn.getAttribute("data-user-id") || "0"
        });
        return;
      }

      // Open Agora (forum)
      const agoraBtn = t.closest && t.closest("[data-open-agora]");
      if (agoraBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = agoraBtn.closest && agoraBtn.closest("[data-topic-id]");
        const fid = parseInt(agoraBtn.getAttribute("data-forum-id") || (card ? (card.getAttribute("data-forum-id") || "0") : "0"), 10) || 0;
        const nm = (agoraBtn.getAttribute("data-forum-name") || (card ? (card.getAttribute("data-forum-name") || "") : "")) || "";
        if (fid) {
          window.dispatchEvent(new CustomEvent("iad:open_agora", { detail: { forum_id: fid, forum_name: nm } }));
        }
        return;
      }

      // Open links modal
      const linksBtn = t.closest && t.closest("[data-iad-open-links]");
      if (linksBtn) {
        e.preventDefault();
        e.stopPropagation();
        const raw = linksBtn.getAttribute("data-links-json") || linksBtn.getAttribute("data-links") || "[]";
        try {
          const urls = JSON.parse(raw);
          const card = linksBtn.closest && linksBtn.closest('[data-topic-id]');
          const tEl = card ? card.querySelector('.iad-title,[data-open-topic-title]') : null;
          const titleText = tEl ? (tEl.textContent || '').trim() : '';
          openLinksModal(urls, titleText);
        } catch (err) {}
        return;
      }
      // Open attachments modal (single pill)
      const attBtn = t.closest && t.closest("[data-iad-open-attachments]");
      if (attBtn) {
        e.preventDefault();
        e.stopPropagation();
        const raw = attBtn.getAttribute("data-attachments-json") || "[]";
        try {
          const urls = JSON.parse(raw);
          const card = attBtn.closest && attBtn.closest("[data-topic-id]");
          const tEl = card ? card.querySelector(".iad-title,[data-open-topic-title]") : null;
          const titleText = tEl ? (tEl.textContent || '').trim() : '';
          openAttachmentsModal(urls, titleText);
        } catch (err) {}
        return;
      }

      // Open video modal
      const videoBtn = t.closest && t.closest("[data-iad-open-video]");
      if (videoBtn) {
        e.preventDefault();
        e.stopPropagation();
        const url = videoBtn.getAttribute("data-video-url") || "";
        if (url) {
          try {
          const card = videoBtn.closest && videoBtn.closest('[data-topic-id]');
          const tEl = card ? card.querySelector('.iad-title,[data-open-topic-title]') : null;
          const titleText = tEl ? (tEl.textContent || '').trim() : '';
          const meta = detectVideoMeta(url);
          if (meta) openVideoModal(meta, titleText);
        } catch (err) {}
        }
        return;
      }

      // Legacy hooks (if present elsewhere): open reply composer
      const quoteBtn = t.closest && t.closest("[data-quote-topic]");
      if (quoteBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = quoteBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, open_reply: 1 } }));
        return;
      }

      const replyBtn = t.closest && t.closest("[data-reply-topic]");
      if (replyBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = replyBtn.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, open_reply: 1 } }));
        return;
      }

      const editBtn = t.closest && t.closest("[data-edit-topic]");
      if (editBtn) {
        e.preventDefault();
        e.stopPropagation();
        alert("Edit is wired into UI, but saving edits isn’t implemented yet.");
        return;
      }

      // Default: open topic when clicking title/excerpt/card area
      const openTitle = t.closest && t.closest("[data-open-topic-title],[data-open-topic-excerpt],[data-open-topic]");
      if (openTitle) {
        e.preventDefault();
        e.stopPropagation();
        const card = openTitle.closest("[data-topic-id]");
        const tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
        if (tid) {
          window.dispatchEvent(new CustomEvent("iad:open_topic_page", { detail: { topic_id: tid, scroll: "" } }));
        }
      }
    };

    // Start
    renderShell();
    loadNext();
  }

  function renderFeed(root, view, forumId) {
    const mount = root ? root.querySelector("[data-iad-view]") : null;
    renderFeedInto(mount, view, forumId);
  }

  window.IA_DISCUSS_UI_FEED = { renderFeed, renderFeedInto };
})();
