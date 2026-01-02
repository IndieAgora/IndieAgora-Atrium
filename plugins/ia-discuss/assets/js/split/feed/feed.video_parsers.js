"use strict";
          </div>

          <div class="iad-videomodal-body" data-iad-videomodal-body></div>
        </div>
      </div>
    `;
    document.body.appendChild(wrap.firstElementChild);
    m = document.querySelector("[data-iad-videomodal]");

    m.querySelectorAll("[data-iad-videomodal-close]").forEach((x) => {
      x.addEventListener("click", () => closeVideoModal());
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && m && !m.hasAttribute("hidden")) {
        closeVideoModal();
      }
    });

    return m;
  }

  function closeVideoModal() {
    var m = document.querySelector("[data-iad-videomodal]");
    if (!m) return;

    var body = m.querySelector("[data-iad-videomodal-body]");
    if (body) body.innerHTML = ""; // stop playback
    m.setAttribute("hidden", "");
    lockPageScroll(false);
  }

  function parseYouTubeId(url) {
    try {
      var u = new URL(url);
      if (u.hostname.includes("youtu.be")) {
        var id = u.pathname.replace(/^\/+/, "").split("/")[0];
        return id || null;
      }
      if (u.hostname.includes("youtube.com")) {
        var id = u.searchParams.get("v");
        return id || null;
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  function parsePeerTubeUuid(url) {
    try {
      var u = new URL(url);
      var m = u.pathname.match(/\/videos\/watch\/([^\/\?\#]+)/i);
      if (m && m[1]) return m[1];
      return null;
    } catch (e) {
      return null;
    }
  }

  function buildVideoMeta(videoUrl) {
    var url = String(videoUrl || "").trim();
    if (!url) return null;

    var yid = parseYouTubeId(url);
    if (yid) {
      return {
        kind: "youtube",
        url,
        embedUrl: `https://www.youtube-nocookie.com/embed/${encodeURIComponent(yid)}`,
        thumbUrl: `https://img.youtube.com/vi/${encodeURIComponent(yid)}/hqdefault.jpg`
      };
    }

    var uuid = parsePeerTubeUuid(url);
    if (uuid) {
      try {
        var u = new URL(url);
        var origin = u.origin;
        return {
          kind: "peertube",
          url,
          embedUrl: origin + "/videos/embed/" + encodeURIComponent(uuid),
          thumbUrl: origin + "/lazy-static/previews/" + encodeURIComponent(uuid) + ".jpg"
        };
      } catch (e) {}
    }

    var lu = url.toLowerCase();
    if (/\.(mp4|webm|mov)(\?|$)/i.test(lu)) {
;
