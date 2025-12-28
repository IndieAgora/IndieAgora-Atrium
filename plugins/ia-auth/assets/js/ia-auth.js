jQuery(function ($) {

  function getFormNonce($form) {
    // Prefer the hidden field inside the actual form (most reliable).
    var n = $form.find('input[name="nonce"]').val();
    if (n) return n;

    // Fall back to localized nonce if present.
    if (window.IA_AUTH && IA_AUTH.nonce) return IA_AUTH.nonce;

    return "";
  }

  function getAjaxUrl() {
    if (window.IA_AUTH && IA_AUTH.ajax) return IA_AUTH.ajax;
    return (window.ajaxurl) ? window.ajaxurl : "/wp-admin/admin-ajax.php";
  }

  function post(action, data, $msg, $form) {
    $msg.removeClass("ok err").text("");
    data = data || {};

    // action must be set
    data.action = action || "";

    // Always attach nonce (prefer hidden field)
    data.nonce = getFormNonce($form);

    $.post(getAjaxUrl(), data)
      .done(function (res) {
        if (res && res.success) {
          $msg.addClass("ok").text(res.data && res.data.message ? res.data.message : "OK");

          var to =
            (res.data && res.data.redirect_to)
              ? res.data.redirect_to
              : ((window.IA_AUTH && IA_AUTH.login_redirect) ? IA_AUTH.login_redirect : "/");

          window.location.href = to;
        } else {
          $msg.addClass("err").text(res && res.data && res.data.message ? res.data.message : "Error");
        }
      })
      .fail(function (xhr) {
        var msg = "Error";
        try {
          var r = xhr.responseJSON;
          if (r && r.data && r.data.message) msg = r.data.message;
        } catch (e) {}
        $msg.addClass("err").text(msg);
      });
  }

  $(document).on("submit", ".ia-auth-form", function (e) {
    e.preventDefault();
    var $f = $(this);
    var action = $f.data("action");
    var $msg = $f.find(".ia-auth-msg");

    var data = {};
    $f.serializeArray().forEach(function (it) {
      data[it.name] = it.value;
    });

    post(action, data, $msg, $f);
  });

});
