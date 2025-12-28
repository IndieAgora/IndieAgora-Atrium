(function () {
  "use strict";

  function qs(sel, root) { return (root || document).querySelector(sel); }

  const CFG = window.IA_PROFILE_MENU || { isAdmin: false, adminUrl: "" };

  // Locked-in order (logged-in).
  // Removed: linked_accounts, import (per your latest rule).
  // Added: admin (admins only).
  const ITEMS_BASE = [
    { key: "view_profile", label: "View Profile" },
    { key: "edit_profile", label: "Edit Profile" },
    { key: "media", label: "Media & Galleries" },
    { key: "activity", label: "Activity" },
    { key: "privacy", label: "Privacy & Visibility" },
    { key: "notifications", label: "Notification Settings" },
    { key: "blocked", label: "Blocked / Muted Users" },
    { key: "export", label: "Export Data" },
    { key: "deactivate", label: "Deactivate Account" },
    { key: "delete", label: "Delete Account" },
    { key: "logout", label: "Log Out", isLogout: true }
  ];

  function getItems() {
    const items = ITEMS_BASE.slice();
    if (CFG.isAdmin) {
      // Insert Admin near the bottom, just before Deactivate/Delete/Logout cluster feels right.
      const idx = Math.max(0, items.findIndex(i => i.key === "deactivate"));
      items.splice(idx, 0, { key: "admin", label: "Admin" });
    }
    return items;
  }

  function closeMenu(shell) {
    const menu = qs("[data-profile-menu]", shell);
    if (!menu) return;
    menu.classList.remove("open");
    menu.setAttribute("aria-hidden", "true");
  }

  function goConnectProfile() {
    window.dispatchEvent(new CustomEvent("ia_atrium:navigate", { detail: { tab: "connect" } }));
    window.dispatchEvent(new CustomEvent("ia_atrium:profile", {
      detail: { userId: (window.IA_ATRIUM && IA_ATRIUM.userId) ? IA_ATRIUM.userId : 0 }
    }));
  }

  function buildMenuHtml(logoutHref) {
    const parts = [];
    const items = getItems();

    for (const it of items) {
      if (it.isLogout) {
        parts.push(`<a class="ia-menu-item" data-profile-action="${it.key}" href="${logoutHref || "#"}">${it.label}</a>`);
      } else if (it.key === "delete" || it.key === "deactivate") {
        parts.push(`<button type="button" class="ia-menu-item ia-menu-item-danger" data-profile-action="${it.key}">${it.label}</button>`);
      } else {
        parts.push(`<button type="button" class="ia-menu-item" data-profile-action="${it.key}">${it.label}</button>`);
      }
    }
    return parts.join("");
  }

  function install() {
    const shell = qs("#ia-atrium-shell");
    if (!shell) return;

    const menu = qs("[data-profile-menu]", shell);
    if (!menu) return;

    const existingLogout = menu.querySelector('a.ia-menu-item[href]');
    const logoutHref = existingLogout ? existingLogout.getAttribute("href") : "#";

    // Zero-touch override of Atrium's hardcoded menu.
    menu.innerHTML = buildMenuHtml(logoutHref);

    // Intercept clicks BEFORE Atrium’s own handler sees them.
    shell.addEventListener("click", function (e) {
      const el = e.target.closest("[data-profile-action]");
      if (!el) return;

      const action = el.getAttribute("data-profile-action");
      if (!action) return;

      // logout navigates normally
      if (action === "logout") return;

      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();

      closeMenu(shell);

      if (action === "view_profile") {
        goConnectProfile();
        return;
      }

      if (action === "admin") {
        if (CFG.isAdmin && CFG.adminUrl) {
          window.location.href = CFG.adminUrl;
        }
        return;
      }

      // Everything else becomes an intent event for Connect (or future micro-plugins).
      window.dispatchEvent(new CustomEvent("ia_connect:profileMenu", {
        detail: { action }
      }));
    }, true);
  }

  document.addEventListener("DOMContentLoaded", install);
})();
