(function () {
  "use strict";

  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.from((root || document).querySelectorAll(sel)); }

  function getUrlParam(key) {
    try { return new URL(window.location.href).searchParams.get(key); } catch (e) { return null; }
  }
  function setUrlParam(key, value) {
    try {
      const url = new URL(window.location.href);
      if (value === null || value === undefined || value === "") url.searchParams.delete(key);
      else url.searchParams.set(key, value);
      window.history.replaceState({}, "", url.toString());
    } catch (e) {}
  }

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

  
  function getLastRequestedProfile() {
    // Priority: URL params (persist across redirects) -> localStorage (set by Discuss)
    let pid = parseInt(getUrlParam("ia_profile") || "0", 10) || 0;
    let uname = (getUrlParam("ia_profile_name") || "").trim();

    if (!pid && !uname) {
      try {
        const raw = localStorage.getItem("ia_connect_last_profile");
        if (raw) {
          const obj = JSON.parse(raw);
          pid = parseInt(obj && obj.user_id ? String(obj.user_id) : "0", 10) || 0;
          uname = (obj && obj.username ? String(obj.username) : "").trim();
        }
      } catch (e) {}
    }

    if (!pid && !uname) return null;
    return { user_id: pid, username: uname };
  }

  function isViewingSelf(targetWpUserId) {
    return !!(window.IA_CONNECT && IA_CONNECT.userId && targetWpUserId && (parseInt(IA_CONNECT.userId, 10) === parseInt(targetWpUserId, 10)));
  }

  async function fetchProfile(target) {
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) return { success: false, data: { message: "Login required" } };

    const fd = new FormData();
    fd.append("nonce", IA_CONNECT.nonce);
    if (target && target.user_id) fd.append("phpbb_user_id", String(target.user_id));
    if (target && target.username) fd.append("username", String(target.username));

    return postForm("ia_connect_get_profile", fd);
  }

  function applyProfileToUI(root, profile) {
    const p = profile || {};

    // identity header
    const nameEl = qs("[data-ia-connect-name]", root);
    if (nameEl) nameEl.textContent = p.display || "Profile";

    const handleEl = qs("[data-ia-connect-handle]", root);
    if (handleEl) handleEl.textContent = p.handle || "";

    // bio panel (editable textarea lives in modal body)
    const bioInput = qs("[data-ia-connect-bio-input]", root);
    if (bioInput) bioInput.value = p.bio || "";

    // media
    const aImg = qs("[data-ia-connect-avatar-img]", root);
    if (aImg) aImg.src = p.avatarUrl || "";

    const cImg = qs("[data-ia-connect-cover-img]", root);
    if (cImg) cImg.src = p.coverUrl || "";

    // Update global-like cache so existing actions (viewer, uploads) behave
    if (window.IA_CONNECT) {
      IA_CONNECT.display = p.display || IA_CONNECT.display;
      IA_CONNECT.handle = p.handle || IA_CONNECT.handle;
      IA_CONNECT.bio = p.bio || IA_CONNECT.bio;
      IA_CONNECT.avatarUrl = p.avatarUrl || IA_CONNECT.avatarUrl;
      IA_CONNECT.coverUrl = p.coverUrl || IA_CONNECT.coverUrl;
      IA_CONNECT._viewingWpUserId = p.wp_user_id || 0;
      IA_CONNECT._viewingUsername = p.username || "";
    }

    // Disable edit/upload actions if not self
    const self = isViewingSelf(p.wp_user_id || 0);

    qsa("[data-ia-connect-avatar-btn],[data-ia-connect-cover-btn]", root).forEach(el => {
      if (!self) {
        el.setAttribute("aria-disabled", "true");
      } else {
        el.removeAttribute("aria-disabled");
      }
    });

    // Hide change overlays for non-self
    const coverOverlay = qs(".ia-connect-cover-overlay", root);
    if (coverOverlay) coverOverlay.style.display = self ? "" : "none";

    const avatarOverlay = qs(".ia-connect-avatar-overlay", root);
    if (avatarOverlay) avatarOverlay.style.display = self ? "" : "none";

    // Follow/Message buttons
    const followBtn = qs('[data-ia-connect-action="follow"]', root);
    const msgBtn = qs('[data-ia-connect-action="message"]', root);

    const viewingId = parseInt(p.wp_user_id || 0, 10) || 0;
    const canAct = !!viewingId && !self;

    if (followBtn) {
      followBtn.disabled = !canAct;
      followBtn.textContent = p.isFollowing ? "Unfollow" : "Follow";
      followBtn.setAttribute("data-ia-connect-following", p.isFollowing ? "1" : "0");
      followBtn.setAttribute("data-ia-connect-follow-target", String(viewingId));
    }
    if (msgBtn) {
      // Message is implemented by ia-message; keep disabled for self, enabled for others.
      msgBtn.disabled = !canAct;
      // Store target identifiers for deep-link into ia-message.
      const toPhpbb = parseInt(p.phpbb_user_id || 0, 10) || 0;
      msgBtn.setAttribute("data-ia-connect-msg-to-phpbb", String(toPhpbb));
      // Also store WP user id as fallback if phpbb id is missing.
      msgBtn.setAttribute("data-ia-connect-msg-to-wp", String(viewingId));
      msgBtn.setAttribute("data-ia-connect-msg-to-name", String(p.username || ""));
    }

    // Stash on root for debugging/other plugins
    root.setAttribute("data-ia-connect-viewing-wp", String(viewingId || 0));
    root.setAttribute("data-ia-connect-viewing-phpbb", String(parseInt(p.phpbb_user_id || 0, 10) || 0));
  }

  
  function setUnavailableProfile(root, message) {
    const nameEl = qs("[data-ia-connect-name]", root);
    if (nameEl) nameEl.textContent = "User unavailable";

    const handleEl = qs("[data-ia-connect-handle]", root);
    if (handleEl) handleEl.textContent = "";

    const bioText = qs("[data-ia-connect-bio-text]", root);
    if (bioText) bioText.textContent = message || "User not available.";

    const followBtn = qs('[data-ia-connect-action="follow"]', root);
    const msgBtn = qs('[data-ia-connect-action="message"]', root);
    if (followBtn) followBtn.disabled = true;
    if (msgBtn) msgBtn.disabled = true;

    root.setAttribute("data-ia-connect-viewing-wp", "0");
  }

  async function toggleFollow(root, targetWpUserId) {
    const fd = new FormData();
    fd.append("nonce", IA_CONNECT.nonce);
    fd.append("target_wp_user_id", String(targetWpUserId));

    setLoading(root, true, "Updating follow…");
    try {
      const json = await postForm("ia_connect_follow_toggle", fd);
      return json;
    } finally {
      setLoading(root, false);
    }
  }

