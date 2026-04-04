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
    // Prefill (used for editing existing posts / quote)
    const prefillBody = (opts && typeof opts.prefillBody === "string") ? opts.prefillBody : "";

    // Draft memory (modal composer is mounted/unmounted on close, so persistence must live outside the DOM).
    const mode = (opts && opts.mode) ? String(opts.mode) : "topic";
    const topicId = (opts && opts.topicId != null) ? (parseInt(opts.topicId, 10) || 0) : 0;
    const hasDraft = (mode === "reply" && topicId > 0 && typeof window !== "undefined" && window.localStorage);

    const draftKey = hasDraft ? ("ia_discuss_reply_draft_v1_" + String(topicId)) : "";
    function draftLoad() {
      if (!hasDraft) return "";
      try { return String(localStorage.getItem(draftKey) || ""); } catch (e) { return ""; }
    }
    function draftSave(val) {
      if (!hasDraft) return;
      try { localStorage.setItem(draftKey, String(val || "")); } catch (e) {}
    }
    function draftClear() {
      if (!hasDraft) return;
      try { localStorage.removeItem(draftKey); } catch (e) {}
    }

    // Restore draft first, then merge any prefillBody (e.g., quote) without overwriting what the user already typed.
    if (ta) {
      const existingDraft = draftLoad();
      if (existingDraft) {
        ta.value = existingDraft;
      } else if (prefillBody) {
        ta.value = prefillBody;
      }

      // If both exist, append the prefill (quote) instead of overwriting.
      if (prefillBody && existingDraft) {
        const pb = String(prefillBody || "").trim();
        const cur = String(ta.value || "");
        if (pb && cur.indexOf(pb) === -1) {
          ta.value = (cur ? (cur + "\n\n") : "") + pb;
        }
      }
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

    // Draft autosave (reply mode only)
    if (ta && hasDraft) {
      const saveNow = () => { try { draftSave(ta.value || ""); } catch (e) {} };
      ta.addEventListener("input", saveNow);
      ta.addEventListener("change", saveNow);
      // Save once on bind in case a prefill/merge happened.
      saveNow();
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

