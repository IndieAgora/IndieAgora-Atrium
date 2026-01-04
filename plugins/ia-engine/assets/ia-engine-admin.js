jQuery(function ($) {
  function setResult(action, ok, message) {
    const $el = $('.ia-engine-test-result[data-result-for="' + action + '"]');
    $el.removeClass('ok bad').addClass(ok ? 'ok' : 'bad').text(message);
  }

  $('.ia-engine-test').on('click', function () {
    const action = $(this).data('action');
    setResult(action, false, 'Testing...');

    $.post(IAEngine.ajaxUrl, {
      action: action,
      nonce: IAEngine.nonce
    })
      .done(function (resp) {
        if (resp && resp.ok) {
          setResult(action, true, resp.message || 'OK');
        } else {
          setResult(action, false, (resp && resp.message) ? resp.message : 'Connection failed.');
        }
      })
      .fail(function () {
        setResult(action, false, 'Request failed.');
      });
  });

  $('.ia-engine-pt-refresh').on('click', function () {
    const action = $(this).data('action') || 'ia_engine_pt_refresh_now';
    const $out = $('.ia-engine-pt-refresh-result');
    $out.removeClass('ok bad').text('Refreshing...');

    $.post(IAEngine.ajaxUrl, {
      action: action,
      nonce: IAEngine.nonce
    })
      .done(function (resp) {
        if (resp && resp.ok) {
          $out.addClass('ok').text(resp.message || 'Refreshed.');
          // Optional: reload so the "Existing" masks reflect new values
          setTimeout(function(){ window.location.reload(); }, 600);
        } else {
          $out.addClass('bad').text((resp && resp.message) ? resp.message : 'Refresh failed.');
        }
      })
      .fail(function () {
        $out.addClass('bad').text('Request failed.');
      });
  });
});
