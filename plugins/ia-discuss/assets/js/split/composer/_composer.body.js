  "use strict";
  const { qs, esc } = window.IA_DISCUSS_CORE;
  const API = window.IA_DISCUSS_API;
  const escapeHtml = (typeof esc === 'function') ? esc : (s) => String(s||'').replace(/[&<>"']/g, (c)=>({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c]));

  // Convert basic rich HTML (e.g., from Word) into BBCode-ish text.
  // Preserves paragraphs + common inline formatting on paste.
  function htmlToBbcode(html) {
    const h = String(html || "");
    if (!h) return "";

    const doc = document.implementation.createHTMLDocument("");
    doc.body.innerHTML = h;

    // Strip dangerous/irrelevant nodes
    doc.querySelectorAll("script,style,meta,link").forEach((n) => n.remove());

    function walk(node) {
      if (!node) return "";
      if (node.nodeType === 3) return node.nodeValue || "";
      if (node.nodeType !== 1) return "";

      const tag = String(node.tagName || "").toLowerCase();

      // Line breaks + block separation
      if (tag === "br") return "\n";

      const inner = Array.from(node.childNodes).map(walk).join("");

      if (tag === "p" || tag === "div" || tag === "section" || tag === "article") {
        const t = inner.replace(/\s+$/g, "");
        return t + "\n\n";
      }
      if (tag === "li") {
        return "- " + inner.replace(/\s+$/g, "") + "\n";
      }
      if (tag === "ul" || tag === "ol") {
        return inner + "\n";
      }

      // Inline formatting
      if (tag === "strong" || tag === "b") return "[b]" + inner + "[/b]";
      if (tag === "em" || tag === "i") return "[i]" + inner + "[/i]";
      if (tag === "u") return "[u]" + inner + "[/u]";

      if (tag === "a") {
        const href = node.getAttribute("href") || "";
        const label = inner || href;
        if (!href) return label;
        return "[url=" + href + "]" + label + "[/url]";
      }

      return inner;
    }

    let out = walk(doc.body);
    // normalize line endings
    out = out.replace(/\r\n/g, "\n");
    // collapse 3+ blank lines
    out = out.replace(/\n{3,}/g, "\n\n");
    return out.trimEnd();
  }


  function wrapSelection(textarea, before, after) {
    const el = textarea;
    const start = el.selectionStart || 0;
    const end = el.selectionEnd || 0;
    const val = el.value || "";
    const selected = val.slice(start, end);
    const next = val.slice(0, start) + before + selected + after + val.slice(end);
    el.value = next;
    el.focus();
    el.selectionStart = start + before.length;
    el.selectionEnd = end + before.length;
  }

  function insertAtCursor(textarea, text) {
    const el = textarea;
    const start = el.selectionStart || 0;
    const end = el.selectionEnd || 0;
    const val = el.value || "";
    const next = val.slice(0, start) + text + val.slice(end);
    el.value = next;
    el.focus();
    const p = start + text.length;
    el.selectionStart = p;
    el.selectionEnd = p;
  }

  function isImage(mime, url) {
    const m = String(mime || "").toLowerCase();
    if (m.startsWith("image/")) return true;
    const u = String(url || "").toLowerCase();
    return /\.(png|jpe?g|gif|webp|bmp|svg)(\?|#|$)/i.test(u);
  }

  function isVideo(mime, url) {
    const m = String(mime || "").toLowerCase();
    if (m.startsWith("video/")) return true;
    const u = String(url || "").toLowerCase();
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
    const mode = opts && opts.mode ? opts.mode : "topic"; // topic|reply
    const title = mode === "topic" ? `<input class="iad-input" data-iad-title placeholder="Title" />` : "";
    const submitLabel = (opts && opts.submitLabel) ? String(opts.submitLabel) : (mode === "topic" ? "Post" : "Reply");
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

          ${mode === "topic" ? `<label class="iad-check" style="display:flex;align-items:center;gap:8px;margin:8px 0 2px 0;user-select:none;">
            <input type="checkbox" data-iad-notify checked />
            <span>Email me replies to this topic</span>
          </label>` : ``}

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

