/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.ui.channels.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.ui = NS.ui || {};
  NS.ui.channels = NS.ui.channels || {};

  function mount() {
    const R = NS.util.qs("#ia-stream-shell");
    if (!R) return null;
    return NS.util.qs('[data-panel="channels"] .ia-stream-channels', R);
  }

  function esc(s) {
    s = (s === null || s === undefined) ? "" : String(s);
    return s.replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
  }

  function renderPlaceholder(msg) {
    const M = mount();
    if (!M) return;
    M.innerHTML = '<div class="ia-stream-placeholder">' + esc(msg || "Loading…") + "</div>";
  }

  function fmtNum(n) {
    n = parseInt(n || 0, 10);
    if (!isFinite(n) || n < 0) n = 0;
    if (n >= 1000000) return (Math.round(n / 100000) / 10) + "m";
    if (n >= 1000) return (Math.round(n / 100) / 10) + "k";
    return String(n);
  }

function isLoggedIn() {
  const shell = NS.util.qs("#ia-stream-shell");
  return !!(shell && shell.getAttribute("data-logged-in") === "1");
}

function deriveChannelUri(channelUrl, channelName) {
  try {
    if (channelUrl) {
      const u = new URL(channelUrl);
      const host = u.host || "";
      const name = (channelName || "").replace(/^@/, "");
      if (host && name) return "@" + name + "@" + host;
    }
  } catch (e) {}
  return (channelName || "").replace(/^@/, "");
}

  function cardHtml(ch) {
    const id = ch && ch.id ? String(ch.id) : "";
    const name = ch && (ch.display_name || ch.name) ? (ch.display_name || ch.name) : "Channel";
    const url = ch && ch.url ? ch.url : "";
    const avatar = ch && ch.avatar ? ch.avatar : "";
    const cover = ch && ch.cover ? ch.cover : "";
    const followers = fmtNum(ch && ch.followers ? ch.followers : 0);

    return (
      '<article class="ia-stream-channel-card" data-channel-id="' + esc(id) + '">' +
        '<div class="ia-stream-channel-cover" style="' + (cover ? 'background-image:url(' + esc(cover) + ');background-size:cover;background-position:center;' : '') + '"></div>' +
        '<div class="ia-stream-channel-body">' +
          '<div class="ia-stream-channel-avatar" style="' + (avatar ? 'background-image:url(' + esc(avatar) + ');background-size:cover;background-position:center;' : '') + '"></div>' +
          '<div>' +
            '<div class="ia-stream-channel-name">' +
              (url ? '<a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(name) + '</a>' : esc(name)) +
            '</div>' +
            '<div class="ia-stream-channel-meta">' +
              'Followers: ' + esc(followers) +
            '</div>' +
            (isLoggedIn() ? (
              '<div class="ia-stream-channel-actions" data-channel-url="' + esc(url) + '" data-channel-name="' + esc(ch && (ch.name || ch.display_name) ? (ch.name || ch.display_name) : name) + '">' +
                '<button type="button" class="ia-stream-channel-action ia-stream-act ia-stream-act-subscribe" data-act="follow">Subscribe</button>' +
                '<span class="ia-stream-channel-status"></span>' +
              '</div>'
            ) : '') +
          '</div>' +
        '</div>' +
      '</article>'
    );
  }

  function bindChannelDelegation() {
    const M = mount();
    if (!M || M.__iaStreamChannelBound) return;
    M.__iaStreamChannelBound = true;

    NS.util.on(M, "click", async function (ev) {
      const t = ev && ev.target ? ev.target : null;
      if (!t) return;

      const btn = (t.closest ? t.closest(".ia-stream-channel-action") : null);
      if (!btn) return;

      const act = btn.getAttribute("data-act") || "";
      if (act !== "follow") return;

      const wrap = btn.closest(".ia-stream-channel-actions");
      const status = wrap ? wrap.querySelector(".ia-stream-channel-status") : null;
      const url = wrap ? (wrap.getAttribute("data-channel-url") || "") : "";
      const name = wrap ? (wrap.getAttribute("data-channel-name") || "") : "";
      const uri = deriveChannelUri(url, name);

      try {
        if (status) status.textContent = "Working…";
        const r = await NS.api.subscribe(uri);
        if (status) status.textContent = (r && r.ok) ? "Subscribed." : ((r && (r.message || r.error)) || "Subscribe failed.");
      } catch (e) {
        if (status) status.textContent = "Subscribe failed.";
      }

      ev.preventDefault();
      ev.stopPropagation();
    });
  }


  NS.ui.channels.load = async function () {
    renderPlaceholder("Loading channels…");

    const res = await NS.api.fetchChannels({ page: 1, per_page: 24 });

    if (!res) {
      renderPlaceholder("No response (network).");
      return;
    }

    if (res.ok === false) {
      renderPlaceholder(res.error || "Channels error.");
      return;
    }

    const items = Array.isArray(res.items) ? res.items : [];
    if (!items.length) {
      const note = res.meta && res.meta.note ? res.meta.note : "";
      renderPlaceholder(note ? ("No channels. " + note) : "No channels returned.");
      return;
    }

    const M = mount();
    if (!M) return;

    M.innerHTML = items.map(cardHtml).join("");
    bindChannelDelegation();
  };

  NS.util.on(window, "ia:stream:tab", function (ev) {
    const tab = ev && ev.detail ? ev.detail.tab : "";
    if (tab === "channels") NS.ui.channels.load();
  });
})();
