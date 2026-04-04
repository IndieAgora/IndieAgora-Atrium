(function () {
  if (!window.IA_ONLINE || !window.fetch) return;

  function detectRoute() {
    try {
      var url = new URL(window.location.href);
      return url.searchParams.get('tab') || window.location.pathname.replace(/^\//, '') || 'home';
    } catch (e) {
      return 'home';
    }
  }

  function ping() {
    var params = new URLSearchParams();
    params.append('action', 'ia_online_ping');
    params.append('nonce', String(window.IA_ONLINE.nonce || ''));
    params.append('route', detectRoute());
    params.append('url', window.location.href);
    fetch(window.IA_ONLINE.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: params
    }).catch(function () {});
  }

  ping();
  window.setInterval(ping, Math.max(15000, Number(window.IA_ONLINE.intervalMs || 60000)));

  var originalPush = history.pushState;
  var originalReplace = history.replaceState;
  history.pushState = function () {
    originalPush.apply(history, arguments);
    window.setTimeout(ping, 200);
  };
  history.replaceState = function () {
    originalReplace.apply(history, arguments);
    window.setTimeout(ping, 200);
  };
  window.addEventListener('popstate', function () { window.setTimeout(ping, 200); });
})();
