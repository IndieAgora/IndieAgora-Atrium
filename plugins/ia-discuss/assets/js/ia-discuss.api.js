(function () {
  "use strict";

  function post(action, payload, opts) {
    payload = payload || {};
    payload.action = action;
    payload.nonce = (window.IA_DISCUSS && IA_DISCUSS.nonce) || "";

    const fd = new FormData();
    Object.keys(payload).forEach((k) => fd.append(k, payload[k]));

    return fetch(IA_DISCUSS.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: fd
    }).then((r) => r.json());
  }

  function uploadFile(file) {
    const fd = new FormData();
    fd.append("action", "ia_discuss_upload");
    fd.append("nonce", IA_DISCUSS.nonce);
    fd.append("file", file);

    return fetch(IA_DISCUSS.ajaxUrl, {
      method: "POST",
      credentials: "same-origin",
      body: fd
    }).then((r) => r.json());
  }

  window.IA_DISCUSS_API = { post, uploadFile };
})();
