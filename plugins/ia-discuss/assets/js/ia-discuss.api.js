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
    // Allow bigger uploads / slower connections.
    // User-requested: 1000 seconds.
    timeoutMs = timeoutMs || 1000000;
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

  // Upload with progress (XMLHttpRequest). Optional.
  function uploadFileWithProgress(file, onProgress) {
    return new Promise((resolve) => {
      try {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", IA_DISCUSS.ajaxUrl, true);
        xhr.withCredentials = true;

        // User-requested: 1000 seconds.
        try { xhr.timeout = 1000000; } catch (e) {}

        xhr.upload.onprogress = function (evt) {
          if (!evt || !evt.lengthComputable) return;
          const pct = Math.max(0, Math.min(100, Math.round((evt.loaded / evt.total) * 100)));
          try { if (typeof onProgress === "function") onProgress(pct, evt.loaded, evt.total); } catch (e) {}
        };

        xhr.onreadystatechange = function () {
          if (xhr.readyState !== 4) return;
          try {
            const t = xhr.responseText || "";
            const j = JSON.parse(t);
            resolve(j);
          } catch (e) {
            resolve({ success: false, data: { message: "Non-JSON response from server", raw: String(xhr.responseText || "").slice(0, 300) } });
          }
        };

        xhr.onerror = function () {
          resolve({ success: false, data: { message: "Upload failed" } });
        };

        xhr.ontimeout = function () {
          resolve({ success: false, data: { message: "Upload timed out" } });
        };

        const fd = new FormData();
        fd.append("action", "ia_discuss_upload");
        fd.append("nonce", IA_DISCUSS.nonce);
        fd.append("file", file);
        xhr.send(fd);
      } catch (e) {
        // Fallback
        uploadFile(file).then(resolve);
      }
    });
  }

  window.IA_DISCUSS_API = { post, uploadFile, uploadFileWithProgress };
})();
