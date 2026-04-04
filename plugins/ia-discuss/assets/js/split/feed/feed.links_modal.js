  function ensureLinksModal() {
    let m = document.querySelector("[data-iad-linksmodal]");
    if (m) return m;

    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="iad-linksmodal" data-iad-linksmodal hidden>
        <div class="iad-linksmodal-backdrop" data-iad-linksmodal-close></div>
        <div class="iad-linksmodal-sheet" role="dialog" aria-modal="true" aria-label="Links">
          <div class="iad-linksmodal-top">
            <div class="iad-linksmodal-title" data-iad-linksmodal-title>Links</div>
            <button class="iad-x" type="button" data-iad-linksmodal-close aria-label="Close">×</button>
          </div>
          <div class="iad-linksmodal-body" data-iad-linksmodal-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap.firstElementChild);
    m = document.querySelector("[data-iad-linksmodal]");

    m.querySelectorAll("[data-iad-linksmodal-close]").forEach((x) => {
      x.addEventListener("click", () => m.setAttribute("hidden", ""));
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && m && !m.hasAttribute("hidden")) {
        m.setAttribute("hidden", "");
      }
    });

    return m;
  }

  function openLinksModal(urls, topicTitle) {
    const m = ensureLinksModal();
    const title = m.querySelector("[data-iad-linksmodal-title]");
    const body = m.querySelector("[data-iad-linksmodal-body]");

    const safe = Array.isArray(urls) ? urls.filter(Boolean).map(String) : [];
    title.textContent = topicTitle ? `Links — ${topicTitle}` : "Links";

    if (!safe.length) {
      body.innerHTML = `<div class="iad-empty">No links found.</div>`;
    } else {
      body.innerHTML = `
        <div class="iad-linkslist">
          ${safe.map((u) => {
            const label = u.length > 80 ? (u.slice(0, 80) + "…") : u;
            return `
              <a class="iad-linksitem" href="${esc(u)}" target="_blank" rel="noopener noreferrer">
                <span class="iad-linksitem-ico">↗</span>
                <span class="iad-linksitem-txt">${esc(label)}</span>
              </a>
            `;
          }).join("")}
        </div>
      `;
    }

    m.removeAttribute("hidden");
  }

  // -----------------------------
  // Video viewer (Atrium-styled)
  // -----------------------------
  function lockPageScroll(lock) {
    const cls = "iad-modal-open";
    if (lock) document.documentElement.classList.add(cls);
    else document.documentElement.classList.remove(cls);
  }

  
  function openAttachmentsModal(urls, topicTitle) {
    const m = ensureLinksModal();
    const title = m.querySelector("[data-iad-linksmodal-title]");
    const body = m.querySelector("[data-iad-linksmodal-body]");

    const safe = Array.isArray(urls) ? urls.filter(Boolean).map(String) : [];
    title.textContent = topicTitle ? `Attachments — ${topicTitle}` : "Attachments";

    if (!safe.length) {
      body.innerHTML = `<div class="iad-empty">No attachments found.</div>`;
    } else {
      body.innerHTML = `
        <div class="iad-linkslist">
          ${safe.map((u) => {
            const label = u.length > 80 ? (u.slice(0, 80) + "…") : u;
            return `
              <a class="iad-linksitem" href="${esc(u)}" target="_blank" rel="noopener noreferrer">
                <span class="iad-linksitem-ico">↗</span>
                <span class="iad-linksitem-txt">${esc(label)}</span>
              </a>
            `;
          }).join("")}
        </div>
      `;
    }

    m.removeAttribute("hidden");
    lockPageScroll(true);
  }

function ensureVideoModal() {
    let m = document.querySelector("[data-iad-videomodal]");
    if (m) return m;

    const wrap = document.createElement("div");
    wrap.innerHTML = `
      <div class="iad-videomodal" data-iad-videomodal hidden>
        <div class="iad-videomodal-backdrop" data-iad-videomodal-close></div>

        <div class="iad-videomodal-sheet" role="dialog" aria-modal="true" aria-label="Video viewer">
          <div class="iad-videomodal-top">
            <div class="iad-videomodal-title" data-iad-videomodal-title>Video</div>
            <div class="iad-videomodal-actions">
              <a class="iad-videomodal-open" data-iad-videomodal-open target="_blank" rel="noopener noreferrer">Open ↗</a>
              <button class="iad-x" type="button" data-iad-videomodal-close aria-label="Close">×</button>
            </div>
          </div>

          <div class="iad-videomodal-body" data-iad-videomodal-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap.firstElementChild);
    m = document.querySelector("[data-iad-videomodal]");

    m.querySelectorAll("[data-iad-videomodal-close]").forEach((x) => {
      x.addEventListener("click", () => closeVideoModal());
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && m && !m.hasAttribute("hidden")) {
        closeVideoModal();
      }
    });

    return m;
  }

  function closeVideoModal() {
    const m = document.querySelector("[data-iad-videomodal]");
    if (!m) return;

    const body = m.querySelector("[data-iad-videomodal-body]");
    if (body) body.innerHTML = ""; // stop playback
    m.setAttribute("hidden", "");
    lockPageScroll(false);
  }

