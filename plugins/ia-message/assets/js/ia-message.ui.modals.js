function iaMsgRelModal(opts){
  opts = opts || {};
  try{ const ex=document.querySelector('.ia-msg-relmodal'); if(ex) ex.remove(); }catch(_){ }
  const wrap=document.createElement('div');
  wrap.className='ia-msg-relmodal';
  wrap.innerHTML =
    '<div class="ia-msg-relmodal-backdrop" data-ia-msg-relmodal-close></div>' +
    '<div class="ia-msg-relmodal-card" role="dialog" aria-modal="true">' +
      '<div class="ia-msg-relmodal-title">'+(opts.title||'')+'</div>' +
      '<div class="ia-msg-relmodal-body">'+(opts.body||'')+'</div>' +
      '<div class="ia-msg-relmodal-actions"></div>' +
    '</div>';
  const acts=wrap.querySelector('.ia-msg-relmodal-actions');
  (opts.actions||[{label:'Close'}]).forEach(a=>{
    const b=document.createElement('button');
    b.type='button';
    b.className='ia-msg-relmodal-btn'+(a.primary?' is-primary':'');
    b.textContent=a.label||'OK';
    b.addEventListener('click', async (ev)=>{
      ev.preventDefault(); ev.stopPropagation();
      if (a.onClick){ try{ await a.onClick(); }catch(_){ } }
      if (a.stay) return;
      try{ wrap.remove(); }catch(_){ }
    });
    acts.appendChild(b);
  });
  wrap.addEventListener('click',(ev)=>{
    const c=ev.target && ev.target.closest ? ev.target.closest('[data-ia-msg-relmodal-close]') : null;
    if(c){ try{ wrap.remove(); }catch(_){ } }
  }, true);
  document.body.appendChild(wrap);
  return wrap;
}
