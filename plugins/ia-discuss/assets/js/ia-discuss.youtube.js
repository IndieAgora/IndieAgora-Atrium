(function (window) {
  "use strict";

  function decodeHtmlUrl(url) {
    const raw = String(url || "").trim();
    if (!raw) return "";
    return raw
      .replace(/&amp;/gi, '&')
      .replace(/&#038;/gi, '&')
      .replace(/&#x26;/gi, '&');
  }

  function cleanId(v) {
    let s = String(v || "").trim();
    if (!s) return null;
    for (let i = 0; i < 2; i++) {
      if (/%[0-9a-f]{2}/i.test(s)) {
        try {
          const dec = decodeURIComponent(s);
          if (dec === s) break;
          s = dec;
        } catch (e) {
          break;
        }
      }
    }
    s = s.split(/[?&#/]/)[0];
    s = s.split(/%3f|%26|%23/i)[0];
    return s || null;
  }

  function parseStartSeconds(value) {
    const raw = String(value || "").trim();
    if (!raw) return "";
    if (/^\d+$/.test(raw)) return raw;
    const m = raw.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/i);
    if (!m) return "";
    const h = parseInt(m[1] || "0", 10);
    const min = parseInt(m[2] || "0", 10);
    const sec = parseInt(m[3] || "0", 10);
    const total = (h * 3600) + (min * 60) + sec;
    return total > 0 ? String(total) : "";
  }

  function parseYouTubeMeta(url) {
    try {
      const u = new URL(decodeHtmlUrl(url));
      const host = String(u.hostname || "").toLowerCase();
      let id = null;
      let isShort = false;

      if (host.includes("youtu.be")) {
        id = cleanId(u.pathname.replace(/^\/+/, "").split("/")[0] || null);
      } else if (host.includes("youtube.com")) {
        const v = u.searchParams.get("v");
        if (v) id = cleanId(v);

        if (!id) {
          const m = u.pathname.match(/^\/(shorts|live)\/([^\/?#]+)/i);
          if (m && m[2]) {
            isShort = String(m[1]).toLowerCase() === "shorts";
            id = cleanId(m[2]);
          }
        }
      }

      const playlistId = String(u.searchParams.get("list") || "").trim();
      const index = String(u.searchParams.get("index") || "").trim();
      const start = String(u.searchParams.get("start") || parseStartSeconds(u.searchParams.get("t")) || "").trim();
      const isPlaylist = !!playlistId;
      if (!id && !isPlaylist) return null;

      return {
        id,
        playlistId,
        isShort,
        isPlaylist,
        index,
        start
      };
    } catch (e) {
      return null;
    }
  }

  function buildEmbed(meta) {
    if (!meta || (!meta.id && !meta.playlistId)) return null;

    const params = new URLSearchParams();
    params.set("autoplay", "0");
    params.set("playsinline", "1");
    params.set("rel", "0");

    if (meta.isPlaylist && meta.playlistId) {
      params.set("list", String(meta.playlistId));
      if (meta.index) params.set("index", String(meta.index));
      if (meta.start) params.set("start", String(meta.start));
      return `https://www.youtube.com/embed/videoseries?${params.toString()}`;
    }

    if (!meta.id) return null;
    if (meta.start) params.set("start", String(meta.start));
    return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(meta.id)}?${params.toString()}`;
  }

  function thumbUrl(meta) {
    if (!meta || !meta.id) return "";
    return `https://img.youtube.com/vi/${encodeURIComponent(meta.id)}/hqdefault.jpg`;
  }

  function buildOpenUrl(meta) {
    if (!meta) return "";
    if (meta.isPlaylist && meta.playlistId) {
      const params = new URLSearchParams();
      if (meta.id) params.set("v", String(meta.id));
      params.set("list", String(meta.playlistId));
      if (meta.index) params.set("index", String(meta.index));
      if (meta.start) params.set("start", String(meta.start));
      return `https://www.youtube.com/watch?${params.toString()}`;
    }
    if (!meta.id) return "";
    const params = new URLSearchParams();
    params.set("v", String(meta.id));
    if (meta.start) params.set("start", String(meta.start));
    return `https://www.youtube.com/watch?${params.toString()}`;
  }

  window.IA_DISCUSS_YOUTUBE = {
    decodeHtmlUrl,
    parseYouTubeMeta,
    buildEmbed,
    thumbUrl,
    buildOpenUrl
  };
})(window);
