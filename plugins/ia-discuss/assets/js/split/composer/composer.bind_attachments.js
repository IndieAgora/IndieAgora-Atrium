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
    let submitting = false;
    submit.addEventListener("click", () => {
      if (submitting) return;
      submitting = true;
      try { submit.disabled = true; submit.classList.add("is-busy"); } catch(e) {}

      setError("");
      const mode = (opts && opts.mode) || "topic";
      const bodyText = (ta.value || "").trim();
      const titleText = title ? (title.value || "").trim() : "";

      const notifyEl = qs('[data-iad-notify]', box);
      const notifyVal = notifyEl ? (notifyEl.checked ? 1 : 0) : 1;

      const payload = {
        mode,
        title: titleText,
        body: bodyText,
        notify: (mode === 'topic') ? notifyVal : 1,
        attachments
      };

      if (opts && typeof opts.onSubmit === "function") {
        const maybePromise = opts.onSubmit(payload, {
          error: setError,
          setError,
          clear() {
            if (title) title.value = "";
            ta.value = "";
            attachments.length = 0;
            attachList.innerHTML = "";
            try { draftClear(); } catch (e) {}
            setOpen(false); // ✅ collapse after submit
          }
        });

        if (maybePromise && typeof maybePromise.then === "function") {
          maybePromise.finally(() => {
            submitting = false;
            try { submit.disabled = false; submit.classList.remove("is-busy"); } catch(e) {}
          });
        } else {
          // If onSubmit didn't return a promise, unlock after a short tick.
          setTimeout(() => {
            submitting = false;
            try { submit.disabled = false; submit.classList.remove("is-busy"); } catch(e) {}
          }, 800);
        }
      }
    });

    // Safety: if some other script expands it after we bind, re-collapse once
    // (but never fight modal startOpen).
    Promise.resolve().then(() => {
      if (!startOpen && !body.hasAttribute("hidden")) setOpen(false);
      if (startOpen) setOpen(true);
    });
  }
