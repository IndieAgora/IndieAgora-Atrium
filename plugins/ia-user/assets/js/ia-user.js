(() => {
  const qs = (sel, el = document) => el.querySelector(sel);
  const qsa = (sel, el = document) => Array.from(el.querySelectorAll(sel));

  function esc(s) {
    return String(s || "").replace(/[&<>"']/g, c => ({
      "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"
    }[c]));
  }

  function buildAuthCard() {
    return `
      <div class="ia-modal-header">
        <div class="ia-modal-title">Welcome</div>
        <button type="button" class="ia-icon-btn" data-ia-auth-close aria-label="Close">×</button>
      </div>

      <div class="ia-modal-body">
        <div class="ia-auth-tabs" role="tablist" aria-label="Authentication">
          <button type="button" class="ia-auth-tab active" data-auth-tab="login" role="tab" aria-selected="true">Log in</button>
          <button type="button" class="ia-auth-tab" data-auth-tab="register" role="tab" aria-selected="false">Register</button>
        </div>

        <div class="ia-auth-panels">
          <section class="ia-auth-panel active" data-auth-panel="login" role="tabpanel" aria-label="Login">
            <form class="ia-user-form" data-ia-user="login">
  <div data-ia-login-core>
              <input type="hidden" name="nonce" value="${esc(window.IA_USER?.nonce || "")}">
              <input type="hidden" name="redirect_to" value="" data-ia-redirect-to />

              <div data-ia-login-core>
              <label class="ia-field">
                <span class="ia-label">Username or Email</span>
                <input class="ia-input" type="text" name="identifier" autocomplete="username" required>
              </label>

              <label class="ia-field">
                <span class="ia-label">Password</span>
                <input class="ia-input" type="password" name="password" autocomplete="current-password" required>
              </label>

              <button class="ia-user-submit" type="submit">Log in</button>
                </div>
<div class="ia-user-meta">
                <a class="ia-user-link" href="#" data-ia-user-forgot="1">Forgot password?</a>
              </div>
              </div><!--/ia-login-core-->
              <!-- Forgot-password mini-panel (same modal, no WP login page) -->
              <div class="ia-forgot" data-ia-forgot-panel style="display:none">
                <label class="ia-field">
                  <span class="ia-label">Username or Email</span>
                  <input class="ia-input" type="text" name="login" autocomplete="username">
                </label>
                <button class="ia-user-submit" type="button" data-ia-user-forgot-send="1">Send reset email</button>
                <div class="ia-user-meta">
                  <a class="ia-user-link" href="#" data-ia-user-forgot-back="1">Back to login</a>
                </div>
              
<!-- Reset-password mini-panel (land here from email link) -->
<div class="ia-reset" data-ia-reset-panel style="display:none">
  <input type="hidden" value="" data-ia-rp-login>
  <input type="hidden" value="" data-ia-rp-key>

  <label class="ia-field">
    <span class="ia-label">New password</span>
    <input class="ia-input" type="password" autocomplete="new-password" data-ia-rp-pass1>
  </label>

  <label class="ia-field">
    <span class="ia-label">Confirm new password</span>
    <input class="ia-input" type="password" autocomplete="new-password" data-ia-rp-pass2>
  </label>

  <button class="ia-user-submit" type="button" data-ia-user-reset-send="1">Reset password</button>

  <div class="ia-user-meta">
    <a class="ia-user-link" href="#" data-ia-user-reset-back="1">Back to login</a>
  </div>
</div>
</div>
              <div class="ia-user-msg" aria-live="polite"></div>
            </form>
          </section>

          <section class="ia-auth-panel" data-auth-panel="register" role="tabpanel" aria-label="Register" style="display:none">
            <form class="ia-user-form" data-ia-user="register">
              <input type="hidden" name="nonce" value="${esc(window.IA_USER?.nonce || "")}">
              <input type="hidden" name="redirect_to" value="" data-ia-redirect-to />

              <label class="ia-field">
                <span class="ia-label">Username</span>
                <input class="ia-input" type="text" name="username" autocomplete="username" required>
              </label>

              <label class="ia-field">
                <span class="ia-label">Email</span>
                <input class="ia-input" type="email" name="email" autocomplete="email" required>
              </label>

              <label class="ia-field">
                <span class="ia-label">Password</span>
                <input class="ia-input" type="password" name="password" autocomplete="new-password" required>
              </label>

              <label class="ia-field">
                <span class="ia-label">Confirm password</span>
                <input class="ia-input" type="password" name="password2" autocomplete="new-password" required>
              </label>

              <button class="ia-user-submit" type="submit">Register</button>
              <div class="ia-user-msg" aria-live="polite"></div>
            </form>
          </section>
        </div>
      </div>

      <div class="ia-modal-footer">
        <button type="button" class="ia-btn" data-ia-auth-close>Close</button>
      </div>
    `;
  }

  function replaceAuthModalMarkup() {
    const shell = qs("#ia-atrium-shell");
    const auth = qs("#ia-atrium-auth");
    if (!shell || !auth) return;

    const card = qs(".ia-modal-card", auth);
    if (!card) return;

    // Replace only once
    if (card.getAttribute("data-ia-user-wired") === "1") return;
    card.setAttribute("data-ia-user-wired", "1");

    card.innerHTML = buildAuthCard();
  }

  function postForm(action, form) {
    const fd = new FormData(form);
    fd.append("action", action);

    return fetch(window.IA_USER.ajax, {
      method: "POST",
      credentials: "same-origin",
      body: fd
    }).then(r => r.json());
  }

  function postForgot(form) {
    // Uses WP admin-ajax.php endpoint from IA_USER.ajax
    const fd = new FormData();
    fd.append("action", "ia_user_forgot");
    // Keep nonce naming aligned with the modal form
    const nonceEl = qs('input[name="nonce"]', form);
    if (nonceEl && nonceEl.value) fd.append("nonce", nonceEl.value);
    // The input is named "login" in our forgot mini-panel
    const loginEl = qs('input[name="login"]', form);
    if (loginEl && loginEl.value) fd.append("login", loginEl.value);

    return fetch(window.IA_USER.ajax, {
      method: "POST",
      credentials: "same-origin",
      body: fd
    }).then(r => r.json());
  }

  function setMsg(form, text, kind) {
    const box = qs(".ia-user-msg", form);
    if (!box) return;
    box.classList.remove("ok", "error");
    if (kind) box.classList.add(kind);
    box.textContent = text || "";
  }

  function setBusy(form, busy) {
    const btn = qs(".ia-user-submit", form);
    if (btn) btn.disabled = !!busy;
  }

function wireForms() {
  const auth = qs("#ia-atrium-auth");
  if (!auth) return;

  // --- Tab switching (Log in / Register) ---
  auth.addEventListener("click", (e) => {
    const tab = e.target.closest(".ia-auth-tab");
    if (!tab) return;

    e.preventDefault();

    const name = tab.getAttribute("data-auth-tab"); // "login" or "register"
    if (!name) return;

    // tabs
    qsa(".ia-auth-tab", auth).forEach(t => {
      const isActive = t === tab;
      t.classList.toggle("active", isActive);
      t.setAttribute("aria-selected", isActive ? "true" : "false");
    });

    // panels
    qsa(".ia-auth-panel", auth).forEach(p => {
      const isActive = p.getAttribute("data-auth-panel") === name;
      p.classList.toggle("active", isActive);
      p.style.display = isActive ? "" : "none";
    });
  });

  // --- Form submit (AJAX) ---
  auth.addEventListener("submit", async (e) => {
    const form = e.target.closest(".ia-user-form");
    if (!form) return;

    e.preventDefault();
    setMsg(form, "", "");
    setBusy(form, true);

    // Ensure redirect_to gets current page URL (preserves ?ia_nav=...)
    const red = qs("[data-ia-redirect-to]", form);
    if (red && !red.value) red.value = window.location.href;

    const mode = form.getAttribute("data-ia-user");
    try {
      let res;
      if (mode === "register") {
        res = await postForm("ia_user_register", form);
      } else {
        res = await postForm("ia_user_login", form);
      }

      if (!res || res.success !== true) {
        const msg = (res && res.data && res.data.message) ? res.data.message : "Request failed.";
        setMsg(form, msg, "error");
        setBusy(form, false);
        return;
      }

      // Registration: send the user to a dedicated "check your email" page.
      // (Actual verification + provisioning happens via ia-auth when they click the link.)
      if (mode === "register") {
        const url = (window.IA_USER && window.IA_USER.check_email_register) ? window.IA_USER.check_email_register : (window.IA_USER?.home || "/");
        window.location.href = url;
        return;
      }

      const redirect = res.data && res.data.redirect_to ? res.data.redirect_to : (window.IA_USER?.home || "/");
      setMsg(form, "OK", "ok");

      window.location.href = redirect;
    } catch (err) {
      setMsg(form, "Network error.", "error");
      setBusy(form, false);
    }
  });

  // Forgot password (fully wired via ia-auth AJAX)
  auth.addEventListener("click", async (e) => {
    // Open forgot panel
    const open = e.target.closest("[data-ia-user-forgot]");
    if (open) {
      e.preventDefault();
      const form = open.closest(".ia-user-form");
      if (!form) return;
      const forgot = qs("[data-ia-forgot-panel]", form);
      if (forgot) {
        forgot.style.display = "";
        // Disable login submit while in forgot mode
        const submit = qs('.ia-user-submit[type="submit"]', form);
        if (submit) submit.style.display = "none";
        // Hide the meta row containing the link
        const meta = qs(".ia-user-meta", form);
        if (meta) meta.style.display = "none";
      }
      setMsg(form, "", "");
      const loginEl = qs('input[name="login"]', form);
      if (loginEl) loginEl.focus();
      return;
    }

    // Back to login
    const back = e.target.closest("[data-ia-user-forgot-back]");
    if (back) {
      e.preventDefault();
      const form = back.closest(".ia-user-form");
      if (!form) return;
      const forgot = qs("[data-ia-forgot-panel]", form);
      if (forgot) forgot.style.display = "none";
      const submit = qs('.ia-user-submit[type="submit"]', form);
      if (submit) submit.style.display = "";
      // Restore the first meta row (Forgot link)
      const metas = qsa(".ia-user-meta", form);
      if (metas[0]) metas[0].style.display = "";
      setMsg(form, "", "");
      const idEl = qs('input[name="identifier"]', form);
      if (idEl) idEl.focus();
      return;
    }

    // Send reset email
    const send = e.target.closest("[data-ia-user-forgot-send]");
    if (send) {
      e.preventDefault();
      const form = send.closest(".ia-user-form");
      if (!form) return;
      setMsg(form, "", "");
      setBusy(form, true);
      try {
        const res = await postForgot(form);
        if (res && res.success === true) {
          const url = (window.IA_USER && window.IA_USER.check_email_reset) ? window.IA_USER.check_email_reset : (window.IA_USER?.home || "/");
          window.location.href = url;
          return;
        }
        // Fallback: show a generic message (avoid user enumeration)
        const msg = (res && res.data && res.data.message) ? res.data.message : "If that account exists, a reset email has been sent.";
        setMsg(form, msg, "ok");
      } catch (err) {
        setMsg(form, "Network error.", "error");
      }
      setBusy(form, false);
    }
  });
}


  // Ensure our markup is present before Atrium's auth-tab switching logic binds.
  document.addEventListener("DOMContentLoaded", () => {
    replaceAuthModalMarkup();
    wireForms();
    setTimeout(() => { try { iaUserMaybeOpenResetFromUrl(); } catch(e) {} }, 0);
  });



async function iaUserResetPassword(login, key, pass1, pass2){
  const fd = new FormData();
  fd.append("action","ia_user_reset");
  fd.append("nonce", window.IA_USER?.nonce || "");
  fd.append("login", login);
  fd.append("key", key);
  fd.append("pass1", pass1);
  fd.append("pass2", pass2);
  const res = await fetch(window.IA_USER?.ajaxUrl || "/wp-admin/admin-ajax.php", { method:"POST", credentials:"same-origin", body: fd });
  const json = await res.json().catch(()=>null);
  return json;
}

function iaUserShowReset(modal){
  const loginForm = qs('form[data-ia-user="login"]', modal);
  if (!loginForm) return;
  const core  = qs('[data-ia-login-core]', loginForm);
  const forgot = qs('[data-ia-forgot-panel]', loginForm);
  const reset = qs('[data-ia-reset-panel]', loginForm);
  if (core) core.style.display = "none";
  if (forgot) forgot.style.display = "none";
  if (reset) reset.style.display = "";
}

function iaUserHideReset(modal){
  const loginForm = qs('form[data-ia-user="login"]', modal);
  if (!loginForm) return;
  const core  = qs('[data-ia-login-core]', loginForm);
  const reset = qs('[data-ia-reset-panel]', loginForm);
  if (reset) reset.style.display = "none";
  if (core) core.style.display = "";
}


function iaUserReadResetParams(){
  try{
    const u = new URL(window.location.href);
    const iaReset = u.searchParams.get("ia_reset");
    const key = (u.searchParams.get("key") || "").trim();
    const login = (u.searchParams.get("login") || "").trim();

    const path = u.pathname.replace(/\/+$/,'');
    const viaPath = (path === "/ia-reset");

    if (key && login && (iaReset === "1" || viaPath)){
      return { key, login };
    }
  }catch(e){}
  return null;
}

// Capture params early, but DON'T open anything until after modal markup is injected.
window.__IA_USER_RESET_PENDING = window.__IA_USER_RESET_PENDING || iaUserReadResetParams();

function iaUserMaybeOpenResetFromUrl(){
  const pending = window.__IA_USER_RESET_PENDING || iaUserReadResetParams();
  if (!pending) return;

  ensureModal();
  openModal();
  const modal = qs(".ia-modal-card");
  if (!modal) return;

  switchPanel("login");
  const loginForm = qs('form[data-ia-user="login"]', modal);
  if (!loginForm) return;

  const rpLogin = qs("[data-ia-rp-login]", loginForm);
  const rpKey   = qs("[data-ia-rp-key]", loginForm);
  if (rpLogin) rpLogin.value = pending.login;
  if (rpKey) rpKey.value = pending.key;

  iaUserShowReset(modal);

  // Clear URL params so refresh doesn't re-trigger
  try{
    const u = new URL(window.location.href);
    u.searchParams.delete("ia_reset");
    u.searchParams.delete("key");
    u.searchParams.delete("login");
    window.history.replaceState({}, "", u.toString());
  }catch(e){}
  window.__IA_USER_RESET_PENDING = null;
}
;

document.addEventListener("click", async (e) => {
  const t = e.target;
  if (!(t instanceof Element)) return;
  const modal = qs(".ia-modal-card");
  if (!modal) return;

  const send = t.closest('[data-ia-user-reset-send="1"]');
  if (send){
    e.preventDefault();
    const loginForm = qs('form[data-ia-user="login"]', modal);
    const msgEl = qs(".ia-user-msg", loginForm);
    const rpLogin = qs("[data-ia-rp-login]", loginForm);
    const rpKey   = qs("[data-ia-rp-key]", loginForm);
    const p1      = qs("[data-ia-rp-pass1]", loginForm);
    const p2      = qs("[data-ia-rp-pass2]", loginForm);

    const login = rpLogin ? rpLogin.value.trim() : "";
    const key   = rpKey ? rpKey.value.trim() : "";
    const pass1 = p1 ? p1.value : "";
    const pass2 = p2 ? p2.value : "";

    if (msgEl) msgEl.textContent = "Resetting password...";
    const json = await iaUserResetPassword(login, key, pass1, pass2);

    if (json && json.success){
      if (msgEl) msgEl.textContent = (json.data && json.data.message) ? json.data.message : "Password reset successful. You can now log in.";
      iaUserHideReset(modal);
      const userField = qs('input[name="username"]', loginForm);
      if (userField && json.data && json.data.login) userField.value = json.data.login;
    } else {
      if (msgEl) msgEl.textContent = (json && json.data && json.data.message) ? json.data.message : "Could not reset password. Please request a new reset email.";
    }
    return;
  }

  const back = t.closest('[data-ia-user-reset-back="1"]');
  if (back){
    e.preventDefault();
    iaUserHideReset(modal);
    return;
  }
}, true);

})();
