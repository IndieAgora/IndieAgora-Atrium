  function ensureSuggestBox(root, input) {
    if (!input) return null;

    let box = document.querySelector('[data-iad-suggest="portal"]');
    if (box) return box;

    box = document.createElement("div");
    box.setAttribute("data-iad-suggest", "portal");
    box.className = "iad-suggest iad-suggest--portal";
    box.style.display = "none";

    // Theme sync: this dropdown is rendered in <body> (portal) so it won't
    // naturally inherit theme styles scoped under .ia-discuss-root.
    // We mirror the current theme onto the portal element itself.
    try {
      const t = (root && root.getAttribute) ? (root.getAttribute('data-iad-theme') || 'dark') : 'dark';
      box.setAttribute('data-iad-theme', t);
    } catch (e) {}

    // Keep the portal theme in sync if the user toggles light/dark.
    if (root && root.__iadSuggestThemeObs !== true) {
      try {
        const mo = new MutationObserver(() => {
          try {
            const t = root.getAttribute('data-iad-theme') || 'dark';
            const b = document.querySelector('[data-iad-suggest="portal"]');
            if (b) b.setAttribute('data-iad-theme', t);
          } catch (e) {}
        });
        mo.observe(root, { attributes: true, attributeFilter: ['data-iad-theme'] });
        root.__iadSuggestThemeObs = true;
      } catch (e) {}
    }

    document.body.appendChild(box);
    return box;
  }

  function positionSuggestBox(box, input) {
    if (!box || !input) return;
    const r = input.getBoundingClientRect();
    // fixed positioning: respect viewport
    box.style.position = "fixed";
    box.style.left = Math.max(8, Math.round(r.left)) + "px";
    box.style.top = Math.round(r.bottom + 8) + "px";
    box.style.width = Math.max(200, Math.round(r.width)) + "px";
  }


  function hideSuggest(box) {
    if (!box) return;
    box.style.display = "none";
    box.innerHTML = "";
  }

  function showSuggest(box, input) {
    if (!box) return;
    // Re-assert theme on show in case the portal was created under a
    // different root instance.
    try {
      const r = input && input.closest ? input.closest('.ia-discuss-root') : null;
      if (r) box.setAttribute('data-iad-theme', r.getAttribute('data-iad-theme') || 'dark');
    } catch (e) {}
    positionSuggestBox(box, input);
    box.style.display = "block";
  }

  function suggestGroup(title, itemsHTML) {
    return `
      <div class="iad-sg">
        <div class="iad-sg-title">${esc(title)}</div>
        <div class="iad-sg-items">${itemsHTML}</div>
      </div>
    `;
  }

