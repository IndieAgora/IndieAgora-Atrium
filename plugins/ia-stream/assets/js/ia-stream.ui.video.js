/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.video.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.video = NS.ui.video || {};

  function esc(s) {
    s = (s === null || s === undefined) ? "" : String(s);
    return s.replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function root() {
    return NS.util.qs("#ia-stream-shell");
  }

  function ensureModal() {
    const R = root();
    if (!R) return null;

    let M = NS.util.qs(".ia-stream-modal", document);
    if (M) return M;

    M = document.createElement("div");
    M.className = "ia-stream-modal";
    M.setAttribute("hidden", "hidden");
    M.innerHTML =
      '<div class="ia-stream-modal-dialog" role="dialog" aria-modal="true" aria-label="Stream video">' +
        '<div class="ia-stream-modal-header">' +
          '<div class="ia-stream-modal-title">Loading…</div>' +
          '<button type="button" class="ia-stream-modal-close" aria-label="Close">✕</button>' +
        '</div>' +
        '<div class="ia-stream-modal-body">' +
          '<div class="ia-stream-modal-video"></div>' +
          '<div class="ia-stream-modal-meta"></div>' +
          '<div class="ia-stream-modal-comments"></div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(M);

    // close handlers
    const closeBtn = NS.util.qs(".ia-stream-modal-close", M);
    NS.util.on(closeBtn, "click", closeModal);

    NS.util.on(M, "click", function (ev) {
      // click backdrop closes
      if (ev && ev.target === M) closeModal();
    });

    NS.util.on(document, "keydown", function (ev) {
      if (!isOpen()) return;
      if (ev && ev.key === "Escape") closeModal();
    });

    return M;
  }

  // ensure action handlers are bound once
  bindActionClicks();

  function isOpen() {
    const M = NS.util.qs(".ia-stream-modal", document);
    return !!(M && !M.hasAttribute("hidden"));
  }

  function closeModal() {
    const M = NS.util.qs(".ia-stream-modal", document);
    if (!M) return;

    M.setAttribute("hidden", "hidden");

    // stop playback by clearing iframe
    const V = NS.util.qs(".ia-stream-modal-video", M);
    if (V) V.innerHTML = "";

    // clear comments/meta
    const C = NS.util.qs(".ia-stream-modal-comments", M);
    if (C) C.innerHTML = "";
    const META = NS.util.qs(".ia-stream-modal-meta", M);
    if (META) META.innerHTML = "";

    const T = NS.util.qs(".ia-stream-modal-title", M);
    if (T) T.textContent = "";
  }

  function openModalShell(title) {
    const M = ensureModal();
    if (!M) return null;

    const T = NS.util.qs(".ia-stream-modal-title", M);
    if (T) T.textContent = title || "Loading…";

    const V = NS.util.qs(".ia-stream-modal-video", M);
    if (V) V.innerHTML = '<div class="ia-stream-player"><div class="ia-stream-player-overlay">Loading…</div></div>';

    const META = NS.util.qs(".ia-stream-modal-meta", M);
    if (META) META.innerHTML = "";

    const C = NS.util.qs(".ia-stream-modal-comments", M);
    if (C) C.innerHTML = '<div class="ia-stream-placeholder">Loading comments…</div>';

    M.removeAttribute("hidden");
    return M;
  }

  // ensure action handlers are bound once
  bindActionClicks();

  function renderVideoIntoModal(M, v) {
    const title = v && v.title ? v.title : "Video";
    const embed = v && v.embed_url ? v.embed_url : "";
    const url = v && v.url ? v.url : "";
    const excerpt = v && v.excerpt ? v.excerpt : "";
    const ago = v && v.published_ago ? v.published_ago : "";
    const ch = v && v.channel ? v.channel : {};
    const chName = ch && (ch.display_name || ch.name) ? (ch.display_name || ch.name) : "";

    const T = NS.util.qs(".ia-stream-modal-title", M);
    if (T) T.textContent = title;

    const V = NS.util.qs(".ia-stream-modal-video", M);
    if (V) {
      if (embed) {
        V.innerHTML =
          '<iframe loading="lazy" src="' + esc(embed) + '" allow="fullscreen; autoplay; encrypted-media" allowfullscreen></iframe>';
      } else if (url) {
        V.innerHTML =
          '<div class="ia-stream-player"><a href="' + esc(url) + '" target="_blank" rel="noopener" style="display:flex;align-items:center;justify-content:center;height:100%;color:white;text-decoration:none">Open on PeerTube</a></div>';
      } else {
        V.innerHTML =
          '<div class="ia-stream-player"><div class="ia-stream-player-overlay">No embed URL</div></div>';
      }
    }

    const META = NS.util.qs(".ia-stream-modal-meta", M);
    if (META) {
      META.innerHTML =
        '<p style="font-weight:600;margin-bottom:6px">' + esc(title) + '</p>' +
        '<div class="ia-stream-modal-sub">' +
          (chName ? esc(chName) : "Unknown channel") +
          (ago ? " • " + esc(ago) : "") +
          (url ? ' • <a href="' + esc(url) + '" target="_blank" rel="noopener">Open</a>' : "") +
        '</div>' +
        (excerpt ? '<p style="margin-top:10px;color:var(--ia-text)">' + esc(excerpt) + '</p>' : '') +
        '<div class="ia-stream-actions" data-video-id="' + esc(v && v.id ? v.id : '') + '" data-channel-url="' + esc(ch && ch.url ? ch.url : '') + '">' +
          '<button type="button" class="ia-stream-action" data-act="like">Like</button>' +
          '<button type="button" class="ia-stream-action" data-act="follow">Follow</button>' +
        '</div>' +
        '<div class="ia-stream-action-status" style="margin-top:8px;color:var(--ia-muted);font-size:0.85rem"></div>';
    }
  }



function deriveChannelUri(channelUrl, channelName) {
  try {
    const u = new URL(channelUrl);
    const host = u.host;
    const name = (channelName || "").trim();
    if (!host || !name) return "";
    // Best-effort subscription handle (works for local channels)
    return name + "@" + host;
  } catch (e) {
    return "";
  }
}

function bindActionClicks() {
  const M = ensureModal();
  if (!M || M.__iaStreamActionsBound) return;
  M.__iaStreamActionsBound = true;

  NS.util.on(M, "click", async function (ev) {
    const btn = ev && ev.target ? ev.target.closest(".ia-stream-action") : null;
    if (!btn) return;

    const act = btn.getAttribute("data-act") || "";
    const meta = NS.util.qs(".ia-stream-actions", M);
    const status = NS.util.qs(".ia-stream-action-status", M);
    const videoId = meta ? (meta.getAttribute("data-video-id") || "") : "";
    const channelUrl = meta ? (meta.getAttribute("data-channel-url") || "") : "";

    const shell = NS.util.qs("#ia-stream-shell");
    const loggedIn = shell && shell.getAttribute("data-logged-in") === "1";

    if (!loggedIn) {
      if (status) status.textContent = "Login required for this action.";
      return;
    }

    if (!videoId) {
      if (status) status.textContent = "Missing video id.";
      return;
    }

    if (status) status.textContent = "Working…";

    if (act === "like") {
      const r = await NS.api.rateVideo(videoId, "like");
      if (r && r.ok !== false) {
        if (status) status.textContent = "Liked.";
      } else {
        if (status) status.textContent = (r && (r.message || r.error)) ? (r.message || r.error) : "Like failed.";
      }
      return;
    }

    if (act === "follow") {
      // We need a channel uri; best-effort from URL + channel name (extracted from DOM title line)
      const titleLine = NS.util.qs(".ia-stream-modal-sub", M);
      const chName = titleLine ? (titleLine.textContent || "").split("•")[0].trim() : "";
      const uri = deriveChannelUri(channelUrl, chName);
      if (!uri) {
        if (status) status.textContent = "Could not determine channel handle.";
        return;
      }
      const r = await NS.api.subscribe(uri);
      if (r && r.ok !== false) {
        if (status) status.textContent = "Followed.";
      } else {
        if (status) status.textContent = (r && (r.message || r.error)) ? (r.message || r.error) : "Follow failed.";
      }
      return;
    }
  });
}
  NS.ui.video.open = async function (videoId) {
    videoId = (videoId === null || videoId === undefined) ? "" : String(videoId).trim();
    if (!videoId) return;

    const M = openModalShell("Loading…");
    if (!M) return;

    // Fetch full video details (PeerTube-backed via PHP module)
    const res = await NS.api.fetchVideo({ id: videoId });

    if (!res || res.ok === false || !res.item) {
      const C = NS.util.qs(".ia-stream-modal-comments", M);
      if (C) C.innerHTML = '<div class="ia-stream-placeholder">' + esc((res && res.error) ? res.error : "Video load failed") + '</div>';
      const V = NS.util.qs(".ia-stream-modal-video", M);
      if (V) V.innerHTML = '<div class="ia-stream-player"><div class="ia-stream-player-overlay">Failed to load</div></div>';
      return;
    }

    renderVideoIntoModal(M, res.item);

    // Load comments after video renders
    if (NS.ui.comments && NS.ui.comments.load) {
      NS.ui.comments.load(videoId);
    }
  };

  NS.ui.video.close = closeModal;
})();
