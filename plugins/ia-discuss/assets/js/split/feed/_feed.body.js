  "use strict";
  var CORE = window.IA_DISCUSS_CORE || {};
  var esc = CORE.esc || function (s) {
    return String(s || "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  };
  var timeAgo = CORE.timeAgo || function () { return ""; };

  var API = window.IA_DISCUSS_API;
  var STATE = window.IA_DISCUSS_STATE;

  function currentUserId() {
    // If your localized IA_DISCUSS includes userId later, we’ll pick it up.
    // Otherwise edit buttons just won’t show.
    try {
      var v = (window.IA_DISCUSS && (IA_DISCUSS.userId || IA_DISCUSS.user_id || IA_DISCUSS.wpUserId)) || 0;
      return parseInt(v, 10) || 0;
    } catch (e) {
      return 0;
    }
  }

  function makeTopicUrl(topicId) {
    try {
      var u = new URL(window.location.href);
      u.searchParams.set("iad_topic", String(topicId));
      return u.toString();
    } catch (e) {
      return window.location.href;
    }
  }

  async function copyToClipboard(text) {
    var t = String(text || "");
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(t);
        return true;
      }
    } catch (e) {}
    try {
      var ta = document.createElement("textarea");
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
    var p = payload || {};
    var username = (p.username || "").trim();
    var user_id = parseInt(p.user_id || "0", 10) || 0;

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

    var tabBtn = document.querySelector('.ia-tab[data-target="connect"]');
    if (tabBtn) tabBtn.click();
  }

  // -----------------------------
  // Icons (inline SVG)
  // -----------------------------
  function ico(name) {
    var common = `width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"`;
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
    var m = document.querySelector("[data-iad-linksmodal]");
    if (m) return m;

    var wrap = document.createElement("div");
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
    var m = ensureLinksModal();
    var title = m.querySelector("[data-iad-linksmodal-title]");
    var body = m.querySelector("[data-iad-linksmodal-body]");

    var safe = Array.isArray(urls) ? urls.filter(Boolean).map(String) : [];
    title.textContent = topicTitle ? `Links — ${topicTitle}` : "Links";

    if (!safe.length) {
      body.innerHTML = `<div class="iad-empty">No links found.</div>`;
    } else {
      body.innerHTML = `
        <div class="iad-linkslist">
          ${safe.map((u) => {
            var label = u.length > 80 ? (u.slice(0, 80) + "…") : u;
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
    var cls = "iad-modal-open";
    if (lock) document.documentElement.classList.add(cls);
    else document.documentElement.classList.remove(cls);
  }

  function ensureVideoModal() {
    var m = document.querySelector("[data-iad-videomodal]");
    if (m) return m;

    var wrap = document.createElement("div");
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
    var m = document.querySelector("[data-iad-videomodal]");
    if (!m) return;

    var body = m.querySelector("[data-iad-videomodal-body]");
    if (body) body.innerHTML = ""; // stop playback
    m.setAttribute("hidden", "");
    lockPageScroll(false);
  }

  function parseYouTubeId(url) {
    try {
      var u = new URL(url);
      if (u.hostname.includes("youtu.be")) {
        var id = u.pathname.replace(/^\/+/, "").split("/")[0];
        return id || null;
      }
      if (u.hostname.includes("youtube.com")) {
        var id = u.searchParams.get("v");
        return id || null;
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  function parsePeerTubeUuid(url) {
    try {
      var u = new URL(url);
      var m = u.pathname.match(/\/videos\/watch\/([^\/\?\#]+)/i);
      if (m && m[1]) return m[1];
      return null;
    } catch (e) {
      return null;
    }
  }

  function buildVideoMeta(videoUrl) {
    var url = String(videoUrl || "").trim();
    if (!url) return null;

    var yid = parseYouTubeId(url);
    if (yid) {
      return {
        kind: "youtube",
        url,
        embedUrl: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(yid)}`,
        thumbUrl: `https://img.youtube.com/vi/${encodeURIComponent(yid)}/hqdefault.jpg`
      };
    }

    var uuid = parsePeerTubeUuid(url);
    if (uuid) {
      try {
        var u = new URL(url);
        var origin = u.origin;
        return {
          kind: "peertube",
          url,
          embedUrl: origin + "/videos/embed/" + encodeURIComponent(uuid),
          thumbUrl: origin + "/lazy-static/previews/" + encodeURIComponent(uuid) + ".jpg"
        };
      } catch (e) {}
    }

    var lu = url.toLowerCase();
    if (/\.(mp4|webm|mov)(\?|$)/i.test(lu)) {
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
          class="iad-attachpill"
          href="${esc(url)}"
          target="_blank"
          rel="noopener noreferrer"
          title="${esc(filename)}">
          ${esc(filename)}
        </a>
      `;
    }).join("");

    return pills ? `<div class="iad-attachrow">${pills}</div>` : "";
  }

  function feedCard(item, view) {
    var author = item.topic_poster_username || ("user#" + (item.topic_poster_id || 0));
    var authorId = parseInt(item.topic_poster_id || "0", 10) || 0;
    var me = currentUserId();

    var ago = timeAgo(item.last_post_time || item.topic_time);

    var forumId = parseInt(item.forum_id || "0", 10) || 0;
    var forumName = item.forum_name || "agora";

    var canEdit = !!(me && authorId && me === authorId);

    // ✅ ADDED: inline uploaded media (video first, then image)
    // This does NOT replace your link-based media block; it only renders attachments.
    var inlineAttMedia = attachmentInlineMediaHTML(item);

    return `
      <article class="iad-card"
        data-topic-id="${item.topic_id}"
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

  async function loadFeed(view, forumId) {
    var tab = "new_posts";
    var res = await API.post("ia_discuss_feed", {
      tab,
      offset: 0,
      forum_id: forumId || 0
    });

    if (!res || !res.success) return { items: [], error: true };
    return res.data;
  }

  function renderFeedInto(mount, view, forumId) {
    if (!mount) return;

    mount.innerHTML = `<div class="iad-loading">Loading…</div>`;

    loadFeed(view, forumId).then((data) => {
      var items = data.items || [];

      if (view === "unread") {
        items = items.filter((it) => !STATE.isRead(it.topic_id));
      }

      mount.innerHTML = `
        <div class="iad-feed">
          <div class="iad-feed-list">
            ${items.length ? items.map((it) => feedCard(it, view)).join("") : `<div class="iad-empty">Nothing here yet.</div>`}
          </div>
        </div>
      `;

      function openTopicFromEvent(e, scroll) {
        e.preventDefault();
        e.stopPropagation();
        var card = e.target.closest("[data-topic-id]");
        if (!card) return;
        var tid = parseInt(card.getAttribute("data-topic-id") || "0", 10);
        if (!tid) return;

        window.dispatchEvent(new CustomEvent("iad:open_topic_page", {
          detail: { topic_id: tid, scroll: scroll || "" }
        }));
      }

      // title/excerpt -> open topic (top)
      mount.querySelectorAll("[data-open-topic-title]").forEach((el) => el.addEventListener("click", (e) => openTopicFromEvent(e, "")));
      mount.querySelectorAll("[data-open-topic-excerpt]").forEach((el) => el.addEventListener("click", (e) => openTopicFromEvent(e, "")));

      // comments icon -> open topic AND scroll to comments
      mount.querySelectorAll("[data-open-topic-comments]").forEach((btn) => btn.addEventListener("click", (e) => openTopicFromEvent(e, "comments")));

      // open agora
      mount.querySelectorAll("[data-open-agora]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          var fid = parseInt(btn.getAttribute("data-forum-id") || "0", 10);
          if (!fid) return;

          var forumName = "";
          var card = btn.closest("[data-topic-id]");
          if (card) forumName = card.getAttribute("data-forum-name") || "";

          window.dispatchEvent(new CustomEvent("iad:open_agora", {
            detail: { forum_id: fid, forum_name: forumName }
          }));
        });
      });

      // open user
      mount.querySelectorAll("[data-open-user]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          openConnectProfile({
            username: btn.getAttribute("data-username") || "",
            user_id: btn.getAttribute("data-user-id") || "0"
          });
        });
      });

      // Links pill
      mount.querySelectorAll("[data-iad-open-links]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();

          var urls = [];
          try { urls = JSON.parse(btn.getAttribute("data-links") || "[]"); }
          catch (err) { urls = []; }

          var card = btn.closest("[data-topic-id]");
          var title = card ? (card.querySelector(".iad-card-title")?.textContent || "") : "";
          openLinksModal(urls, title);
        });
      });

      // Video thumbnail -> viewer
      mount.querySelectorAll("[data-iad-open-video]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();

          var videoUrl = btn.getAttribute("data-video-url") || "";
          var meta = buildVideoMeta(videoUrl);

          var card = btn.closest("[data-topic-id]");
          var title = card ? (card.querySelector(".iad-card-title")?.textContent || "") : "Video";
          openVideoModal(meta, title);
        });
      });

      // Copy link
      mount.querySelectorAll("[data-copy-topic-link]").forEach((btn) => {
        btn.addEventListener("click", async (e) => {
          e.preventDefault();
          e.stopPropagation();

          var card = btn.closest("[data-topic-id]");
          var tid = card ? parseInt(card.getAttribute("data-topic-id") || "0", 10) : 0;
          if (!tid) return;

          var url = makeTopicUrl(tid);
          var ok = await copyToClipboard(url);

          btn.classList.add("is-pressed");
          setTimeout(() => btn.classList.remove("is-pressed"), 450);

          if (!ok) alert("Could not copy link on this browser.");
        });
      });

      // Share to Connect (event only — Connect will implement)
      mount.querySelectorAll("[data-share-topic]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();

          var card = btn.closest("[data-topic-id]");
          if (!card) return;
          var tid = parseInt(card.getAttribute("data-topic-id") || "0", 10);
          if (!tid) return;

          var title = card.querySelector(".iad-card-title")?.textContent || "";
          var url = makeTopicUrl(tid);

          window.dispatchEvent(new CustomEvent("ia:share_to_connect", {
            detail: { type: "discuss_topic", topic_id: tid, title, url }
          }));

          btn.classList.add("is-pressed");
          setTimeout(() => btn.classList.remove("is-pressed"), 450);
        });
      });

      // Edit (UI hook only)
      mount.querySelectorAll("[data-edit-topic]").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopPropagation();
          alert("Edit is wired into UI, but saving edits isn’t implemented yet.");
        });
      });
    });
  }

  function renderFeed(root, view, forumId) {
    var mount = root ? root.querySelector("[data-iad-view]") : null;
    renderFeedInto(mount, view, forumId);
  }

  window.IA_DISCUSS_UI_FEED = { renderFeed, renderFeedInto };
