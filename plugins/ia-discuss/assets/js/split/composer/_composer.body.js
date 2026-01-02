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
    if (!box) return;

    var toggle = qs("[data-iad-toggle]", box);
    var body = qs("[data-iad-body]", box);
    var ta = qs("[data-iad-bodytext]", box);
    var title = qs("[data-iad-title]", box);
    var attachList = qs("[data-iad-attachlist]", box);
    var errBox = qs("[data-iad-error]", box);
    var addAttachBtn = qs("[data-iad-add-attach]", box);

    var startOpen = !!(opts && opts.startOpen);


    // Prefill (used for editing existing posts)
    var prefillBody = (opts && typeof opts.prefillBody === "string") ? opts.prefillBody : "";
    if (ta && prefillBody) {
      ta.value = prefillBody;
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

    var attachments = [];

    function setError(msg) {
      if (!errBox) return;
      errBox.textContent = msg ? String(msg) : "";
    }

    function openFilePicker() {
      if (IA_DISCUSS.loggedIn !== "1") {
        window.dispatchEvent(new CustomEvent("ia:login_required"));
        return;
      }

      var input = document.createElement("input");
      input.type = "file";
      input.accept = "*/*";
      input.onchange = async () => {
        if (!input.files || !input.files[0]) return;
        var file = input.files[0];

        attachList.innerHTML = `<div class="iad-attachpill is-loading">Uploading…</div>`;
        var res = await API.uploadFile(file);

        if (!res || !res.success) {
          attachList.innerHTML = `<div class="iad-attachpill is-error">Upload failed</div>`;
          return;
        }

        var a = {
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
      var isHidden = body.hasAttribute("hidden");
      setOpen(isHidden);
    });

    // Toolbar actions
    box.addEventListener("click", (e) => {
      var btn = e.target.closest("button[data-act]");
      if (!btn) return;

      var act = btn.getAttribute("data-act");
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
            var img = isImage(a.mime, a.url);
            var vid = isVideo(a.mime, a.url);
            var kind = vid ? "video" : (img ? "image" : "file");
            var showInsert = !img && !vid;

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
          var idx = parseInt(b.getAttribute("data-iad-insert-att") || "0", 10);
          var a = attachments[idx];
          if (!a) return;
          insertAtCursor(ta, `\n[url=${a.url}]${a.filename}[/url]\n`);
        });
      });

      attachList.querySelectorAll("[data-iad-remove-att]").forEach((b) => {
        b.addEventListener("click", () => {
          var idx = parseInt(b.getAttribute("data-iad-remove-att") || "0", 10);
          if (idx < 0 || idx >= attachments.length) return;
          attachments.splice(idx, 1);
          renderAttachList();
        });
      });
    }

    var submit = qs("[data-iad-submit]", box);
    submit.addEventListener("click", () => {
      setError("");
      var mode = (opts && opts.mode) || "topic";
      var bodyText = (ta.value || "").trim();
      var titleText = title ? (title.value || "").trim() : "";

      var payload = {
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