async function openProfile(root, target, source) {
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) {
      // Atrium handles the auth modal; keep intent persisted.
      if (target && target.user_id) setUrlParam("ia_profile", String(target.user_id));
      if (target && target.username) setUrlParam("ia_profile_name", String(target.username));
      return;
    }

    setLoading(root, true, "Loading profile…");
    try {
      const json = await fetchProfile(target || {});
      if (json && json.success && json.data && json.data.profile) {
        applyProfileToUI(root, json.data.profile);
        setActiveView(root, "wall", { source: source || "openProfile" });
        return;
      }
      toast(root, (json && json.data && json.data.message) ? json.data.message : "Failed to load profile");
    } finally {
      setLoading(root, false);
    }
  }

  function showLoggedOutGate(root) {
    // Minimal, non-invasive message; Atrium auth modal is the real gate.
    const wall = qs('[data-ia-connect-view="wall"]', root);
    if (!wall) return;
    wall.innerHTML = `
      <div class="ia-connect-card">
        <div class="ia-connect-card-title">Connect</div>
        <div class="ia-connect-card-body">Please log in or register to view profiles.</div>
      </div>
    `;
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

  
  // ---------------------------
  // Privacy view (self only)
  // ---------------------------
  function renderPrivacyView(ctx, panel) {
    const viewingId = (window.IA_CONNECT && IA_CONNECT._viewingWpUserId) ? IA_CONNECT._viewingWpUserId : 0;

    // Only allow editing privacy on your own profile.
    if (!isViewingSelf(viewingId)) {
      panel.innerHTML = `
        <div class="ia-connect-card">
          <div class="ia-connect-card-title">Privacy</div>
          <div class="ia-connect-card-body">Privacy settings are only available on your own profile.</div>
        </div>`;
      return;
    }

    const privacy = (window.IA_CONNECT && IA_CONNECT.privacy && typeof IA_CONNECT.privacy === "object") ? IA_CONNECT.privacy : {};
    const vis = (privacy.profile_visibility || (privacy.hide_profile ? "hidden" : "public"));
    const discourage = !!privacy.discourage_search;

    panel.innerHTML = `
      <div class="ia-connect-card">
        <div class="ia-connect-card-title">Privacy</div>
        <div class="ia-connect-card-body">

          <div class="ia-connect-field">
            <div class="ia-connect-muted" style="margin-bottom:8px;">Profile visibility</div>

            <label class="ia-connect-radirow">
              <input type="radio" name="ia_connect_vis" value="public" data-ia-connect-privacy-vis ${vis === "public" ? "checked" : ""}/>
              <span>Everyone</span>
            </label>

            <label class="ia-connect-radirow">
              <input type="radio" name="ia_connect_vis" value="friends" data-ia-connect-privacy-vis ${vis === "friends" ? "checked" : ""}/>
              <span>Only friends</span>
              <span class="ia-connect-muted" style="margin-left:6px;">(friends = mutual follows)</span>
            </label>

            <label class="ia-connect-radirow">
              <input type="radio" name="ia_connect_vis" value="hidden" data-ia-connect-privacy-vis ${vis === "hidden" ? "checked" : ""}/>
              <span>Hidden from everyone</span>
            </label>

            <div class="ia-connect-muted" style="margin-top:8px;">
              If your profile is hidden (or friends-only), you won’t appear in user search and direct profile links will show:
              <em>User not available due to their privacy settings.</em>
            </div>
          </div>

          <hr class="ia-connect-hr" />

          <label class="ia-connect-checkrow">
            <input type="checkbox" data-ia-connect-privacy-noindex ${discourage ? "checked" : ""}/>
            <span>Discourage search engines from my profile</span>
          </label>
          <div class="ia-connect-muted">
            This adds a <em>noindex,nofollow</em> robots rule when your profile is viewed.
          </div>

        </div>
      </div>`;

    const visInputs = qsa("[data-ia-connect-privacy-vis]", panel);
    const cbNoindex = qs("[data-ia-connect-privacy-noindex]", panel);
    if (!visInputs.length || !cbNoindex) return;

    async function savePrivacy(nextVis, nextNoindex) {
      const fd = new FormData();
      fd.append("nonce", IA_CONNECT.nonce);
      fd.append("profile_visibility", nextVis);
      fd.append("discourage_search", nextNoindex ? "1" : "0");

      const root = document.getElementById("ia-connect-root") || panel.closest("[data-ia-connect-root]") || document.body;

      setLoading(root, true, "Saving privacy…");
      try {
        const json = await postForm("ia_connect_update_privacy", fd);
        if (json && json.success) {
          IA_CONNECT.privacy = (json.data && json.data.privacy) ? json.data.privacy : Object.assign({}, privacy, { profile_visibility: nextVis, discourage_search: nextNoindex ? 1 : 0, hide_profile: (nextVis === "hidden") ? 1 : 0 });
          toast(root, "Privacy updated.");
          return;
        }
        toast(root, (json && json.data && json.data.message) ? json.data.message : "Privacy update failed.");
      } catch (e) {
        toast(root, "Privacy update failed.");
      } finally {
        setLoading(root, false);
      }

      // If save failed, reload current UI state from IA_CONNECT.privacy.
      const p = (window.IA_CONNECT && IA_CONNECT.privacy) ? IA_CONNECT.privacy : {};
      const curVis = (p.profile_visibility || (p.hide_profile ? "hidden" : "public"));
      visInputs.forEach(i => { i.checked = (i.value === curVis); });
      cbNoindex.checked = !!p.discourage_search;
    }

    visInputs.forEach(r => {
      r.addEventListener("change", () => {
        const nextVis = visInputs.find(x => x.checked)?.value || "public";
        const nextNoindex = !!cbNoindex.checked;
        savePrivacy(nextVis, nextNoindex);
      });
    });

    cbNoindex.addEventListener("change", () => {
      const nextVis = visInputs.find(x => x.checked)?.value || "public";
      const nextNoindex = !!cbNoindex.checked;
      savePrivacy(nextVis, nextNoindex);
    });
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
              <input type="checkbox" data-ia-privacy="hide_profile" ${p.hide_profile ? "checked" : ""} />
              <span class="ia-toggle-text">
                <div>Hide my profile</div>
                <div class="ia-toggle-sub">Hide from search and direct profile access.</div>
              </span>
            </label>
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
    // Back-compat: keep the old function name, but route through the unified account endpoint.
    return saveAccount(root, { only: "bio" });
  }

  function cleanUsernameForLogin(raw) {
    const s = String(raw || "").trim().replace(/\s+/g, "_").toLowerCase();
    // Keep it close to WP sanitize_user (ASCII-ish)
    return s.replace(/[^a-z0-9_\-\.@]/g, "");
  }

  function renderEditView(root, panel) {
    const viewingWp = parseInt(root.getAttribute("data-ia-connect-viewing-wp") || "0", 10) || 0;
    const meWp = (window.IA_CONNECT && IA_CONNECT.userId) ? (parseInt(IA_CONNECT.userId, 10) || 0) : 0;
    const self = !!(viewingWp && meWp && viewingWp === meWp);

    if (!self) {
      panel.innerHTML = `
        <div class="ia-connect-card">
          <div class="ia-connect-card-title">Edit Profile</div>
          <div class="ia-connect-muted">You can only edit your own profile.</div>
        </div>
      `;
      return;
    }

    const uname = (window.IA_CONNECT && IA_CONNECT.username) ? String(IA_CONNECT.username) : "";
    const email = (window.IA_CONNECT && IA_CONNECT.email) ? String(IA_CONNECT.email) : "";
    const bio = (window.IA_CONNECT && IA_CONNECT.bio) ? String(IA_CONNECT.bio) : "";

    panel.innerHTML = `
      <div class="ia-connect-card">
        <div class="ia-connect-card-title">Edit</div>

        <label class="ia-field">
          <div class="ia-label">Bio</div>
          <textarea class="ia-textarea" rows="5" data-ia-connect-edit-bio placeholder="Write something about yourself…">${escapeHtml(bio)}</textarea>
        </label>

        <label class="ia-field" style="margin-top:14px;">
          <div class="ia-label">Username</div>
          <input type="text" class="ia-input" data-ia-connect-edit-username value="${escapeHtml(uname)}" />
          <div class="ia-connect-muted" style="margin-top:6px;">Changing username syncs across WordPress, phpBB and PeerTube.</div>
        </label>

        <label class="ia-field" style="margin-top:14px;">
          <div class="ia-label">Email</div>
          <input type="email" class="ia-input" data-ia-connect-edit-email value="${escapeHtml(email)}" />
          <div class="ia-connect-muted" style="margin-top:6px;">Changing email requires verification. We’ll email the new address.</div>
        </label>

        <div class="ia-field" style="margin-top:14px;">
          <div class="ia-label">Password</div>
          <button type="button" class="ia-btn ia-btn-secondary" data-ia-connect-password-reset>Send password reset email</button>
          <div class="ia-connect-muted" style="margin-top:6px;">You’ll receive a reset link at your current verified email address.</div>
        </div>

        <div style="margin-top:16px; display:flex; gap:10px; align-items:center;">
          <button type="button" class="ia-btn ia-btn-primary" data-ia-connect-save-account>Save</button>
          <div class="ia-connect-muted" data-ia-connect-edit-status></div>
        </div>
      </div>
    `;
  }

  async function saveAccount(root, opts) {
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) return false;

    const only = opts && opts.only ? String(opts.only) : "";
    const panel = qs('[data-ia-connect-view="edit"]', root);
    if (!panel) return false;

    const uEl = qs("[data-ia-connect-edit-username]", panel);
    const eEl = qs("[data-ia-connect-edit-email]", panel);
    const bEl = qs("[data-ia-connect-edit-bio]", panel);

    const usernameRaw = uEl ? (uEl.value || "") : "";
    const email = eEl ? (eEl.value || "") : "";
    const bio = bEl ? (bEl.value || "") : "";

    const fd = new FormData();
    fd.append("nonce", IA_CONNECT.nonce);
    if (!only || only === "username") fd.append("username", usernameRaw);
    if (!only || only === "email") fd.append("email", email);
    if (!only || only === "bio") fd.append("bio", bio);

    setLoading(root, true, "Saving…");
    try {
      const json = await postForm("ia_connect_update_account", fd);
      if (!json || !json.success) {
        toast(root, (json && json.data && json.data.message) ? json.data.message : "Save failed");
        return false;
      }

      const data = json.data || {};

      if (typeof data.bio === "string") {
        IA_CONNECT.bio = data.bio;
        const bioEl = qs("[data-ia-connect-bio-text]", root);
        if (bioEl) bioEl.textContent = (IA_CONNECT.bio || "").trim() ? IA_CONNECT.bio : "No bio yet.";
      }

      if (typeof data.username === "string" && data.username.trim()) {
        // data.username is the display username; the login is a clean variant.
        IA_CONNECT.display = data.username;
        IA_CONNECT.username = cleanUsernameForLogin(data.username);
        IA_CONNECT.handle = "agorian/" + IA_CONNECT.username;

        const nameEl = qs("[data-ia-connect-name]", root);
        if (nameEl) nameEl.textContent = IA_CONNECT.display;
        const handleEl = qs("[data-ia-connect-handle]", root);
        if (handleEl) handleEl.textContent = IA_CONNECT.handle;
      }

      if (typeof data.email === "string") {
        // If verification is required, we keep IA_CONNECT.email as-is until verified.
        if (data.email_verification_sent) {
          toast(root, "Verification email sent");
        } else {
          IA_CONNECT.email = data.email;
        }
      }

      if (data.message && typeof data.message === "string") {
        toast(root, data.message);
      } else {
        toast(root, "Saved");
      }

      return true;
    } catch (e) {
      toast(root, "Save failed");
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
    fd.append("hide_profile", getVal("hide_profile"));

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

  

  async function requestPasswordReset(root) {
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) return false;

    const fd = new FormData();
    fd.append("action", "ia_connect_password_reset");
    fd.append("nonce", IA_CONNECT.nonce);

    setLoading(root, true, "Sending reset email…");
    try {
      const res = await fetch(IA_CONNECT.ajaxUrl, { method: "POST", body: fd });
      const json = await res.json();
      if (!json || !json.success) {
        toast(root, (json && json.data && json.data.message) ? json.data.message : "Could not send reset email");
        return false;
      }
      toast(root, (json.data && json.data.message) ? json.data.message : "Reset email sent");
      return true;
    } catch (e) {
      toast(root, "Network error");
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

        // Only update the displayed media if we're currently viewing our own profile.
        const viewingWp = parseInt(root.getAttribute("data-ia-connect-viewing-wp") || "0", 10) || 0;
        const meWp = (window.IA_CONNECT && IA_CONNECT.userId) ? (parseInt(IA_CONNECT.userId, 10) || 0) : 0;
        if (viewingWp && meWp && viewingWp === meWp) {
          if (isAvatar) {
            const avatarImg = qs("[data-ia-connect-avatar-img]", root);
            if (avatarImg && IA_CONNECT.avatarUrl) {
              avatarImg.src = IA_CONNECT.avatarUrl;
              avatarImg.style.display = "block";
            }
          } else {
            const coverImg = qs("[data-ia-connect-cover-img]", root);
            if (coverImg && IA_CONNECT.coverUrl) {
              coverImg.src = IA_CONNECT.coverUrl;
              coverImg.style.display = "block";
            }
          }
        }

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

  
  function renderUserSearchResults(root, results) {
    const box = qs("[data-ia-connect-usersearch-results]", root);
    if (!box) return;
    const items = Array.isArray(results) ? results : [];
    if (!items.length) {
      box.innerHTML = "";
      box.setAttribute("aria-hidden", "true");
      return;
    }
    box.innerHTML = items.map(r => {
      const display = String(r.display || r.username || "User");
      const uname = String(r.username || "");
      const pid = String(r.phpbb_user_id || 0);
      const avatar = String(r.avatarUrl || "");
      return `
        <button type="button" class="ia-connect-usersearch-item"
                data-ia-connect-usersearch-pick="1"
                data-user-id="${pid}"
                data-username="${uname}">
          <img class="ia-connect-usersearch-avatar" alt="" src="${avatar}">
          <div>
            <div class="ia-connect-usersearch-name">${escapeHtml(display)}</div>
            <div class="ia-connect-usersearch-username">${escapeHtml("@" + uname)}</div>
          </div>
        </button>
      `;
    }).join("");
    box.setAttribute("aria-hidden", "false");
  }

  async function userSearch(root, q) {
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) return [];
    const fd = new FormData();
    fd.append("nonce", IA_CONNECT.nonce);
    fd.append("q", String(q || ""));
    const json = await postForm("ia_connect_user_search", fd);
    if (json && json.success && json.data && Array.isArray(json.data.results)) return json.data.results;
    return [];
  }

  function bindUserSearch(root) {
    const input = qs("[data-ia-connect-usersearch-input]", root);
    if (!input) return;

    let timer = null;
    input.addEventListener("input", () => {
      if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) return;
      const q = (input.value || "").trim();
      if (timer) clearTimeout(timer);
      timer = setTimeout(async () => {
        if (!q) {
          renderUserSearchResults(root, []);
          return;
        }
        try {
          const res = await userSearch(root, q);
          renderUserSearchResults(root, res);
        } catch (e) {
          renderUserSearchResults(root, []);
        }
      }, 220);
    });

    // Pick result
    root.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-ia-connect-usersearch-pick]");
      if (!btn) return;
      const target = {
        user_id: parseInt(btn.getAttribute("data-user-id") || "0", 10) || 0,
        username: (btn.getAttribute("data-username") || "").trim()
      };
      // persist deep link
      if (target.user_id) setUrlParam("ia_profile", String(target.user_id));
      if (target.username) setUrlParam("ia_profile_name", target.username);

      renderUserSearchResults(root, []);
      if (input) input.value = "";
      openProfile(root, target, "user-search");
    });

    // Click outside closes dropdown
    document.addEventListener("click", (e) => {
      const box = qs("[data-ia-connect-usersearch-results]", root);
      if (!box) return;
      if (e.target === input || box.contains(e.target)) return;
      box.setAttribute("aria-hidden", "true");
    });
  }

