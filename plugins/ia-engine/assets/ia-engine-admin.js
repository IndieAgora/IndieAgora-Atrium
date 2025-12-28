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
});
