/* FILE: wp-content/plugins/ia-stream/assets/js/ia-stream.state.js */
(function () {
  "use strict";

  const NS = window.IA_STREAM;
  if (!NS) return;

  NS.store = NS.store || {};

  NS.store.get = function (key, fallback) {
    try {
      const v = window.localStorage.getItem("ia_stream_" + key);
      if (v === null || v === undefined) return fallback;
      return v;
    } catch (e) {
      return fallback;
    }
  };

  NS.store.set = function (key, val) {
    try { window.localStorage.setItem("ia_stream_" + key, String(val)); } catch (e) {}
  };

  function readUrl() {
    try {
      const u = new URL(window.location.href);
      return {
        q: String(u.searchParams.get('stream_q') || '').trim(),
        scope: String(u.searchParams.get('stream_scope') || '').trim(),
        sort: String(u.searchParams.get('stream_sort') || '').trim(),
        view: String(u.searchParams.get('stream_view') || '').trim(),
        video: String(u.searchParams.get('video') || u.searchParams.get('v') || '').trim(),
        channel: String(u.searchParams.get('stream_channel') || '').trim(),
        channelName: String(u.searchParams.get('stream_channel_name') || '').trim(),
        subscriptions: String(u.searchParams.get('stream_subscriptions') || '').trim()
      };
    } catch (e) {
      return { q: '', scope: '', sort: '', view: '', video: '' };
    }
  }

  const fromUrl = readUrl();
  const hasSearch = !!fromUrl.q || fromUrl.view === 'search';
  const hasVideo = !!fromUrl.video;
  const hasChannel = !!fromUrl.channel;
  const hasSubscriptions = fromUrl.subscriptions === '1';

  NS.state.activeTab = hasSearch ? 'search' : (hasSubscriptions ? 'subscriptions' : ((hasChannel || hasVideo) ? 'browse' : 'discover'));
  NS.state.query = fromUrl.q || NS.store.get("query", "");
  NS.state.scope = fromUrl.scope || NS.store.get("scope", "all");
  NS.state.sort = fromUrl.sort || NS.store.get("sort", "-publishedAt");
  NS.state.channelHandle = fromUrl.channel || '';
  NS.state.channelName = fromUrl.channelName || '';
  NS.state.route = { search: hasSearch, video: hasVideo, channel: hasChannel, subscriptions: hasSubscriptions };
})();