function boot() {
    const root = qs("#ia-connect-root");
    if (!root) return;

    const viewer = viewerApi(root);

    window.IA_CONNECT_API = window.IA_CONNECT_API || {};
    window.IA_CONNECT_API.registerView = registry.registerView.bind(registry);

    // If logged out, do not show profile content.
    if (!window.IA_CONNECT || !IA_CONNECT.isLoggedIn) {
      showLoggedOutGate(root);
    }

    setIdentity(root);
    fillModalBodies(root);
    bindUserSearch(root);
    // Follow toggle
    const followBtn = qs('[data-ia-connect-action="follow"]', root);
    if (followBtn) {
      followBtn.addEventListener("click", async function () {
        try {
          const targetId = parseInt(followBtn.getAttribute("data-ia-connect-follow-target") || "0", 10) || 0;
          if (!targetId) return;
          if (isViewingSelf(targetId)) return;

          const json = await toggleFollow(root, targetId);
          if (json && json.success) {
            const isF = !!(json.data && json.data.isFollowing);
            followBtn.setAttribute("data-ia-connect-following", isF ? "1" : "0");
            followBtn.textContent = isF ? "Unfollow" : "Follow";
            toast(root, (json.data && json.data.message) ? json.data.message : (isF ? "Followed" : "Unfollowed"));
          } else {
            const msg = (json && json.data && json.data.message) ? json.data.message : "Follow failed";
            toast(root, msg);
            if (msg.indexOf("privacy") !== -1) setUnavailableProfile(root, msg);
          }
        } catch (e) {
          toast(root, "Follow failed");
        }
      });
    }

        
    // Message click is handled via delegated handler on root (button can be re-rendered).

    registry.registerView("edit", renderEditView);
    registry.registerView("privacy", renderPrivacyView);

    setActiveView(root, "wall", { source: "init" });

    qsa("[data-ia-connect-view-btn]", root).forEach(btn => {
      btn.addEventListener("click", () => {
        const viewKey = btn.getAttribute("data-ia-connect-view-btn");
        setActiveView(root, viewKey, { source: "subtabs" });
      });
    });

    root.addEventListener("click", async (e) => {
      if (root.classList.contains("ia-is-busy")) return;

      // Message -> IA Message (deep-link)
      const msgBtn = e.target.closest('[data-ia-connect-action="message"]');
      if (msgBtn) {
        // Disabled buttons don't fire clicks, but keep this guard anyway.
        if (msgBtn.disabled) return;
        try {
          const toPhpbb = parseInt(msgBtn.getAttribute("data-ia-connect-msg-to-phpbb") || "0", 10) || 0;
          const toWp    = parseInt(msgBtn.getAttribute("data-ia-connect-msg-to-wp") || "0", 10) || 0;
          const to = toPhpbb || toWp;
          if (!to) return;

          const url = new URL(window.location.href);
          url.searchParams.set("tab", "messages");
          url.searchParams.set("ia_msg_to", String(to));

          const toName = (msgBtn.getAttribute("data-ia-connect-msg-to-name") || "").trim();
          if (toName) url.searchParams.set("ia_msg_name", toName);

          // Hard navigate (do not depend on SPA router).
          window.location.href = url.toString();
        } catch (err) {
          // no-op
        }
        return;
      }

      if (e.target.closest("[data-ia-connect-close]")) {
        closeModal(root);
        return;
      }

      
      if (e.target.closest("[data-ia-connect-password-reset]")) {
        await requestPasswordReset(root);
        return;
      }

if (e.target.closest("[data-ia-connect-save-account]")) {
        await saveAccount(root);
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
        if (avatarBtn.getAttribute("aria-disabled") === "true") return;

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
        if (coverBtn.getAttribute("aria-disabled") === "true") return;

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

    window.addEventListener("ia_atrium:profile", (ev) => {
      const d = (ev && ev.detail) ? ev.detail : {};
      const target = {
        user_id: parseInt(d.userId || "0", 10) || 0,
        username: (d.username || "").trim()
      };
      if (target.user_id || target.username) {
        openProfile(root, target, "ia_atrium:profile");
      } else {
        setActiveView(root, "wall", { source: "ia_atrium:profile" });
      }
    });


    // Fired by Discuss when a username is clicked.
    window.addEventListener("ia:open_profile", (ev) => {
      const d = (ev && ev.detail) ? ev.detail : {};
      const target = {
        user_id: parseInt(d.user_id || d.userId || "0", 10) || 0,
        username: (d.username || "").trim()
      };
      openProfile(root, target, "ia:open_profile");
    });

    // If we were deep-linked (e.g. after login), open the requested profile.
    const last = getLastRequestedProfile();
    if (last) {
      openProfile(root, last, "deep-link");
    }
    window.addEventListener("ia_profile:action", onMenuAction);
    window.addEventListener("ia_connect:profileMenu", onMenuAction);
  }

  document.addEventListener("DOMContentLoaded", boot);
})();
