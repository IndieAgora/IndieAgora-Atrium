(function () {
  "use strict";

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  const VIEW_KEYS = ["wall","edit","media","activity","privacy","notifications","blocked","export"];

  const registry = {
    views: {},
    registerView(viewKey, renderer) {
      if (!viewKey || typeof renderer !== "function") return;
      this.views[viewKey] = renderer;
    }
  };

  function escapeHtml(str) {
    return (str || "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function toast(root, msg) {
    const el = qs("[data-ia-connect-toast]", root);
    if (!el) return;
    el.textContent = msg || "";
    el.classList.add("open");
    el.setAttribute("aria-hidden", "false");
    window.clearTimeout(el._t);
    el._t = window.setTimeout(() => {
      el.classList.remove("open");
      el.setAttribute("aria-hidden", "true");
    }, 1800);
  }

  function setLoading(root, on, text) {
    const overlay = qs("[data-ia-connect-loading]", root);
    const label = qs("[data-ia-connect-loading-text]", root);
    if (!overlay) return;

    overlay.classList.toggle("open", !!on);
    overlay.setAttribute("aria-hidden", on ? "false" : "true");
    if (label) label.textContent = text || (on ? "Working…" : "");
    root.classList.toggle("ia-is-busy", !!on);
  }

  function setIdentity(root) {
    const nameEl = qs("[data-ia-connect-name]", root);
    const handleEl = qs("[data-ia-connect-handle]", root);
    const bioEl = qs("[data-ia-connect-bio-text]", root);
    const avatarImg = qs("[data-ia-connect-avatar-img]", root);
    const coverImg = qs("[data-ia-connect-cover-img]", root);

    if (nameEl) nameEl.textContent = (window.IA_CONNECT && IA_CONNECT.display) ? IA_CONNECT.display : "Profile";
    if (handleEl) handleEl.textContent = (window.IA_CONNECT && IA_CONNECT.handle) ? IA_CONNECT.handle : "";

    const bio = (window.IA_CONNECT && typeof IA_CONNECT.bio === "string") ? IA_CONNECT.bio.trim() : "";
    if (bioEl) bioEl.textContent = bio ? bio : "No bio yet.";

    const aurl = (window.IA_CONNECT && IA_CONNECT.avatarUrl) ? IA_CONNECT.avatarUrl : "";
    if (avatarImg) {
      if (aurl) {
        avatarImg.src = aurl;
        avatarImg.alt = "Avatar";
        avatarImg.style.display = "block";
      } else {
        avatarImg.removeAttribute("src");
        avatarImg.alt = "";
        avatarImg.style.display = "none";
      }
    }

    const curl = (window.IA_CONNECT && IA_CONNECT.coverUrl) ? IA_CONNECT.coverUrl : "";
    if (coverImg) {
      if (curl) {
        coverImg.src = curl;
        coverImg.alt = "Cover photo";
        coverImg.style.display = "block";
      } else {
        coverImg.removeAttribute("src");
        coverImg.alt = "";
        coverImg.style.display = "none";
      }
    }

    const followBtn = qs('[data-ia-connect-action="follow"]', root);
    if (followBtn) {
      followBtn.disabled = true;
      followBtn.title = "You can’t follow yourself.";
    }

    const msgBtn = qs('[data-ia-connect-action="message"]', root);
    if (msgBtn) {
      msgBtn.disabled = true;
      msgBtn.title = "Messaging is provided by a separate plugin.";
    }
  }

  function setActiveView(root, viewKey, ctx) {
    if (!VIEW_KEYS.includes(viewKey)) viewKey = "wall";

    qsa("[data-ia-connect-view-btn]", root).forEach(btn => {
      const active = btn.getAttribute("data-ia-connect-view-btn") === viewKey;
      btn.classList.toggle("active", active);
      btn.setAttribute("aria-selected", active ? "true" : "false");
    });

    qsa("[data-ia-connect-view]", root).forEach(panel => {
      const key = panel.getAttribute("data-ia-connect-view");
      const active = key === viewKey;
      panel.hidden = !active;
      if (active) renderView(viewKey, ctx, panel);
    });
  }

  // IMPORTANT: keep canonical renderView so Wall keeps Composer/Feed slots.
  function renderView(viewKey, ctx, panel) {
    if (registry.views[viewKey]) {
      registry.views[viewKey](ctx || {}, panel);
      return;
    }

    if (viewKey === "wall") {
      panel.innerHTML = `
        <div class="ia-connect-card">
          <div class="ia-connect-card-title">Wall</div>
          <div class="ia-connect-card-body">Composer and feed will be provided by micro-plugins.</div>
        </div>

        <div class="ia-connect-card ia-connect-slot">
          <div class="ia-connect-card-title">Composer slot</div>
          <div class="ia-connect-card-body">Future plugin renders here.</div>
        </div>

        <div class="ia-connect-card ia-connect-slot">
          <div class="ia-connect-card-title">Feed slot</div>
          <div class="ia-connect-card-body">Future plugin renders here.</div>
        </div>
      `;
      return;
    }

    panel.innerHTML = `
      <div class="ia-connect-card">
        <div class="ia-connect-card-title">${escapeHtml(viewKey[0].toUpperCase() + viewKey.slice(1))}</div>
        <div class="ia-connect-card-body">This is a skeleton view. A future micro-plugin will render real content here.</div>
      </div>
    `;
  }

  function closeModal(root) {
    qsa("[data-ia-connect-modal]", root).forEach(m => {
      m.classList.remove("open");
      m.setAttribute("aria-hidden", "true");
    });
  }

  function fillModalBodies(root) {
    const del = qs('[data-ia-connect-modal-body="delete"]', root);
    if (del) del.innerHTML = `
      <div class="ia-connect-card">
        <div class="ia-connect-card-title">Not wired yet</div>
        <div class="ia-connect-card-body">Deletion will be implemented later.</div>
      </div>
    `;

    const deact = qs('[data-ia-connect-modal-body="deactivate"]', root);
    if (deact) deact.innerHTML = `
      <div class="ia-connect-card">
        <div class="ia-connect-card-title">Not wired yet</div>
        <div class="ia-connect-card-body">Deactivation will be implemented later.</div>
      </div>
    `;

    const edit = qs('[data-ia-connect-view="edit"]', root);
    if (edit) {
      edit.innerHTML = `
        <div class="ia-connect-card">
          <div class="ia-connect-card-title">Edit profile</div>
          <div class="ia-connect-card-body">
            <div class="ia-field">
              <div class="ia-label">Bio</div>
              <textarea class="ia-input ia-textarea" data-ia-connect-bio-input></textarea>
            </div>
            <div class="ia-row">
              <button type="button" class="ia-btn ia-btn-primary" data-ia-connect-save-bio>Save</button>
            </div>
          </div>
        </div>
      `;

      const input = qs("[data-ia-connect-bio-input]", edit);
      if (input && window.IA_CONNECT && typeof IA_CONNECT.bio === "string") {
        input.value = IA_CONNECT.bio;
      }
    }

    const privacy = qs('[data-ia-connect-view="privacy"]', root);
    if (privacy) {
      const p = (window.IA_CONNECT && IA_CONNECT.privacy) ? IA_CONNECT.privacy : {};
      privacy.innerHTML = `
        <div class="ia-connect-card">
          <div class="ia-connect-card-title">Privacy</div>
          <div class="ia-connect-card-body">
            <label class="ia-toggle">
              <input type="checkbox" data-ia-privacy="profile_public" ${p.profile_public ? "checked" : ""} />
              <span class="ia-toggle-text">
                <div>Public profile</div>
                <div class="ia-toggle-sub">Allow non-members to view your basic profile.</div>
              </span>
            </label>
            <label class="ia-toggle">
              <input type="checkbox" data-ia-privacy="show_activity" ${p.show_activity ? "checked" : ""} />
              <span class="ia-toggle-text">
                <div>Show activity</div>
                <div class="ia-toggle-sub">Show activity and status indicators.</div>
              </span>
            </label>
            <label class="ia-toggle">
              <input type="checkbox" data-ia-privacy="allow_mentions" ${p.allow_mentions ? "checked" : ""} />
              <span class="ia-toggle-text">
                <div>Allow mentions</div>
                <div class="ia-toggle-sub">Allow other users to mention you.</div>
              </span>
            </label>
            <div class="ia-row">
              <button type="button" class="ia-btn ia-btn-primary" data-ia-connect-save-privacy>Save</button>
            </div>
          </div>
        </div>
      `;
    }
  }

  async function postForm(action, formData) {
    const url = (window.IA_CONNECT && IA_CONNECT.ajaxUrl) ? IA_CONNECT.ajaxUrl : "";
    if (!url) throw new Error("No ajaxUrl");
    formData.append("action", action);

    const res = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      body: formData
    });

    return res.json();
  }

  async function saveBio(root) {
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) return false;

    const input = qs("[data-ia-connect-bio-input]", root);
    const bio = input ? input.value : "";

    const fd = new FormData();
    fd.append("nonce", IA_CONNECT.nonce);
    fd.append("bio", bio);

    setLoading(root, true, "Saving bio…");
    try {
      const json = await postForm("ia_connect_update_bio", fd);
      if (json && json.success) {
        IA_CONNECT.bio = (json.data && typeof json.data.bio === "string") ? json.data.bio : bio;
        setIdentity(root);
        toast(root, "Bio saved");
        return true;
      }
      toast(root, (json && json.data && json.data.message) ? json.data.message : "Bio save failed");
      return false;
    } finally {
      setLoading(root, false);
    }
  }

  async function savePrivacy(root) {
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) return false;

    const panel = qs('[data-ia-connect-view="privacy"]', root);
    const getVal = (key) => {
      const el = qs(`[data-ia-privacy="${key}"]`, panel);
      return el && el.checked ? "1" : "0";
    };

    const fd = new FormData();
    fd.append("nonce", IA_CONNECT.nonce);
    fd.append("profile_public", getVal("profile_public"));
    fd.append("show_activity", getVal("show_activity"));
    fd.append("allow_mentions", getVal("allow_mentions"));

    setLoading(root, true, "Saving privacy…");
    try {
      const json = await postForm("ia_connect_update_privacy", fd);
      if (json && json.success) {
        IA_CONNECT.privacy = json.data && json.data.privacy ? json.data.privacy : IA_CONNECT.privacy;
        toast(root, "Privacy saved");
        return true;
      }
      toast(root, (json && json.data && json.data.message) ? json.data.message : "Privacy save failed");
      return false;
    } finally {
      setLoading(root, false);
    }
  }

  async function uploadImage(root, kind, file) {
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) return false;

    const fd = new FormData();
    fd.append("nonce", IA_CONNECT.nonce);

    const isAvatar = kind === "avatar";
    fd.append(isAvatar ? "avatar" : "cover", file);

    setLoading(root, true, isAvatar ? "Uploading avatar…" : "Uploading cover…");
    try {
      const json = await postForm(isAvatar ? "ia_connect_upload_avatar" : "ia_connect_upload_cover", fd);

      if (json && json.success) {
        if (isAvatar && json.data && json.data.avatarUrl) IA_CONNECT.avatarUrl = json.data.avatarUrl;
        if (!isAvatar && json.data && json.data.coverUrl) IA_CONNECT.coverUrl = json.data.coverUrl;

        setIdentity(root);
        toast(root, isAvatar ? "Avatar updated" : "Cover updated");
        return true;
      }

      toast(root, (json && json.data && json.data.message) ? json.data.message : "Upload failed");
      return false;
    } finally {
      setLoading(root, false);
    }
  }

  // Full-screen viewer: zoom + pan/drag + ESC close
  function viewerApi(root) {
    const wrap  = qs("[data-ia-connect-viewer]", root);
    const img   = qs("[data-ia-connect-viewer-img]", root);
    const title = qs("[data-ia-connect-viewer-title]", root);
    const stage = qs("[data-ia-connect-viewer-stage]", root);
    const open  = qs("[data-ia-connect-viewer-open]", root);
    const dl    = qs("[data-ia-connect-viewer-download]", root);

    const state = {
      isOpen: false,
      scale: 1,
      x: 0,
      y: 0,
      dragging: false,
      dragStartX: 0,
      dragStartY: 0,
      startX: 0,
      startY: 0
    };

    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
    function apply() {
      if (!img) return;
      img.style.transform = `translate3d(${state.x}px, ${state.y}px, 0) scale(${state.scale})`;
    }
    function reset() { state.scale = 1; state.x = 0; state.y = 0; apply(); }

    function setOpen(on) {
      if (!wrap) return;
      state.isOpen = !!on;
      wrap.classList.toggle("open", !!on);
      wrap.setAttribute("aria-hidden", on ? "false" : "true");
      document.documentElement.classList.toggle("ia-connect-viewer-open", !!on);
      if (!on) reset();
    }

    function filenameFromUrl(url) {
      try {
        const u = new URL(url, window.location.href);
        const p = u.pathname || "";
        const base = p.split("/").filter(Boolean).pop() || "image";
        return base;
      } catch {
        const clean = (url || "").split("?")[0].split("#")[0];
        const parts = clean.split("/").filter(Boolean);
        return parts.pop() || "image";
      }
    }

    function openViewer(kind, src) {
      if (!wrap || !img) return;
      if (!src) return;

      if (title) title.textContent = (kind === "cover") ? "Cover photo" : "Profile picture";
      img.src = src;
      img.alt = (kind === "cover") ? "Cover photo" : "Profile picture";

      if (open) open.href = src;

      if (dl) {
        dl.href = src;
        dl.setAttribute("download", filenameFromUrl(src));
      }

      setOpen(true);
      reset();
    }

    function closeViewer() { setOpen(false); }

    function zoomBy(delta, aroundClientX, aroundClientY) {
      if (!stage) return;

      const prev = state.scale;
      const next = clamp(prev * (delta > 0 ? 1.12 : 1 / 1.12), 0.2, 6);

      const rect = stage.getBoundingClientRect();
      const cx = (typeof aroundClientX === "number") ? aroundClientX : (rect.left + rect.width / 2);
      const cy = (typeof aroundClientY === "number") ? aroundClientY : (rect.top + rect.height / 2);

      const dx = cx - (rect.left + rect.width / 2);
      const dy = cy - (rect.top + rect.height / 2);

      const k = (next / prev) - 1;
      state.x -= dx * k;
      state.y -= dy * k;

      state.scale = next;
      apply();
    }

    const btnClose = qs("[data-ia-connect-viewer-close-btn]", root);
    const bgClose  = qs("[data-ia-connect-viewer-close]", root);
    const btnIn    = qs("[data-ia-connect-viewer-zoom-in]", root);
    const btnOut   = qs("[data-ia-connect-viewer-zoom-out]", root);
    const btnReset = qs("[data-ia-connect-viewer-reset]", root);

    if (btnClose) btnClose.addEventListener("click", closeViewer);
    if (bgClose)  bgClose.addEventListener("click", closeViewer);
    if (btnIn)    btnIn.addEventListener("click", () => zoomBy(+1));
    if (btnOut)   btnOut.addEventListener("click", () => zoomBy(-1));
    if (btnReset) btnReset.addEventListener("click", reset);

    if (stage) {
      stage.addEventListener("pointerdown", (e) => {
        if (!state.isOpen) return;
        state.dragging = true;
        state.dragStartX = e.clientX;
        state.dragStartY = e.clientY;
        state.startX = state.x;
        state.startY = state.y;
        stage.setPointerCapture(e.pointerId);
        stage.classList.add("dragging");
      });

      stage.addEventListener("pointermove", (e) => {
        if (!state.dragging) return;
        const dx = e.clientX - state.dragStartX;
        const dy = e.clientY - state.dragStartY;
        state.x = state.startX + dx;
        state.y = state.startY + dy;
        apply();
      });

      stage.addEventListener("pointerup", () => {
        state.dragging = false;
        stage.classList.remove("dragging");
      });

      stage.addEventListener("pointercancel", () => {
        state.dragging = false;
        stage.classList.remove("dragging");
      });

      stage.addEventListener("wheel", (e) => {
        if (!state.isOpen) return;
        e.preventDefault();
        zoomBy(e.deltaY < 0 ? +1 : -1, e.clientX, e.clientY);
      }, { passive: false });
    }

    document.addEventListener("keydown", (e) => {
      if (!state.isOpen) return;
      if (e.key === "Escape") closeViewer();
    });

    return { openViewer, closeViewer };
  }

  function onMenuAction(ev) {
    // reserved for integration with ia-profile-menu / atrium events
  }

  function boot() {
    const root = qs("#ia-connect-root");
    if (!root) return;

    const viewer = viewerApi(root);

    window.IA_CONNECT_API = window.IA_CONNECT_API || {};
    window.IA_CONNECT_API.registerView = registry.registerView.bind(registry);

    setIdentity(root);
    fillModalBodies(root);
    setActiveView(root, "wall", { source: "init" });

    qsa("[data-ia-connect-view-btn]", root).forEach(btn => {
      btn.addEventListener("click", () => {
        const viewKey = btn.getAttribute("data-ia-connect-view-btn");
        setActiveView(root, viewKey, { source: "subtabs" });
      });
    });

    root.addEventListener("click", async (e) => {
      if (root.classList.contains("ia-is-busy")) return;

      if (e.target.closest("[data-ia-connect-close]")) {
        closeModal(root);
        return;
      }

      if (e.target.closest("[data-ia-connect-edit-bio]")) {
        setActiveView(root, "edit", { source: "edit_bio_button" });
        return;
      }

      if (e.target.closest("[data-ia-connect-save-bio]")) {
        await saveBio(root);
        return;
      }

      if (e.target.closest("[data-ia-connect-save-privacy]")) {
        await savePrivacy(root);
        return;
      }

      // AVATAR:
      // - click anywhere on avatar => open viewer (if image exists)
      // - click the INNER Change button only => upload
      const avatarBtn = e.target.closest("[data-ia-connect-avatar-btn]");
      if (avatarBtn) {
        const clickedChange = e.target.closest(".ia-connect-avatar-overlay-btn");
        if (clickedChange) {
          const fileInput = qs("[data-ia-connect-avatar-file]", root);
          if (!fileInput) return;
          fileInput.value = "";
          fileInput.click();
          return;
        }

        const src = (window.IA_CONNECT && IA_CONNECT.avatarUrl) ? IA_CONNECT.avatarUrl : "";
        if (src) {
          viewer.openViewer("avatar", src);
          return;
        }

        // no avatar yet => fallback to upload
        const fileInput = qs("[data-ia-connect-avatar-file]", root);
        if (!fileInput) return;
        fileInput.value = "";
        fileInput.click();
        return;
      }

      // COVER:
      // - click anywhere on cover => open viewer (if image exists)
      // - click the overlay text => upload
      const coverBtn = e.target.closest("[data-ia-connect-cover-btn]");
      if (coverBtn) {
        const clickedChange = e.target.closest(".ia-connect-cover-overlay");
        if (clickedChange) {
          const fileInput = qs("[data-ia-connect-cover-file]", root);
          if (!fileInput) return;
          fileInput.value = "";
          fileInput.click();
          return;
        }

        const src = (window.IA_CONNECT && IA_CONNECT.coverUrl) ? IA_CONNECT.coverUrl : "";
        if (src) {
          viewer.openViewer("cover", src);
          return;
        }

        const fileInput = qs("[data-ia-connect-cover-file]", root);
        if (!fileInput) return;
        fileInput.value = "";
        fileInput.click();
        return;
      }
    });

    const avatarInput = qs("[data-ia-connect-avatar-file]", root);
    if (avatarInput) {
      avatarInput.addEventListener("change", async () => {
        const f = avatarInput.files && avatarInput.files[0] ? avatarInput.files[0] : null;
        if (!f) return;
        await uploadImage(root, "avatar", f);
      });
    }

    const coverInput = qs("[data-ia-connect-cover-file]", root);
    if (coverInput) {
      coverInput.addEventListener("change", async () => {
        const f = coverInput.files && coverInput.files[0] ? coverInput.files[0] : null;
        if (!f) return;
        await uploadImage(root, "cover", f);
      });
    }

    window.addEventListener("ia_atrium:profile", () => {
      setActiveView(root, "wall", { source: "ia_atrium:profile" });
    });

    window.addEventListener("ia_profile:action", onMenuAction);
    window.addEventListener("ia_connect:profileMenu", onMenuAction);
  }

  document.addEventListener("DOMContentLoaded", boot);
})();
