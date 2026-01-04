(function(){
  'use strict';

  function ready(fn){
    if(document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function qs(sel, root){ return (root || document).querySelector(sel); }
  function qsa(sel, root){ return Array.from((root || document).querySelectorAll(sel)); }

  function setUrlParam(key, value){
    try{
      var url = new URL(window.location.href);
      if(value === null || value === undefined || value === '') url.searchParams.delete(key);
      else url.searchParams.set(key, value);
      window.history.replaceState({}, '', url.toString());
    }catch(e){}
  }

  function getResetParams(){
    try{
      var url = new URL(window.location.href);
      var ia_reset = url.searchParams.get('ia_reset');
      if(ia_reset !== '1') return null;
      var key = url.searchParams.get('key') || '';
      var login = url.searchParams.get('login') || '';
      if(!key || !login) return null;
      return { key: key, login: login };
    }catch(e){
      return null;
    }
  }

  function openModal(modal){
    if(!modal) return;
    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function showPanel(modal, name){
    // tabs (if they exist)
    qsa('.ia-auth-tab', modal).forEach(function(btn){
      var is = btn.getAttribute('data-auth-tab') === name;
      btn.classList.toggle('active', is);
      btn.setAttribute('aria-selected', is ? 'true' : 'false');
    });

    // panels
    qsa('.ia-auth-panel', modal).forEach(function(p){
      var is = p.getAttribute('data-auth-panel') === name;
      p.classList.toggle('active', is);
      // keep CSS simple: active panels are display:block; others display:none
      p.style.display = is ? '' : 'none';
    });
  }

  function ensureResetPanel(modal){
    var panelsWrap = qs('.ia-auth-panels', modal);
    if(!panelsWrap) return null;

    var existing = qs('.ia-auth-panel[data-auth-panel="reset"]', panelsWrap);
    if(existing) return existing;

    var section = document.createElement('section');
    section.className = 'ia-auth-panel';
    section.setAttribute('data-auth-panel', 'reset');
    section.setAttribute('role', 'tabpanel');
    section.setAttribute('aria-label', 'Reset password');

    section.innerHTML = ''
      + '<form class="ia-reset-form" data-ia-reset-form="1" data-ia-auth-skip="1">'
      + '  <input type="hidden" name="nonce" value="">'
      + '  <input type="hidden" name="login" value="">'
      + '  <input type="hidden" name="key" value="">'
      + '  <label class="ia-field">'
      + '    <span class="ia-label">New password</span>'
      + '    <input class="ia-input" type="password" name="pass1" autocomplete="new-password" required>'
      + '  </label>'
      + '  <label class="ia-field">'
      + '    <span class="ia-label">Confirm new password</span>'
      + '    <input class="ia-input" type="password" name="pass2" autocomplete="new-password" required>'
      + '  </label>'
      + '  <button class="ia-btn ia-btn-primary" type="submit">Set new password</button>'
      + '  <div class="ia-auth-msg" aria-live="polite"></div>'
      + '  <div class="ia-auth-links" style="margin-top:10px;">'
      + '    <a href="#" data-ia-reset-back="1">Back to login</a>'
      + '  </div>'
      + '</form>';

    panelsWrap.appendChild(section);
    return section;
  }

  function post(action, payload){
    var fd = new FormData();
    fd.append('action', action);
    Object.keys(payload || {}).forEach(function(k){ fd.append(k, payload[k]); });
    return fetch((window.IA_RESET_FIX && IA_RESET_FIX.ajax) ? IA_RESET_FIX.ajax : '/wp-admin/admin-ajax.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: fd
    }).then(function(r){ return r.json(); });
  }

  function boot(){
    var params = getResetParams();
    if(!params) return;

    var tries = 0;
    var t = setInterval(function(){
      tries++;
      var modal = document.getElementById('ia-atrium-auth');
      if(!modal){
        if(tries > 200) clearInterval(t);
        return;
      }

      // Ensure panel exists, fill hidden fields
      var panel = ensureResetPanel(modal);
      if(!panel){
        if(tries > 200) clearInterval(t);
        return;
      }

      var form = qs('form[data-ia-reset-form="1"]', panel);
      if(!form){
        if(tries > 200) clearInterval(t);
        return;
      }

      // Fill
      qs('input[name="nonce"]', form).value = (window.IA_RESET_FIX && IA_RESET_FIX.nonce) ? IA_RESET_FIX.nonce : '';
      qs('input[name="login"]', form).value = params.login;
      qs('input[name="key"]', form).value = params.key;

      // Wire "back to login"
      var back = qs('[data-ia-reset-back="1"]', form);
      if(back && !back.__iaBound){
        back.__iaBound = true;
        back.addEventListener('click', function(ev){
          ev.preventDefault();
          showPanel(modal, 'login');
        });
      }

      // Wire submit once
      if(!form.__iaBound){
        form.__iaBound = true;
        // Use capture + stopImmediatePropagation to prevent IA Auth's delegated
        // handlers (if any) from intercepting this submit and showing a generic "Error".
        form.addEventListener('submit', function(ev){
          ev.preventDefault();
          if (typeof ev.stopImmediatePropagation === 'function') ev.stopImmediatePropagation();
          if (typeof ev.stopPropagation === 'function') ev.stopPropagation();
          var msg = qs('.ia-auth-msg', form);
          if(msg) msg.textContent = '';

          var pass1 = qs('input[name="pass1"]', form).value || '';
          var pass2 = qs('input[name="pass2"]', form).value || '';
          if(!pass1 || !pass2){
            if(msg) msg.textContent = 'Please fill both password fields.';
            return;
          }
          if(pass1 !== pass2){
            if(msg) msg.textContent = 'Passwords do not match.';
            return;
          }

          post('ia_user_reset', {
            nonce: (window.IA_RESET_FIX && IA_RESET_FIX.nonce) ? IA_RESET_FIX.nonce : '',
            login: params.login,
            key: params.key,
            pass1: pass1,
            pass2: pass2
          }).then(function(r){
            if(r && r.success){
              if(msg) msg.textContent = (r.data && r.data.message) ? r.data.message : 'Password reset successful.';
              // clear params so refresh doesn't reopen
              setUrlParam('ia_reset', '');
              setUrlParam('key', '');
              setUrlParam('login', '');
              // go to login panel and focus
              showPanel(modal, 'login');
              try{
                var idField = qs('input[name="identifier"]', modal);
                if(idField) idField.focus();
              }catch(e){}
            } else {
              var m = (r && r.data && r.data.message) ? r.data.message : 'Reset failed. Please request a new reset email.';
              if(msg) msg.textContent = m;
            }
          }).catch(function(){
            if(msg) msg.textContent = 'Network error. Please try again.';
        }, true);
        }, true);
      }

      openModal(modal);
      showPanel(modal, 'reset');

      clearInterval(t);
    }, 50);
  }

  ready(boot);
})();
