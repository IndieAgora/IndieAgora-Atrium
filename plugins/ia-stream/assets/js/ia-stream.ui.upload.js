/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.upload.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.upload = NS.ui.upload || {};

  function esc(s) {
    s = (s === null || s === undefined) ? "" : String(s);
    return s.replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function ensureModal() {
    let M = NS.util.qs(".ia-stream-upload-modal", document);
    if (M) return M;

    M = document.createElement("div");
    M.className = "ia-stream-upload-modal";
    M.setAttribute("hidden", "hidden");
    M.innerHTML =
      '<div class="ia-stream-upload-dialog" role="dialog" aria-modal="true" aria-label="Upload video">' +
        '<div class="ia-stream-upload-header">' +
          '<div class="ia-stream-upload-title">Upload</div>' +
          '<button type="button" class="ia-stream-upload-close" aria-label="Close">✕</button>' +
        '</div>' +
        '<div class="ia-stream-upload-body">' +
          '<div class="ia-stream-upload-field">' +
            '<label>Video file</label>' +
            '<input type="file" class="ia-stream-upload-file" accept="video/*" />' +
          '</div>' +
          '<div class="ia-stream-upload-field">' +
            '<label>Title</label>' +
            '<input type="text" class="ia-stream-upload-name" placeholder="Video title" />' +
          '</div>' +
          '<div class="ia-stream-upload-field">' +
            '<label>Description</label>' +
            '<textarea class="ia-stream-upload-desc" rows="4" placeholder="Optional description"></textarea>' +
          '</div>' +
          '<div class="ia-stream-upload-actions">' +
            '<button type="button" class="ia-stream-upload-send">Upload</button>' +
            '<div class="ia-stream-upload-status"></div>' +
          '</div>' +
          '<div class="ia-stream-upload-note">Note: this uses the single-request upload endpoint. Very large files may fail if server/proxy limits are low.</div>' +
        '</div>' +
      '</div>';

    document.body.appendChild(M);

    NS.util.on(NS.util.qs(".ia-stream-upload-close", M), "click", close);
    NS.util.on(M, "click", function (ev) { if (ev && ev.target === M) close(); });

    return M;
  }

  function open() {
    const M = ensureModal();
    if (!M) return;
    const st = NS.util.qs(".ia-stream-upload-status", M);
    if (st) st.textContent = "";
    M.removeAttribute("hidden");
  }

  function close() {
    const M = NS.util.qs(".ia-stream-upload-modal", document);
    if (!M) return;
    M.setAttribute("hidden", "hidden");
  }

  function bind() {
    const shell = NS.util.qs("#ia-stream-shell");
    if (!shell) return;

    const btn = NS.util.qs(".ia-stream-upload-btn", shell);
    if (!btn) return;

    NS.util.on(btn, "click", function () {
      open();
    });

    const M = ensureModal();
    if (!M || M.__iaUploadBound) return;
    M.__iaUploadBound = true;

    NS.util.on(M, "click", async function (ev) {
      const send = ev && ev.target ? ev.target.closest(".ia-stream-upload-send") : null;
      if (!send) return;

      const fileEl = NS.util.qs(".ia-stream-upload-file", M);
      const nameEl = NS.util.qs(".ia-stream-upload-name", M);
      const descEl = NS.util.qs(".ia-stream-upload-desc", M);
      const status = NS.util.qs(".ia-stream-upload-status", M);

      const file = fileEl && fileEl.files && fileEl.files[0] ? fileEl.files[0] : null;
      const name = nameEl ? (nameEl.value || "").trim() : "";
      const desc = descEl ? (descEl.value || "").trim() : "";

      if (!file) { if (status) status.textContent = "Choose a video file first."; return; }

      if (status) status.textContent = "Uploading…";
      send.disabled = true;

      const res = await NS.api.upload(file, name || file.name, desc);

      send.disabled = false;

      if (res && res.ok !== false) {
        if (status) status.textContent = "Uploaded.";
        // Soft refresh feed
        NS.util.dispatch("ia:stream:refresh", {});
        setTimeout(close, 600);
      } else {
        if (status) status.textContent = (res && (res.message || res.error)) ? (res.message || res.error) : "Upload failed.";
      }
    });
  }

  NS.ui.upload.boot = bind;
})();
