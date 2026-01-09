(function () {
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
    const box = qs("[data-iad-composer]", root);
    if (!box) return;

    const toggle = qs("[data-iad-toggle]", box);
    const body = qs("[data-iad-body]", box);
    const ta = qs("[data-iad-bodytext]", box);
    const title = qs("[data-iad-title]", box);
    const attachList = qs("[data-iad-attachlist]", box);
    const errBox = qs("[data-iad-error]", box);
    const addAttachBtn = qs("[data-iad-add-attach]", box);

    const startOpen = !!(opts && opts.startOpen);


    // Prefill (used for editing existing posts)
    const prefillBody = (opts && typeof opts.prefillBody === "string") ? opts.prefillBody : "";
    if (ta && prefillBody) {
      ta.value = prefillBody;
    }

    // Smart paste: keep paragraphs + basic formatting when pasting rich text (Word, web pages, etc.)
    if (ta) {
      ta.addEventListener("paste", (ev) => {
        try {
          const cb = ev.clipboardData;
          if (!cb) return;
          const html = cb.getData("text/html") || "";
          if (!html) {
            // Ensure plain-text paste keeps paragraphs (some sources use \r\n)
            const plain = cb.getData("text/plain") || "";
            if (!plain) return;
            const normalized = plain.replace(/\r\n/g, "\n");
            ev.preventDefault();
            insertAtCursor(ta, normalized);
            return;
          }
          const converted = htmlToBbcode(html);
          if (!converted) return;
          ev.preventDefault();
          insertAtCursor(ta, converted);
        } catch (e) {}
      });
    }

    // Default: start collapsed on mount (keeps inline composer tidy).
    // Modal composers can request startOpen=true.
    try {
      if (!startOpen) {
        body.setAttribute("hidden", "");
        box.classList.remove("is-open");
        body.style.display = "";
      }
    } catch (e) {}

    const attachments = [];

    function setError(msg) {
      if (!errBox) return;
      errBox.textContent = msg ? String(msg) : "";
    }

    function openFilePicker() {
      if (IA_DISCUSS.loggedIn !== "1") {
        window.dispatchEvent(new CustomEvent("ia:login_required"));
        return;
      }

      const input = document.createElement("input");
      input.type = "file";
      input.accept = "*/*";
      input.onchange = async () => {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

        attachList.innerHTML = `
          <div class="iad-attachpill is-loading">
            Uploading…
            <div class="iad-uploadbar"><div class="iad-uploadbar-fill" style="width:0%"></div></div>
          </div>
        `;
        const fill = attachList.querySelector(".iad-uploadbar-fill");
        let res = null;
        try {
          if (!API || !API.uploadFile) throw new Error("Upload API missing");
          if (typeof API.uploadFileWithProgress === "function") {
            res = await API.uploadFileWithProgress(file, (pct) => {
              try { if (fill) fill.style.width = String(pct) + "%"; } catch (e) {}
            });
          } else {
            res = await API.uploadFile(file);
          }
        } catch (e) {
          const msg = (e && e.name === "AbortError") ? "Upload timed out" : (e && e.message ? e.message : "Upload failed");
          attachList.innerHTML = `<div class="iad-attachpill is-error">${escapeHtml(msg)}</div>`;
          return;
        }

        if (!res || !res.success) {
          const msg = (res && res.data && res.data.message) ? String(res.data.message)
                    : (res && res.message) ? String(res.message)
                    : "Upload failed";
          // If server returned non-JSON content, surface a short snippet for diagnosis.
          const raw = (res && res.data && res.data.raw) ? String(res.data.raw) : "";
          if (raw) {
            try { console.warn("[IA_DISCUSS] upload failed (raw)", raw); } catch (e) {}
          }
          attachList.innerHTML = `<div class="iad-attachpill is-error">${escapeHtml(msg)}</div>`;
          return;
        }

        const a = {
          url: res.data.url,
          mime: res.data.type || "",
          filename: res.data.name || file.name,
          size: res.data.size || file.size || 0
        };

        attachments.push(a);
        renderAttachList();

        // Inline insert ONLY for documents/other (not images/videos)
        if (!isImage(a.mime, a.url) && !isVideo(a.mime, a.url)) {
          insertAtCursor(ta, `\n[url=${a.url}]${a.filename}[/url]\n`);
        }
      };
      input.click();
    }

    function setOpen(open) {
      if (open) {
        body.removeAttribute("hidden");
        box.classList.add("is-open");
      } else {
        body.setAttribute("hidden", "");
        box.classList.remove("is-open");
      }
    }

    toggle.addEventListener("click", () => {
      const isHidden = body.hasAttribute("hidden");
      setOpen(isHidden);
    });

    // Toolbar actions
    box.addEventListener("click", (e) => {
      const btn = e.target.closest("button[data-act]");
      if (!btn) return;

      const act = btn.getAttribute("data-act");
      if (!act) return;

      if (act === "b") return wrapSelection(ta, "[b]", "[/b]");
      if (act === "i") return wrapSelection(ta, "[i]", "[/i]");
      if (act === "u") return wrapSelection(ta, "[u]", "[/u]");
      if (act === "spoiler") return wrapSelection(ta, "[spoiler]", "[/spoiler]");
      if (act === "quote") return wrapSelection(ta, "[quote]", "[/quote]");
      if (act === "attach") return openFilePicker();
    });

    if (addAttachBtn) {
      addAttachBtn.addEventListener("click", () => openFilePicker());
    }

    function renderAttachList() {
      if (!attachments.length) {
        attachList.innerHTML = "";
        return;
      }

      attachList.innerHTML = `
        <div class="iad-attachrow">
          ${attachments.map((a, idx) => {
            const img = isImage(a.mime, a.url);
            const vid = isVideo(a.mime, a.url);
            const kind = vid ? "video" : (img ? "image" : "file");
            const showInsert = !img && !vid;

            return `
              <div class="iad-attachitem">
                <a class="iad-attachpill" href="${esc(a.url)}" target="_blank" rel="noopener noreferrer" title="${esc(a.filename)}">
                  ${esc(a.filename)}
                </a>

                <div class="iad-attachmini">
                  <span class="iad-attachkind">${esc(kind)}</span>
                  ${showInsert ? `<button type="button" class="iad-mini" data-iad-insert-att="${idx}">Insert</button>` : ``}
                  <button type="button" class="iad-mini is-danger" data-iad-remove-att="${idx}">Remove</button>
                </div>
              </div>
            `;
          }).join("")}
        </div>
      `;

      attachList.querySelectorAll("[data-iad-insert-att]").forEach((b) => {
        b.addEventListener("click", () => {
          const idx = parseInt(b.getAttribute("data-iad-insert-att") || "0", 10);
          const a = attachments[idx];
          if (!a) return;
          insertAtCursor(ta, `\n[url=${a.url}]${a.filename}[/url]\n`);
        });
      });

      attachList.querySelectorAll("[data-iad-remove-att]").forEach((b) => {
        b.addEventListener("click", () => {
          const idx = parseInt(b.getAttribute("data-iad-remove-att") || "0", 10);
          if (idx < 0 || idx >= attachments.length) return;
          attachments.splice(idx, 1);
          renderAttachList();
        });
      });
    }

    const submit = qs("[data-iad-submit]", box);
    submit.addEventListener("click", () => {
      setError("");
      const mode = (opts && opts.mode) || "topic";
      const bodyText = (ta.value || "").trim();
      const titleText = title ? (title.value || "").trim() : "";

      const payload = {
        mode,
        title: titleText,
        body: bodyText,
        attachments
      };

      if (opts && typeof opts.onSubmit === "function") {
        opts.onSubmit(payload, {
          error: setError,
          clear() {
            if (title) title.value = "";
            ta.value = "";
            attachments.length = 0;
            attachList.innerHTML = "";
            setOpen(false); // ✅ collapse after submit
          }
        });
      }
    });

    // Safety: if some other script expands it after we bind, re-collapse once
    // (but never fight modal startOpen).
    Promise.resolve().then(() => {
      if (!startOpen && !body.hasAttribute("hidden")) setOpen(false);
      if (startOpen) setOpen(true);
    });
  }

  window.IA_DISCUSS_UI_COMPOSER = { composerHTML, bindComposer };
})();