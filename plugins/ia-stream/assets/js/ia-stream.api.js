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

      // Normalize common WP AJAX error shapes so Stream UI can treat
      // recoverable token states consistently across all write surfaces.
      try {
        const out = (json && typeof json === 'object') ? Object.assign({}, json) : json;
        const nested = out && typeof out === 'object' ? (out.data && typeof out.data === 'object' ? out.data : (out.error && typeof out.error === 'object' ? out.error : null)) : null;
        const topCode = out && typeof out.code === 'string' ? out.code : '';
        const nestedCode = nested && typeof nested.code === 'string' ? nested.code : '';
        const topError = out && typeof out.error === 'string' ? out.error : '';
        const nestedError = nested && typeof nested.error === 'string' ? nested.error : (nested && typeof nested.message === 'string' ? nested.message : '');
        if (out && typeof out === 'object') {
          if (!out.code && nestedCode) out.code = nestedCode;
          if ((!out.error || typeof out.error !== 'string') && nestedError) out.error = nestedError;
          if (!out.message && typeof out.error === 'string') out.message = out.error;
          if (out.ok === undefined && out.success === false) out.ok = false;
          if (out.ok === undefined && out.success === true) out.ok = true;
          if (out.ok === false && !out.code && topError && (topError === 'missing_user_token' || topError === 'password_required')) {
            out.code = topError;
          }
        }
        return out;
      } catch (e) {
        return json;
      }
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

  NS.api.fetchCommentThread = function (opts) {
    return NS.api.post("ia_stream_comment_thread", opts || {});
  };

  NS.api.createCommentThread = function (opts) {
    return NS.api.post("ia_stream_comment_create", opts || {});
  };

  NS.api.replyToComment = function (opts) {
    return NS.api.post("ia_stream_comment_reply", opts || {});
  };

  NS.api.rateVideo = function (opts) {
    return NS.api.post("ia_stream_video_rate", opts || {});
  };

  NS.api.rateComment = function (opts) {
    return NS.api.post("ia_stream_comment_rate", opts || {});
  };

  NS.api.deleteComment = function (opts) {
    return NS.api.post("ia_stream_comment_delete", opts || {});
  };
})();
