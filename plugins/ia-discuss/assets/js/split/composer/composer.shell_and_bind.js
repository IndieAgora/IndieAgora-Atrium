  "use strict";
  var { qs, esc } = window.IA_DISCUSS_CORE;
  var API = window.IA_DISCUSS_API;

  function wrapSelection(textarea, before, after) {
    var el = textarea;
    var start = el.selectionStart || 0;
    var end = el.selectionEnd || 0;
    var val = el.value || "";
    var selected = val.slice(start, end);
    var next = val.slice(0, start) + before + selected + after + val.slice(end);
    el.value = next;
    el.focus();
    el.selectionStart = start + before.length;
    el.selectionEnd = end + before.length;
  }

  function insertAtCursor(textarea, text) {
    var el = textarea;
    var start = el.selectionStart || 0;
    var end = el.selectionEnd || 0;
    var val = el.value || "";
    var next = val.slice(0, start) + text + val.slice(end);
    el.value = next;
    el.focus();
    var p = start + text.length;
    el.selectionStart = p;
    el.selectionEnd = p;
  }

  function isImage(mime, url) {
    var m = String(mime || "").toLowerCase();
    if (m.startsWith("image/")) return true;
    var u = String(url || "").toLowerCase();
    return /\.(png|jpe?g|gif|webp|bmp|svg)(\?|#|$)/i.test(u);
  }

  function isVideo(mime, url) {
    var m = String(mime || "").toLowerCase();
    if (m.startsWith("video/")) return true;
    var u = String(url || "").toLowerCase();
    return /\.(mp4|webm|mov|m4v|ogg)(\?|#|$)/i.test(u);
  }

  function makeToolbar() {
    return `
      <div class="iad-editorbar">
        <button type="button" data-act="b" title="Bold"><strong>B</strong></button>
        <button type="button" data-act="i" title="Italic"><em>I</em></button>
        <button type="button" data-act="u" title="Underline"><span style="text-decoration:underline">U</span></button>
        <button type="button" data-act="spoiler" title="Spoiler">S</button>
        <button type="button" data-act="quote" title="Quote">❝</button>
        <button type="button" data-act="attach" title="Attach">📎</button>
      </div>
    `;
  }

  function composerHTML(opts) {
    var mode = opts && opts.mode ? opts.mode : "topic"; // topic|reply
    var title = mode === "topic" ? `<input class="iad-input" data-iad-title placeholder="Title" />` : "";
    var submitLabel = (opts && opts.submitLabel) ? String(opts.submitLabel) : (mode === "topic" ? "Post" : "Reply");
    return `
      <div class="iad-composer ${mode === "reply" ? "is-reply" : ""}" data-iad-composer>
        <div class="iad-composer-top">
          <button type="button" class="iad-composer-toggle" data-iad-toggle>
            <span class="iad-dot"></span>
            <span>${mode === "topic" ? "Create a post" : "Write a reply"}</span>
          </button>
        </div>

        <div class="iad-composer-body" data-iad-body hidden>
          ${title}
          ${makeToolbar()}
          <textarea class="iad-textarea" data-iad-bodytext placeholder="Text (BBCode supported)"></textarea>

          <div data-iad-error aria-live="polite"></div>

          <div class="iad-attachlist" data-iad-attachlist></div>

          <div class="iad-composer-actions">
            <button type="button" class="iad-btn is-muted" data-iad-add-attach>+ Add attachment</button>
            <button type="button" class="iad-btn" data-iad-submit>${submitLabel}</button>
          </div>
        </div>
      </div>
    `;
  }

  function bindComposer(root, opts) {
    var box = qs("[data-iad-composer]", root);
;
