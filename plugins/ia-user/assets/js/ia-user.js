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
              <input type="hidden" name="nonce" value="${esc(window.IA_USER?.nonce || "")}">
              <input type="hidden" name="redirect_to" value="" data-ia-redirect-to />

              <label class="ia-field">
                <span class="ia-label">Username or Email</span>
                <input class="ia-input" type="text" name="identifier" autocomplete="username" required>
              </label>

              <label class="ia-field">
                <span class="ia-label">Password</span>
                <input class="ia-input" type="password" name="password" autocomplete="current-password" required>
              </label>

              <button class="ia-user-submit" type="submit">Log in</button>
              <div class="ia-user-meta">
                <a class="ia-user-link" href="#" data-ia-user-forgot="1">Forgot password?</a>
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

      const redirect = res.data && res.data.redirect_to ? res.data.redirect_to : (window.IA_USER?.home || "/");
      setMsg(form, "OK", "ok");

      window.location.href = redirect;
    } catch (err) {
      setMsg(form, "Network error.", "error");
      setBusy(form, false);
    }
  });

  // Placeholder "forgot password" (non-functional for now)
  auth.addEventListener("click", (e) => {
    const a = e.target.closest("[data-ia-user-forgot]");
    if (!a) return;
    e.preventDefault();
    const form = a.closest(".ia-user-form");
    setMsg(form, "Password reset isn’t wired yet.", "error");
  });
}


  // Ensure our markup is present before Atrium's auth-tab switching logic binds.
  document.addEventListener("DOMContentLoaded", () => {
    replaceAuthModalMarkup();
    wireForms();
  });

})();