"use strict";
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
;
