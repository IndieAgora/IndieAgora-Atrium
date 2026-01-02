"use strict";
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

;
