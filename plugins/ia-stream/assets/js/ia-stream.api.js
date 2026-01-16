/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.api.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.api = NS.api || {};

  /**
   * NOTE:
   * We are not wiring PeerTube yet (no creds endpoints pasted yet).
   * This is a placeholder API layer that will be connected to WP AJAX
   * (which then calls PeerTube API using ia-engine credentials).
   */

  NS.api.post = async function (action, data) {
    // For now, this intentionally fails gracefully until ajax.php is filled.
    // When wired: window.IA_STREAM_CFG.ajaxUrl + nonce, like Discuss.
    const cfg = window.IA_STREAM_CFG || null;
    if (!cfg || !cfg.ajaxUrl) {
      return { ok: false, error: "IA_STREAM_CFG missing (ajax not wired yet)." };
    }

    const body = new URLSearchParams();
    body.set("action", action);
    body.set("nonce", cfg.nonce || "");
    Object.keys(data || {}).forEach((k) => body.set(k, String(data[k])));

    try {
      const res = await fetch(cfg.ajaxUrl, {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
        credentials: "same-origin",
        body: body.toString()
      });

      const txt = await res.text();
      const json = NS.util.safeJson(txt, null);
      if (!json) return { ok: false, error: "Non-JSON response", raw: txt };

      return json;
    } catch (e) {
      return { ok: false, error: e && e.message ? e.message : "Network error" };
    }
  };

  // These will be implemented once ajax endpoints exist.
  NS.api.fetchFeed = function (opts) {
    return NS.api.post("ia_stream_feed", opts || {});
  };

  NS.api.fetchChannels = function (opts) {
    return NS.api.post("ia_stream_channels", opts || {});
  };

  NS.api.fetchVideo = function (opts) {
    return NS.api.post("ia_stream_video", opts || {});
  };

  NS.api.fetchComments = function (opts) {
    return NS.api.post("ia_stream_comments", opts || {});
  };

  NS.api.fetchMySubs = function (opts) {
    return NS.api.post("ia_stream_my_subs", opts || {});
  };

// ---------------------------
// Write actions (PeerTube via WP AJAX)
// ---------------------------

NS.api.rateVideo = function (videoId, rating) {
  return NS.api.post("ia_stream_rate", { video_id: videoId, rating: rating || "like" });
};

NS.api.subscribe = function (uri) {
  return NS.api.post("ia_stream_subscribe", { uri: uri || "" });
};

NS.api.commentCreate = function (videoId, text) {
  return NS.api.post("ia_stream_comment_create", { video_id: videoId, text: text || "" });
};

// Upload uses multipart FormData (handled by WP AJAX)
NS.api.upload = async function (file, name, description) {
  const cfg = window.IA_STREAM_CFG || null;
  if (!cfg || !cfg.ajaxUrl) return { ok: false, error: "IA_STREAM_CFG missing (ajax not wired yet)." };

  const fd = new FormData();
  fd.append("action", "ia_stream_upload");
  fd.append("nonce", cfg.nonce || "");
  fd.append("videofile", file);
  if (name) fd.append("name", String(name));
  if (description) fd.append("description", String(description));

  try {
    const res = await fetch(cfg.ajaxUrl, { method: "POST", credentials: "same-origin", body: fd });
    const txt = await res.text();
    const json = NS.util.safeJson(txt, null);
    if (!json) return { ok: false, error: "Non-JSON response", raw: txt };
    return json;
  } catch (e) {
    return { ok: false, error: e && e.message ? e.message : "Network error" };
  }
};


})();
