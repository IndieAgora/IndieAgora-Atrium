(function () {
  "use strict";

  function safeJsonFromResponse(r) {
    return r.text().then((t) => {
      try { return JSON.parse(t); }
      catch (e) {
        return { success: false, data: { message: "Non-JSON response from server", raw: String(t).slice(0, 300) } };
      }
    });
  }

  function fetchWithTimeout(url, opts, timeoutMs) {
    timeoutMs = timeoutMs || 30000;
    if (typeof AbortController === "undefined") return fetch(url, opts);
    const ac = new AbortController();
    const id = setTimeout(() => ac.abort(), timeoutMs);
    opts = Object.assign({}, opts, { signal: ac.signal });
    return fetch(url, opts).finally(() => clearTimeout(id));
  }


  function post(action, payload, opts) {
    payload = payload || {};
    payload.action = action;
    payload.nonce = (window.IA_DISCUSS && IA_DISCUSS.nonce) || "";

    const fd = new FormData();
    Object.keys(payload).forEach((k) => fd.append(k, payload[k]));

    return fetchWithTimeout(IA_DISCUSS.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: fd
    }).then((r) => safeJsonFromResponse(r));
  }

  function uploadFile(file) {
    const fd = new FormData();
    fd.append("action", "ia_discuss_upload");
    fd.append("nonce", IA_DISCUSS.nonce);
    fd.append("file", file);

    return fetchWithTimeout(IA_DISCUSS.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: fd
    }).then((r) => safeJsonFromResponse(r));
  }

  window.IA_DISCUSS_API = { post, uploadFile };
})();
