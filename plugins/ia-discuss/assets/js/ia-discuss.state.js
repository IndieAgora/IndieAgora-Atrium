(function () {
  "use strict";

  const KEY = "ia_discuss_state_v1";

  function load() {
    try { return JSON.parse(localStorage.getItem(KEY) || "{}") || {}; }
    catch(e){ return {}; }
  }
  function save(s) {
    try { localStorage.setItem(KEY, JSON.stringify(s || {})); } catch(e){}
  }

  function get() { return load(); }
  function set(patch) {
    const s = load();
    Object.assign(s, patch || {});
    save(s);
    return s;
  }

  function markRead(topicId) {
    const s = load();
    s.read = s.read || {};
    s.read[String(topicId)] = Math.floor(Date.now()/1000);
    save(s);
  }

  function isRead(topicId) {
    const s = load();
    return !!(s.read && s.read[String(topicId)]);
  }

  window.IA_DISCUSS_STATE = { get, set, markRead, isRead };
})();
