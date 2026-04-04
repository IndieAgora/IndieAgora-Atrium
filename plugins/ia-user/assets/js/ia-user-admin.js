(function(){
  function init(){
    var input = document.querySelector('.ia-user-admin-search-input');
    var list = document.getElementById('ia-user-admin-search-suggestions');
    if (!input || !list || !window.ajaxurl) return;

    var timer = null;
    var lastValue = '';

    function clearList(){
      while (list.firstChild) list.removeChild(list.firstChild);
    }

    function fillSuggestions(items){
      clearList();
      if (!Array.isArray(items)) return;
      items.forEach(function(item){
        if (!item || !item.label) return;
        var opt = document.createElement('option');
        opt.value = item.value || '';
        opt.label = item.label;
        list.appendChild(opt);
      });
    }

    function fetchSuggestions(value){
      var form = new window.FormData();
      form.append('action', 'ia_user_admin_search_suggest');
      form.append('q', value);
      fetch(window.ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        body: form
      })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (!data || !data.success || !data.data) {
          clearList();
          return;
        }
        fillSuggestions(data.data.items || []);
      })
      .catch(function(){ clearList(); });
    }

    input.addEventListener('input', function(){
      var value = (input.value || '').trim();
      if (value === lastValue) return;
      lastValue = value;
      if (timer) window.clearTimeout(timer);
      if (value.length < 2) {
        clearList();
        return;
      }
      timer = window.setTimeout(function(){ fetchSuggestions(value); }, 180);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
