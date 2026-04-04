  function decodeHtmlUrl(url) {
    const YT = window.IA_DISCUSS_YOUTUBE || {};
    if (YT.decodeHtmlUrl) return YT.decodeHtmlUrl(url);
    const raw = String(url || "").trim();
    if (!raw) return "";
    return raw
      .replace(/&amp;/gi, '&')
      .replace(/&#038;/gi, '&')
      .replace(/&#x26;/gi, '&');
  }

  function parseYouTubeMeta(url) {
    const YT = window.IA_DISCUSS_YOUTUBE || {};
    if (YT.parseYouTubeMeta) return YT.parseYouTubeMeta(url);
    return null;
  }

  function parsePeerTubeUuid(url) {
    try {
      const u = new URL(decodeHtmlUrl(url));
      const m = u.pathname.match(/\/videos\/watch\/([^\/\?\#]+)/i);
      if (m && m[1]) return m[1];
      return null;
    } catch (e) {
      return null;
    }
  }

  function buildVideoMeta(videoUrl) {
    const url = String(videoUrl || "").trim();
    if (!url) return null;

    const yt = parseYouTubeMeta(url);
    if (yt && (yt.id || yt.isPlaylist)) {
      const YT = window.IA_DISCUSS_YOUTUBE || {};
      const embedUrl = YT.buildEmbed ? YT.buildEmbed(yt) : "";
      const thumbUrl = YT.thumbUrl ? YT.thumbUrl(yt) : "";
      return {
        kind: yt.isPlaylist ? "youtube-playlist" : "youtube",
        url,
        isShort: !!yt.isShort,
        isPlaylist: !!yt.isPlaylist,
        embedUrl,
        thumbUrl,
        openUrl: YT.buildOpenUrl ? YT.buildOpenUrl(yt) : url
      };
    }

    const uuid = parsePeerTubeUuid(url);
    if (uuid) {
      try {
        const u = new URL(decodeHtmlUrl(url));
        const origin = u.origin;
        return {
          kind: "peertube",
          url,
          embedUrl: origin + "/videos/embed/" + encodeURIComponent(uuid),
          thumbUrl: origin + "/lazy-static/previews/" + encodeURIComponent(uuid) + ".jpg"
        };
      } catch (e) {}
    }

    const lu = url.toLowerCase();
    if (/\.(mp4|webm|mov)(\?|$)/i.test(lu)) {
      return { kind: "file", url, embedUrl: url, thumbUrl: "" };
    }

    return { kind: "iframe", url, embedUrl: url, thumbUrl: "" };
  }

  // Back-compat alias (older handler name)
  function detectVideoMeta(videoUrl) {
    return buildVideoMeta(videoUrl);
  }

