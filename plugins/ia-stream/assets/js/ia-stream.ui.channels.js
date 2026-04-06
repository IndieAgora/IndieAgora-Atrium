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
    return NS.util.qs('[data-ia-stream-discover-channels]', R);
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

  function channelPageUrl(ch) {
    try {
      const u = new URL(window.location.href);
      u.searchParams.set('tab', 'stream');
      const handle = String((ch && (ch.handle || ch.name)) || '').trim();
      if (handle) u.searchParams.set('stream_channel', handle);
      else u.searchParams.delete('stream_channel');
      const label = String((ch && (ch.display_name || ch.name)) || '').trim();
      if (label) u.searchParams.set('stream_channel_name', label);
      else u.searchParams.delete('stream_channel_name');
      u.searchParams.delete('stream_q');
      u.searchParams.delete('stream_scope');
      u.searchParams.delete('stream_view');
      u.searchParams.delete('stream_subscriptions');
      u.hash = '';
      return u.toString();
    } catch (e) {
      return '#';
    }
  }

  function cardHtml(ch) {
    const id = ch && ch.id ? String(ch.id) : "";
    const name = ch && (ch.display_name || ch.name) ? (ch.display_name || ch.name) : "Channel";
    const url = ch && ch.url ? ch.url : "";
    const avatar = ch && ch.avatar ? ch.avatar : "";
    const cover = ch && ch.cover ? ch.cover : "";
    const followers = fmtNum(ch && ch.followers ? ch.followers : 0);
    const handle = ch && (ch.handle || ch.name) ? String(ch.handle || ch.name) : '';
    const localUrl = channelPageUrl(ch);

    return (
      '<article class="ia-stream-channel-card" data-channel-id="' + esc(id) + '" data-channel-handle="' + esc(handle) + '">' +
        '<button type="button" class="ia-stream-channel-open" data-open-channel="' + esc(handle) + '" data-open-channel-name="' + esc(name) + '" aria-label="' + esc(name) + '">' +
          '<div class="ia-stream-channel-cover" style="' + (cover ? 'background-image:url(' + esc(cover) + ');background-size:cover;background-position:center;' : '') + '"></div>' +
          '<div class="ia-stream-channel-body">' +
            '<div class="ia-stream-channel-avatar" style="' + (avatar ? 'background-image:url(' + esc(avatar) + ');background-size:cover;background-position:center;' : '') + '"></div>' +
            '<div class="ia-stream-channel-copy">' +
              '<div class="ia-stream-channel-name">' + esc(name) + '</div>' +
              '<div class="ia-stream-channel-meta">Followers: ' + esc(followers) + '</div>' +
            '</div>' +
          '</div>' +
        '</button>' +
        '<div class="ia-stream-channel-links">' +
          '<a class="ia-stream-channel-deeplink" href="' + esc(localUrl) + '">Open in Stream</a>' +
          (url ? '<a class="ia-stream-channel-external" href="' + esc(url) + '" target="_blank" rel="noopener">PeerTube</a>' : '') +
        '</div>' +
      '</article>'
    );
  }

  NS.ui.channels.renderInto = function (sel, items) {
    const R = NS.util.qs("#ia-stream-shell");
    const M = R ? NS.util.qs(sel, R) : null;
    if (!M) return;
    M.innerHTML = (Array.isArray(items) ? items : []).map(cardHtml).join("");
  };

  NS.ui.channels.load = async function (opts) {
    opts = opts || {};
    const target = opts.target || '[data-ia-stream-discover-channels]';
    const R = NS.util.qs("#ia-stream-shell");
    const M = R ? NS.util.qs(target, R) : null;
    if (M) M.innerHTML = '<div class="ia-stream-placeholder">Loading channels…</div>';

    const res = await NS.api.fetchChannels({ page: 1, per_page: opts.per_page || 8, search: opts.search || "" });

    if (!res || res.ok === false) {
      if (M) M.innerHTML = '<div class="ia-stream-placeholder">' + esc((res && res.error) || 'Channels error.') + '</div>';
      return [];
    }

    const items = Array.isArray(res.items) ? res.items : [];
    if (!items.length) {
      if (M) M.innerHTML = '<div class="ia-stream-placeholder">No channels found.</div>';
      return [];
    }

    if (M) M.innerHTML = items.map(cardHtml).join("");
    return items;
  };

  NS.util.on(window, "ia:stream:tab", function (ev) {
    const tab = ev && ev.detail ? ev.detail.tab : "";
    if (tab === "discover") NS.ui.channels.load();
  });
})();
