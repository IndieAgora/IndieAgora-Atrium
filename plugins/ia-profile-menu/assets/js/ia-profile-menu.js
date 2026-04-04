(function(){
  "use strict";

  function qs(sel, root){ return (root||document).querySelector(sel); }

  const CFG = window.IA_PROFILE_MENU || { isAdmin:false, adminUrl:"" };

  // Only actions that Connect implements.
  const ITEMS_BASE = [
    { key:"view_profile", label:"View Profile" },
    { key:"settings", label:"Settings" },
    { key:"privacy", label:"Privacy" },
    { key:"export", label:"Export Data" },
    { key:"deactivate", label:"Deactivate Account", danger:true },
    { key:"delete", label:"Delete Account", danger:true },
    { key:"logout", label:"Log Out", isLogout:true }
  ];

  function getItems(){
    const items = ITEMS_BASE.slice();
    if (CFG.isAdmin) items.splice(1,0,{ key:"admin", label:"Admin" });
    return items;
  }

  function closeMenu(shell){
    const menu = qs("[data-profile-menu]", shell);
    if (!menu) return;
    menu.classList.remove("open");
    menu.setAttribute("aria-hidden","true");
  }

  function setUrlParams(updates){
    try{
      const u = new URL(location.href);
      Object.keys(updates||{}).forEach(k=>{
        const v = updates[k];
        if (v===null) u.searchParams.delete(k);
        else u.searchParams.set(k,String(v));
      });
      history.pushState({},"",u.toString());
    }catch(_){ }
  }

  function goSelfProfile(){
    // Force a hard navigation so Connect rebuilds the panel with the default (self) context.
    try{
      const u = new URL(location.href);
      u.searchParams.set("tab","connect");
      u.searchParams.delete("ia_profile");
      u.searchParams.delete("ia_profile_name");
      location.href = u.toString();
      return;
    }catch(_){
      location.href = "?tab=connect";
    }
  }

  function buildMenuHtml(logoutHref){
    const parts=[];
    const items=getItems();
    for (const it of items){
      if (it.isLogout){
        parts.push('<a class="ia-menu-item" data-profile-action="'+it.key+'" href="'+(logoutHref||"#")+'">'+it.label+'</a>');
      } else {
        const cls = it.danger ? 'ia-menu-item ia-menu-item-danger' : 'ia-menu-item';
        parts.push('<button type="button" class="'+cls+'" data-profile-action="'+it.key+'">'+it.label+'</button>');
      }
    }
    return parts.join("");
  }

  function install(){
    const shell = qs("#ia-atrium-shell");
    if (!shell) return;

    const menu = qs("[data-profile-menu]", shell);
    if (!menu) return;

    const existingLogout = menu.querySelector('a.ia-menu-item[href]');
    const logoutHref = existingLogout ? existingLogout.getAttribute("href") : "#";

    menu.innerHTML = buildMenuHtml(logoutHref);

    shell.addEventListener("click", function(e){
      const el = e.target && e.target.closest ? e.target.closest("[data-profile-action]") : null;
      if (!el) return;
      const action = el.getAttribute("data-profile-action") || "";
      if (!action || action === "logout") return;

      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === "function") e.stopImmediatePropagation();

      closeMenu(shell);

      if (action === "view_profile"){
        goSelfProfile();
        return;
      }

      if (action === "admin"){
        if (CFG.isAdmin && CFG.adminUrl) location.href = CFG.adminUrl;
        return;
      }

      // Everything else is handled by Connect.
      window.dispatchEvent(new CustomEvent("ia_connect:profileMenu", {
        detail:{ action:action }
      }));
    }, true);
  }

  document.addEventListener("DOMContentLoaded", install);
})();
