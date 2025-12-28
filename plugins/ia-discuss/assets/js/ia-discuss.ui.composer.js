(function () {
  "use strict";
  const { qs, esc } = window.IA_DISCUSS_CORE;
  const API = window.IA_DISCUSS_API;

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

  function makeToolbar(onAction) {
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

          <div class="iad-attachlist" data-iad-attachlist></div>

          <div class="iad-composer-actions">
            <button type="button" class="iad-btn" data-iad-submit>${mode === "topic" ? "Post" : "Reply"}</button>
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

    const attachments = [];

    toggle.addEventListener("click", () => {
      const hidden = body.hasAttribute("hidden");
      if (hidden) body.removeAttribute("hidden");
      else body.setAttribute("hidden", "");
    });

    box.addEventListener("click", async (e) => {
      const btn = e.target.closest("button[data-act]");
      if (!btn) return;

      const act = btn.getAttribute("data-act");
      if (!act) return;

      if (act === "b") return wrapSelection(ta, "[b]", "[/b]");
      if (act === "i") return wrapSelection(ta, "[i]", "[/i]");
      if (act === "u") return wrapSelection(ta, "[u]", "[/u]");
      if (act === "spoiler") return wrapSelection(ta, "[spoiler]", "[/spoiler]");
      if (act === "quote") return wrapSelection(ta, "[quote]", "[/quote]");

      if (act === "attach") {
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

          attachList.innerHTML = `<div class="iad-attachpill is-loading">Uploading…</div>`;
          const res = await API.uploadFile(file);

          if (!res || !res.success) {
            attachList.innerHTML = `<div class="iad-attachpill is-error">Upload failed</div>`;
            return;
          }

          attachments.push({
            url: res.data.url,
            mime: res.data.type || "",
            filename: res.data.name || file.name,
            size: res.data.size || file.size || 0
          });

          renderAttachList();
        };
        input.click();
      }
    });

    function renderAttachList() {
      attachList.innerHTML = attachments.map((a) => `
        <span class="iad-attachpill" title="${esc(a.filename)}">${esc(a.filename)}</span>
      `).join("");
    }

    const submit = qs("[data-iad-submit]", box);
    submit.addEventListener("click", () => {
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
          clear() {
            if (title) title.value = "";
            ta.value = "";
            attachments.length = 0;
            attachList.innerHTML = "";
            body.setAttribute("hidden", "");
          }
        });
      }
    });
  }

  window.IA_DISCUSS_UI_COMPOSER = { composerHTML, bindComposer };
})();
