(function(){
  'use strict';

  const C = window.IA_CONNECT || {};
  const ajaxUrl = C.ajaxUrl || (window.ajaxurl || '/wp-admin/admin-ajax.php');
  const nonces = (C.nonces || {});
  const BLANK_AVA = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';

  const qs = (root, sel) => (root || document).querySelector(sel);
  const qsa = (root, sel) => Array.from((root || document).querySelectorAll(sel));

  function postForm(action, data, files){
    return new Promise((resolve,reject)=>{
      const fd = new FormData();
      fd.append('action', action);
      Object.keys(data||{}).forEach(k=>{
        if (data[k] !== undefined && data[k] !== null) fd.append(k, data[k]);
      });
      if (files && files.length){
        for (let i=0;i<files.length;i++) fd.append('files[]', files[i]);
      }
      const xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl, true);
      xhr.onreadystatechange = ()=>{
        if (xhr.readyState !== 4) return;
        let json = null;
        try{ json = JSON.parse(xhr.responseText || '{}'); }catch(e){}
        if (xhr.status >= 200 && xhr.status < 300 && json){
          resolve(json);
        } else {
          reject(json || {success:false, data:{message:'Request failed'}});
        }
      };
      xhr.send(fd);
    });
  }

  function esc(s){
    return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[m]));
  }

  function linkMentions(text){
    const t = String(text||'');
    return t.replace(/(^|\s)@([a-zA-Z0-9_\-\.]{2,40})/g, (m,sp,u)=>{
      const url = '?tab=connect&ia_profile_name=' + encodeURIComponent(u);
      return sp + '<a class="iac-mention" href="' + url + '">@' + esc(u) + '</a>';
    });
  }

  // Extract URLs from plain text (best-effort). Used for rich previews/embeds.
  function extractUrls(text){
    const t = String(text||'');
    const m = t.match(/https?:\/\/[^\s<>()\[\]"']+/gi);
    return m ? m.map(s=>s.replace(/[\]\).,;!?]+$/,'')).filter(Boolean) : [];
  }

  function parseYouTubeId(u){
    try{
      const url = new URL(u);
      const host = (url.hostname||'').toLowerCase();
      if (host === 'youtu.be'){
        const id = (url.pathname||'').replace(/^\//,'').split('/')[0];
        return id || '';
      }
      if (host.endsWith('youtube.com') || host.endsWith('youtube-nocookie.com')){
        if (url.pathname.startsWith('/watch')){
          return url.searchParams.get('v') || '';
        }
        const m1 = url.pathname.match(/^\/shorts\/([^/?#]+)/i);
        if (m1) return m1[1];
        const m2 = url.pathname.match(/^\/embed\/([^/?#]+)/i);
        if (m2) return m2[1];
        const m3 = url.pathname.match(/^\/live\/([^/?#]+)/i);
        if (m3) return m3[1];
      }
    }catch(_){ }
    return '';
  }

  function parseVimeoId(u){
    try{
      const url = new URL(u);
      const host = (url.hostname||'').toLowerCase();
      if (!host.endsWith('vimeo.com')) return '';
      const m = (url.pathname||'').match(/\/(\d{6,})/);
      return m ? m[1] : '';
    }catch(_){ }
    return '';
  }

  function parsePeerTubeEmbed(u){
    // Support common PeerTube patterns:
    //   /videos/watch/<uuid>
    //   /w/<shortId>
    //   /videos/embed/<id>
    try{
      const url = new URL(u);
      const path = url.pathname || '';
      if (/\/videos\/embed\//i.test(path)){
        return url.origin + path;
      }
      const m1 = path.match(/\/videos\/watch\/([^/?#]+)/i);
      if (m1) return url.origin + '/videos/embed/' + m1[1];
      const m2 = path.match(/\/w\/([^/?#]+)/i);
      if (m2) return url.origin + '/videos/embed/' + m2[1];
    }catch(_){ }
    return '';
  }

  function renderVideoEmbedsFromText(text){
    const urls = extractUrls(text);
    if (!urls.length) return '';
    const embeds = [];
    const seen = new Set();

    for (let i=0;i<urls.length && embeds.length<3;i++){
      const u = urls[i];
      if (seen.has(u)) continue;
      seen.add(u);

      const yt = parseYouTubeId(u);
      if (yt){
        const src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(yt);
        embeds.push(
          '<div class="iac-video-embed" data-iac-embed>' +
            '<iframe src="' + esc(src) + '" loading="lazy" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>' +
          '</div>'
        );
        continue;
      }

      const vm = parseVimeoId(u);
      if (vm){
        const src = 'https://player.vimeo.com/video/' + encodeURIComponent(vm);
        embeds.push(
          '<div class="iac-video-embed" data-iac-embed>' +
            '<iframe src="' + esc(src) + '" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>' +
          '</div>'
        );
        continue;
      }

      const pt = parsePeerTubeEmbed(u);
      if (pt){
        embeds.push(
          '<div class="iac-video-embed" data-iac-embed>' +
            '<iframe src="' + esc(pt) + '" loading="lazy" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>' +
          '</div>'
        );
        continue;
      }

      // Direct video file link in text.
      if (/\.(mp4|webm|ogg)(\?|#|$)/i.test(u)){
        embeds.push(
          '<div class="iac-video-embed is-file" data-iac-embed>' +
            '<video src="' + esc(u) + '" controls playsinline preload="metadata"></video>' +
          '</div>'
        );
        continue;
      }
    }

    return embeds.length ? ('<div class="iac-embeds">' + embeds.join('') + '</div>') : '';
  }

  function escapeRegExp(s){
    return String(s||'').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  }

  function isEmbeddableVideoUrl(u){
    if (!u) return false;
    if (parseYouTubeId(u)) return true;
    if (parseVimeoId(u)) return true;
    if (parsePeerTubeEmbed(u)) return true;
    if (/\.(mp4|webm|ogg)(\?|#|$)/i.test(u)) return true;
    return false;
  }

  function stripEmbeddableVideoUrls(text){
    let t = String(text||'');
    const urls = extractUrls(t);
    if (!urls.length) return t;
    const seen = new Set();
    urls.forEach(u=>{
      if (!u || seen.has(u)) return;
      seen.add(u);
      if (!isEmbeddableVideoUrl(u)) return;
      const re = new RegExp('(?:^|\\s)'+ escapeRegExp(u) + '(?=\\s|$)', 'g');
      t = t.replace(re, (m)=>{
        // Preserve any leading whitespace captured by (?:^|\s)
        return m[0] === ' ' || m[0] === '\n' || m[0] === '\t' ? m[0] : '';
      });
      // Also remove any remaining direct occurrences (e.g. punctuation-adjacent)
      t = t.replace(new RegExp(escapeRegExp(u), 'g'), '');
    });
    // Tidy whitespace/newlines after removals.
    t = t.replace(/[ \t]+\n/g, '\n');
    t = t.replace(/\n{3,}/g, '\n\n');
    return t.trim();
  }

  function profileUrlFrom(u){
    const url = new URL(location.href);
    url.searchParams.set('tab','connect');
    if (u && u.phpbb_user_id) url.searchParams.set('ia_profile', String(u.phpbb_user_id));
    if (u && u.username) url.searchParams.set('ia_profile_name', String(u.username));
    return url.toString();
  }

  function userLinkHtml(label, phpbbId, username, extraClass){
    const pid = parseInt(phpbbId||0,10)||0;
    const uname = String(username||'');
    const cls = extraClass ? (' ' + String(extraClass)) : '';
    if (!pid && !uname) return '<span class="iac-user-name">' + esc(label||'User') + '</span>';
    return '<button type="button" class="iac-userlink' + cls + '" data-iac-userlink data-phpbb="' + esc(pid) + '" data-username="' + esc(uname) + '">' + esc(label||'User') + '</button>';
  }

  function hydrateMentions(_mount){
    // Placeholder for future mention interactions.
  }

  // Small modal-style toast popup (non-blocking)
  function showToast(message, ms){
    const ttl = typeof ms === 'number' ? ms : 2200;
    let wrap = document.querySelector('.iac-toast-wrap');
    if (!wrap){
      wrap = document.createElement('div');
      wrap.className = 'iac-toast-wrap';
      // Inline fallback styling (helps if cached CSS prevents new rules applying)
      wrap.style.position = 'fixed';
      wrap.style.left = '50%';
      wrap.style.bottom = '18px';
      wrap.style.transform = 'translateX(-50%)';
      // Must sit above all Atrium/modals.
      wrap.style.zIndex = '999999';
      wrap.style.display = 'flex';
      wrap.style.flexDirection = 'column';
      wrap.style.gap = '10px';
      wrap.style.pointerEvents = 'none';
      document.body.appendChild(wrap);
    }
    const t = document.createElement('div');
    t.className = 'iac-toast';
    t.style.pointerEvents = 'auto';
    t.style.minWidth = 'min(420px,92vw)';
    t.style.background = 'rgba(0,0,0,.88)';
    t.style.border = '1px solid rgba(255,255,255,.12)';
    t.style.borderRadius = '14px';
    t.style.padding = '10px 12px';
    t.style.boxShadow = '0 18px 50px rgba(0,0,0,.55)';
    t.style.display = 'flex';
    t.style.alignItems = 'center';
    t.style.gap = '10px';
    t.innerHTML = '<div class="iac-toast-txt"></div><button type="button" class="iac-toast-x" aria-label="Close">×</button>';
    t.querySelector('.iac-toast-txt').textContent = String(message||'');
    t.querySelector('.iac-toast-txt').style.color = '#fff';
    t.querySelector('.iac-toast-txt').style.fontSize = '14px';
    t.querySelector('.iac-toast-txt').style.lineHeight = '1.3';
    const x = t.querySelector('.iac-toast-x');
    x.style.background = 'rgba(255,255,255,.08)';
    x.style.border = '1px solid rgba(255,255,255,.12)';
    x.style.color = '#fff';
    x.style.borderRadius = '10px';
    x.style.padding = '6px 10px';
    x.style.cursor = 'pointer';
    x.style.fontSize = '14px';
    const kill = ()=>{ if (t && t.parentNode) t.parentNode.removeChild(t); };
    t.querySelector('.iac-toast-x').addEventListener('click', (e)=>{ e.preventDefault(); kill(); });
    wrap.appendChild(t);
    window.setTimeout(kill, ttl);
  }

  function mount(){
    const root = qs(document, '.iac-profile');
    if (!root) return;

    const viewer = qs(document, '[data-iac-viewer]');
    const viewerBody = qs(document, '[data-iac-viewer-body]');

    // Post modal
    const postModal = qs(document, '[data-iac-post-modal]');
    const postBody  = qs(document, '[data-iac-post-body]');
    const postComms = qs(document, '[data-iac-post-comments]');
    const postCommentInput = qs(document, '[data-iac-post-comment]');
    const postCommentSend  = qs(document, '[data-iac-post-comment-send]');
    const postCopyBtn = qs(document, '[data-iac-post-copy]');
    const postShareBtn = qs(document, '[data-iac-post-share]');

    // Share modal
    const shareModal = qs(document, '[data-iac-share-modal]');
    const shareSearch = qs(document, '[data-iac-share-search]');
    const shareResults = qs(document, '[data-iac-share-results]');
    const sharePicked = qs(document, '[data-iac-share-picked]');
    const shareSend = qs(document, '[data-iac-share-send]');
    const shareSelf = qs(document, '[data-iac-share-self]');

    let currentOpenPostId = 0;
    let shareForPostId = 0;
    let sharePickedUsers = [];
    let lastUrlBeforeModal = '';

    function openViewer(payload){
      if (!viewer || !viewerBody) return;
      viewerBody.innerHTML = '';
      const src = String(payload || '');

      // Try infer type
      const isVideo = /\.(mp4|webm|ogg)(\?|#|$)/i.test(src);
      const isImage = /\.(png|jpe?g|gif|webp|avif)(\?|#|$)/i.test(src);
      const isPdf   = /\.(pdf)(\?|#|$)/i.test(src);

      if (isVideo){
        const v = document.createElement('video');
        v.src = src;
        v.controls = true;
        v.playsInline = true;
        viewerBody.appendChild(v);
      } else if (isPdf){
        const iframe = document.createElement('iframe');
        iframe.src = src;
        viewerBody.appendChild(iframe);
      } else if (isImage || src){
        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        viewerBody.appendChild(img);
      }

      viewer.hidden = false;
    }

    function closeViewer(){
      if (!viewer || !viewerBody) return;
      viewer.hidden = true;
      viewerBody.innerHTML = '';
    }

    qsa(document, '[data-iac-viewer-close]').forEach(el=>{
      el.addEventListener('click', (e)=>{ e.preventDefault(); closeViewer(); });
    });

    function openPostModal(pid, pushState){
      if (!postModal) return;
      currentOpenPostId = pid;
      try { if (postCommentSend) postCommentSend.setAttribute('data-post-id', String(pid)); } catch (e) {}
      postModal.hidden = false;
      document.documentElement.style.overflow = 'hidden';

      if (pushState){
        try{
          lastUrlBeforeModal = location.href;
          const u = new URL(location.href);
          u.searchParams.set('tab','connect');
          u.searchParams.set('ia_post', String(pid));
          u.searchParams.delete('ia_comment');
          history.pushState({iac_post: pid}, '', u.toString());
        }catch(_){ }
      }

      if (postBody) postBody.innerHTML = '<div class="iac-card" style="margin:0">Loading…</div>';
      if (postComms) postComms.innerHTML = '';
      if (postCommentInput) postCommentInput.value = '';

      loadPostForModal(pid);
    }

    function closePostModal(popState){
      if (!postModal) return;
      postModal.hidden = true;
      document.documentElement.style.overflow = '';
      currentOpenPostId = 0;
      if (!popState){
        try{
          if (lastUrlBeforeModal) history.pushState({}, '', lastUrlBeforeModal);
          else {
            const u = new URL(location.href);
            u.searchParams.delete('ia_post');
            history.pushState({}, '', u.toString());
          }
        }catch(_){ }
      }
    }

    function openShareModal(pid){
      if (!shareModal) return;
      shareForPostId = pid;
      sharePickedUsers = [];
      if (sharePicked) sharePicked.innerHTML = '';
      if (shareResults) shareResults.innerHTML = '';
      if (shareSearch) shareSearch.value = '';
      if (shareSend) shareSend.disabled = true;
      shareModal.hidden = false;
    }

    function closeShareModal(){
      if (!shareModal) return;
      shareModal.hidden = true;
      shareForPostId = 0;
      sharePickedUsers = [];
    }

    qsa(document,'[data-iac-share-close]').forEach(el=>{
      el.addEventListener('click', (e)=>{ e.preventDefault(); closeShareModal(); });
    });

    function renderPicked(){
      if (!sharePicked) return;
      sharePicked.innerHTML = sharePickedUsers.map(u=>
        '<span class="iac-chip" data-key="' + esc(u.phpbb_user_id||u.wp_user_id||0) + '">' +
          esc(u.display||u.username||'User') +
          '<button type="button" class="iac-chip-x" data-iac-chip-x aria-label="Remove">×</button>' +
        '</span>'
      ).join('');
      // Enable when something is selected; if no valid targets, we'll show an error on send.
      if (shareSend) shareSend.disabled = sharePickedUsers.length === 0;
    }

    if (sharePicked){
      sharePicked.addEventListener('click', (e)=>{
        const x = e.target.closest('[data-iac-chip-x]');
        if (!x) return;
        const chip = e.target.closest('.iac-chip');
        const key = parseInt(chip?.getAttribute('data-key')||'0',10)||0;
        sharePickedUsers = sharePickedUsers.filter(u => ((u.phpbb_user_id||u.wp_user_id||0) !== key));
        renderPicked();
      });
    }

    function addPicked(user){
      const id = user?.phpbb_user_id || user?.wp_user_id || 0;
      if (!id && !user?.username) return;
      if (sharePickedUsers.some(u => ((u.phpbb_user_id||u.wp_user_id||0) === id && id) || (user?.username && (u.username||'') === user.username))) return;
      sharePickedUsers.push(user);
      renderPicked();
    }

    const deb = (fn, ms)=>{ let t=0; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), ms); }; };
    const doShareSearch = deb(async ()=>{
      if (!shareResults) return;
      const q = String(shareSearch?.value||'').trim();
      if (!q){ shareResults.innerHTML=''; return; }
      try{
        const r = await postForm('ia_connect_mention_suggest', {nonce: nonces.mention_suggest||'', q});
        if (!r || !r.success) throw r;
        const rows = r.data.results || [];
        const blankAva = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
        shareResults.innerHTML = rows.map(u=>
          '<div class="iac-share-row" data-iac-share-pick data-phpbb="' + esc(u.phpbb_user_id||0) + '" data-wp="' + esc(u.wp_user_id||0) + '" data-username="' + esc(u.username||'') + '" data-display="' + esc(u.display||u.username||'User') + '">' +
            '<img class="iac-share-ava" src="' + esc(u.avatarUrl||blankAva) + '" alt="" />' +
            '<div class="iac-share-name">' + esc(u.display||u.username||'User') + '</div>' +
          '</div>'
        ).join('');
      } catch(_){
        shareResults.innerHTML = '';
      }
    }, 180);

    if (shareSearch){
      shareSearch.addEventListener('input', doShareSearch);
    }

    if (shareResults){
      const pickShareUser = (e)=>{
        const row = e.target.closest('[data-iac-share-pick]');
        if (!row) return;
        e.preventDefault();
        const phpbb = parseInt(row.getAttribute('data-phpbb')||'0',10)||0;
        const wpId = parseInt(row.getAttribute('data-wp')||'0',10)||0;
        const uname = row.getAttribute('data-username') || '';
        const disp = row.getAttribute('data-display') || (uname || 'User');
        addPicked({phpbb_user_id: phpbb, wp_user_id: wpId, username: uname, display: disp});
      };
      // Desktop + mobile
      shareResults.addEventListener('mousedown', pickShareUser);
      shareResults.addEventListener('click', pickShareUser);
      shareResults.addEventListener('touchstart', pickShareUser, {passive:false});
    }

    if (shareSelf){
      shareSelf.addEventListener('click', async ()=>{
        if (!shareForPostId) return;
        try{
          const r = await postForm('ia_connect_post_share', {nonce: nonces.post_share||'', post_id: shareForPostId, share_to_self: 1});
          if (!r || !r.success) throw r;
          showToast('Shared to your wall');
          closeShareModal();
        } catch(e){
          alert(e?.data?.message || 'Share failed');
        }
      });
    }

    if (shareSend){
      shareSend.addEventListener('click', async ()=>{
        if (!shareForPostId) return;
        const targets = sharePickedUsers.map(u=>u.phpbb_user_id||0).filter(Boolean);
        if (!targets.length) { alert('Selected user(s) have no phpBB id mapping yet.'); return; }
        shareSend.disabled = true;
        try{
          const r = await postForm('ia_connect_post_share', {nonce: nonces.post_share||'', post_id: shareForPostId, targets});
          if (!r || !r.success) throw r;
          showToast('Shared to ' + (r.data.created_count||targets.length) + ' wall(s)');
          closeShareModal();
        } catch(e){
          alert(e?.data?.message || 'Share failed');
        } finally {
          shareSend.disabled = false;
        }
      });
    }

    qsa(document,'[data-iac-post-close]').forEach(el=>{
      el.addEventListener('click', (e)=>{ e.preventDefault(); closePostModal(false); });
    });

    async function loadPostForModal(pid){
      try{
        const r = await postForm('ia_connect_post_get', {nonce: nonces.post_get||'', post_id: pid});
        if (!r || !r.success) throw r;
        const p = r.data.post;
        const comments = r.data.comments || [];
        if (postBody) postBody.innerHTML = renderCard(p, {mode:'modal'});
        if (postComms) postComms.innerHTML = renderCommentThread(comments);
        if (postBody) hydrateGalleries(postBody);
        if (postComms) hydrateMentions(postComms);

        // If opened from an email (or deep link), optionally highlight a specific comment.
        try{
          const u = new URL(location.href);
          const cid = parseInt(u.searchParams.get('ia_comment')||'0',10)||0;
          if (cid > 0 && postComms){
            const el = postComms.querySelector('[data-comment-id="' + cid + '"]');
            if (el){
              el.classList.add('iac-comment-highlight');
              // Scroll it into view in the modal.
              setTimeout(()=>{ try{ el.scrollIntoView({block:'center'}); }catch(_){ } }, 50);
            }
          }
        }catch(_){ }
      } catch(e){
        if (postBody) postBody.innerHTML = '<div class="iac-card" style="margin:0">Failed to load.</div>';
      }
    }

    function renderCommentThread(comments){
      if (!comments || !comments.length) return '<div class="iac-card" style="margin:0">No comments yet.</div>';
      const byParent = {};
      comments.forEach(c=>{
        const pid = String(c.parent_comment_id||0);
        (byParent[pid] = byParent[pid] || []).push(c);
      });
      const renderLevel = (parentId, depth)=>{
        const list = byParent[String(parentId||0)] || [];
        return list.map(c=>{
          const kids = renderLevel(c.id, depth+1);
          const pad = depth ? (' style="margin-left:' + Math.min(24, depth*12) + 'px"') : '';
          return (
            '<div class="iac-comment"' + pad + ' data-comment-id="' + esc(c.id) + '">' +
              '<img class="iac-comment-ava" src="' + esc(c.author_avatar||BLANK_AVA) + '" alt="" />' +
                '<div class="iac-comment-bub">' +
                  '<div class="iac-comment-a">' + userLinkHtml(c.author||'User', c.author_phpbb_id, c.author_username, 'is-comment') + '</div>' +
                '<div class="iac-comment-t">' + linkMentions(c.body||'') + '</div>' +
              '</div>' +
            '</div>' +
            kids
          );
        }).join('');
      };
      return renderLevel(0,0);
    }

    if (postCopyBtn){
      postCopyBtn.addEventListener('click', async ()=>{
        if (!currentOpenPostId) return;
        try{
          const u = new URL(location.href);
          u.searchParams.set('tab','connect');
          u.searchParams.set('ia_post', String(currentOpenPostId));
          await navigator.clipboard.writeText(u.toString());
        }catch(_){ }
      });
    }

    if (postShareBtn){
      postShareBtn.addEventListener('click', ()=>{ if (currentOpenPostId) openShareModal(currentOpenPostId); });
    }

    if (postCommentSend){
      postCommentSend.addEventListener('click', async ()=>{
        const pid = currentOpenPostId || (parseInt(postCommentSend.getAttribute('data-post-id')||'0',10)||0);
        if (!pid) return;
        const txt = String(postCommentInput?.value||'').trim();
        if (!txt) return;
        postCommentSend.disabled = true;
        try{
          const r = await postForm('ia_connect_comment_create', {
            nonce: nonces.comment_create||'',
            post_id: pid,
            parent_comment_id: 0,
            body: txt
          });
          if (!r || !r.success) throw r;
          const c = r.data.comment;
          if (postComms){
            // If there was a "No comments" placeholder, replace it.
            if (/No comments yet/i.test(postComms.textContent||'')) postComms.innerHTML = '';
            postComms.insertAdjacentHTML('beforeend',
              '<div class="iac-comment" data-comment-id="' + esc(c.id) + '">' +
                '<img class="iac-comment-ava" src="' + esc(c.author_avatar||BLANK_AVA) + '" alt="" />' +
                  '<div class="iac-comment-bub"><div class="iac-comment-a">' + userLinkHtml(c.author||'User', c.author_phpbb_id, c.author_username, 'is-comment') + '</div>' +
                '<div class="iac-comment-t">' + linkMentions(c.body||'') + '</div></div></div>'
            );
          }
          if (postCommentInput) postCommentInput.value = '';
        } catch(e){
          alert(e?.data?.message || 'Comment failed');
        } finally {
          postCommentSend.disabled = false;
        }
      });
    }

    // Mentions: lightweight @username suggestions
    const mentionBox = document.createElement('div');
    mentionBox.className = 'iac-mentionbox';
    mentionBox.hidden = true;
    document.body.appendChild(mentionBox);

    let mentionActiveEl = null;
    let mentionToken = '';
    let mentionItems = [];

    function hideMention(){
      mentionBox.hidden = true;
      mentionBox.innerHTML = '';
      mentionActiveEl = null;
      mentionToken = '';
      mentionItems = [];
    }

    async function fetchMentions(q){
      const r = await postForm('ia_connect_mention_suggest', {nonce: nonces.mention_suggest||'', q});
      if (!r || !r.success) return [];
      return r.data.results || [];
    }

    const mentionDebounced = deb(async (q)=>{
      if (!mentionActiveEl) return;
      const items = await fetchMentions(q);
      mentionItems = items;
      if (!items.length){ hideMention(); return; }
      mentionBox.innerHTML = items.map((u, idx)=>
        '<div class="iac-mention-row" data-iac-mention-pick data-idx="' + esc(idx) + '">' +
          '<img class="iac-mention-ava" src="' + esc(u.avatarUrl||'') + '" alt="" />' +
          '<div>' +
            '<div class="iac-mention-name">' + esc(u.display||u.username||'User') + '</div>' +
            '<div class="iac-mention-user">@' + esc(u.username||'user') + '</div>' +
          '</div>' +
        '</div>'
      ).join('');

      // Position under the input
      const rct = mentionActiveEl.getBoundingClientRect();
      const top = Math.min(window.innerHeight - 10, rct.bottom + 6);
      const left = Math.min(window.innerWidth - 10, rct.left);
      mentionBox.style.top = top + 'px';
      mentionBox.style.left = left + 'px';
      mentionBox.hidden = false;
    }, 160);

    function getAtToken(el){
      const v = String(el.value||'');
      const pos = (typeof el.selectionStart === 'number') ? el.selectionStart : v.length;
      const before = v.slice(0,pos);
      const m = before.match(/(^|\s)@([a-zA-Z0-9_\-\.]{1,40})$/);
      if (!m) return '';
      return m[2] || '';
    }

    function replaceAtToken(el, username){
      const v = String(el.value||'');
      const pos = (typeof el.selectionStart === 'number') ? el.selectionStart : v.length;
      const before = v.slice(0,pos);
      const after = v.slice(pos);
      const m = before.match(/(^|\s)@([a-zA-Z0-9_\-\.]{1,40})$/);
      if (!m) return;
      const start = before.length - m[2].length - 1; // include '@'
      const pre = v.slice(0,start);
      const ins = '@' + username + ' ';
      const next = pre + ins + after;
      el.value = next;
      const caret = (pre + ins).length;
      if (el.setSelectionRange) el.setSelectionRange(caret, caret);
    }

    function attachMention(el){
      if (!el) return;
      el.addEventListener('input', ()=>{
        const token = getAtToken(el);
        if (!token){ hideMention(); return; }
        mentionActiveEl = el;
        mentionToken = token;
        mentionDebounced(token);
      });
      el.addEventListener('blur', ()=>{ setTimeout(()=>hideMention(), 120); });
    }

    mentionBox.addEventListener('mousedown', (e)=>{
      const row = e.target.closest('[data-iac-mention-pick]');
      if (!row) return;
      e.preventDefault();
      const idx = parseInt(row.getAttribute('data-idx')||'0',10)||0;
      const u = mentionItems[idx];
      if (!u || !mentionActiveEl) return;
      replaceAtToken(mentionActiveEl, u.username||'');
      hideMention();
      try{ mentionActiveEl.focus(); }catch(_){ }
    });

    // Popstate: allow browser back to close modal
    window.addEventListener('popstate', ()=>{
      const u = new URL(location.href);
      const pid = parseInt(u.searchParams.get('ia_post')||'0',10)||0;
      if (pid > 0){
        if (!postModal || postModal.hidden) openPostModal(pid, false);
      } else {
        if (postModal && !postModal.hidden) closePostModal(true);
      }
    });

    // Click-to-view (cover/avatar + attachment media)
    root.addEventListener('click', (e)=>{
      const change = e.target.closest('[data-iac-change]');
      if (change){
        e.preventDefault();
        e.stopPropagation();
        const kind = change.getAttribute('data-iac-change');
        if (kind === 'profile') {
          const pick = qs(document, '[data-iac-filepick-profile]');
          if (pick) pick.click();
        }
        if (kind === 'cover') {
          const pick = qs(document, '[data-iac-filepick-cover]');
          if (pick) pick.click();
        }
        return;
      }

      const view = e.target.closest('[data-iac-view]');
      if (view){
        e.preventDefault();
        const src = view.getAttribute('data-iac-view');
        openViewer(src);
      }
    });

    // File pickers
    function uploadOne(action, nonceKey, file, cb){
      const fd = new FormData();
      fd.append('action', action);
      fd.append('nonce', nonces[nonceKey] || '');
      fd.append('file', file);
      const xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl, true);
      xhr.onreadystatechange = ()=>{
        if (xhr.readyState !== 4) return;
        let json = null; try{ json = JSON.parse(xhr.responseText||'{}'); }catch(e){}
        if (xhr.status >= 200 && xhr.status < 300 && json && json.success){
          cb(null, json.data);
        } else {
          cb(json?.data?.message || 'Upload failed');
        }
      };
      xhr.send(fd);
    }

    const profPick = qs(document, '[data-iac-filepick-profile]');
    if (profPick){
      profPick.addEventListener('change', ()=>{
        const f = profPick.files && profPick.files[0];
        if (!f) return;
        uploadOne('ia_connect_upload_profile','profile_photo',f,(err,data)=>{
          profPick.value = '';
          if (err) return alert(err);
          const img = qs(root, '.iac-avatar-img');
          if (img && data.url) img.src = data.url + (data.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
        });
      });
    }

    const covPick = qs(document, '[data-iac-filepick-cover]');
    if (covPick){
      covPick.addEventListener('change', ()=>{
        const f = covPick.files && covPick.files[0];
        if (!f) return;
        uploadOne('ia_connect_upload_cover','cover_photo',f,(err,data)=>{
          covPick.value = '';
          if (err) return alert(err);
          const img = qs(root, '.iac-cover-img');
          if (img && data.url) img.src = data.url + (data.url.includes('?') ? '&' : '?') + 'v=' + Date.now();
          if (!img && data.url){
            // If fallback, inject img
            const cover = qs(root, '.iac-cover');
            if (cover){
              cover.innerHTML = '<img class="iac-cover-img" src="' + esc(data.url) + '" alt="Cover" />' + cover.innerHTML;
            }
          }
        });
      });
    }

    // Tabs
    qsa(root, '[data-iac-tab]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const t = btn.getAttribute('data-iac-tab');
        qsa(root, '[data-iac-tab]').forEach(b=>b.classList.toggle('is-active', b===btn));
        qsa(root, '[data-iac-panel]').forEach(p=>p.classList.toggle('is-active', p.getAttribute('data-iac-panel')===t));
      });
    });


    // Profile menu integration (ia-profile-menu dispatches ia_connect:profileMenu)
    window.addEventListener("ia_connect:profileMenu", function(ev){
      const action = ev && ev.detail ? String(ev.detail.action||"") : "";
      if (!action) return;
      function goTab(tab){
        const btn = document.querySelector("[data-iac-tab=\""+tab+"\"]");
        if (btn) btn.click();
      }

      function clickWhenReady(selector, tries){
        let left = typeof tries === "number" ? tries : 20;
        const tick = ()=>{
          const b = document.querySelector(selector);
          if (b){ b.click(); return; }
          left--;
          if (left <= 0) return;
          setTimeout(tick, 120);
        };
        tick();
      }

      if (action === "settings" || action === "privacy"){
        goTab(action);
        return;
      }

      // These live inside Settings, so navigate there first, then trigger.
      function scrollToSection(key){
        try{
          const el = document.querySelector('[data-iac-settings-section="'+key+'"]');
          if (el && typeof el.scrollIntoView === 'function'){
            el.scrollIntoView({ behavior:'smooth', block:'start' });
          }
        }catch(_){ }
      }

      if (action === "export"){
        goTab("settings");
        scrollToSection('export');
        // Optional: auto-click export button once the settings panel is visible.
        clickWhenReady("[data-iac-acct-export]", 30);
        return;
      }
      if (action === "deactivate"){
        goTab("settings");
        scrollToSection('deactivate');
        return;
      }
      if (action === "delete"){
        goTab("settings");
        scrollToSection('delete');
        return;
      }
    });

    // Message button (delegated, survives SPA rerenders)
    document.addEventListener('click', (e)=>{
      const btn = e.target && e.target.closest ? e.target.closest('[data-iac-message]') : null;
      if (!btn) return;
      try { e.preventDefault(); e.stopPropagation(); } catch(_){ }
      const prof = document.querySelector('[data-iac-profile]');
      const toPhpbb = prof ? (parseInt(prof.getAttribute('data-wall-phpbb') || '0', 10) || 0) : 0;
      if (!toPhpbb) return;
      try { window.localStorage && localStorage.setItem('ia_msg_to', String(toPhpbb)); } catch(_){ }
      try { window.__IA_MESSAGE_PENDING_DM_TO = toPhpbb; window.__IA_MESSAGE_DEEPLINK_DONE = false; } catch(_){ }
      try { if (window.IA_ATRIUM && typeof window.IA_ATRIUM.setTab === 'function') { window.IA_ATRIUM.setTab('messages'); return; } } catch(_){ }
      try { if (window.IA_ATRIUM && typeof window.IA_ATRIUM.openTab === 'function') { window.IA_ATRIUM.openTab('messages'); return; } } catch(_){ }
      try { const t = document.querySelector('a[href*="tab=messages"], button[data-tab="messages"], a[data-tab="messages"], [data-ia-tab="messages"], [data-tab-key="messages"]'); if (t) { t.click(); return; } } catch(_){ }
      try { const u = new URL(location.href); u.searchParams.set('tab','messages'); location.href = u.toString(); } catch(_){ }
    }, true);

    // Search: users + wall
    const searchInput = qs(document, '.iac-search-input');
    const resultsBox = qs(document, '.iac-search-results');
    let searchTimer = null;

    function hideResults(){ if (resultsBox){ resultsBox.hidden = true; resultsBox.innerHTML = ''; } }

    function renderSearch(users, posts, comments){
      if (!resultsBox) return;
      const blankAva = 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=';
      let html = '';
      if (users && users.length){
        html += '<div class="iac-search-hdr">Users</div>';
        users.forEach(u=>{
          html += '<div class="iac-search-row" data-iac-go-profile="1" data-phpbb="' + (u.phpbb_user_id||0) + '" data-username="' + esc(u.username) + '">' +
            '<img class="iac-search-ava" src="' + esc(u.avatarUrl||blankAva) + '" alt="" />' +
            '<div class="iac-search-txt"><div class="iac-search-name">' + esc(u.display||u.username) + '</div>' +
            '<div class="iac-search-sub">@' + esc(u.username) + '</div></div></div>';
        });
      }

      if (posts && posts.length){
        html += '<div class="iac-search-hdr">Posts</div>';
        posts.forEach(p=>{
          html += '<div class="iac-search-row" data-iac-go-post="' + (p.id||0) + '">' +
            '<img class="iac-search-ava" src="' + esc(p.author_avatar||blankAva) + '" alt="" />' +
            '<div class="iac-search-txt"><div class="iac-search-name">' + esc(p.title || '(status)') + '</div>' +
            '<div class="iac-search-sub">' + esc((p.author||'User') + ' · #' + p.id) + '</div></div></div>';
        });
      }

      if (comments && comments.length){
        html += '<div class="iac-search-hdr">Comments</div>';
        comments.forEach(c=>{
          html += '<div class="iac-search-row" data-iac-go-post="' + (c.post_id||0) + '">' +
            '<img class="iac-search-ava" src="' + esc(c.author_avatar||blankAva) + '" alt="" />' +
            '<div class="iac-search-txt"><div class="iac-search-name">' + esc(c.author||'User') + '</div>' +
            '<div class="iac-search-sub">' + esc(String(c.body||'').slice(0,80)) + '</div></div></div>';
        });
      }

      if (!html) html = '<div class="iac-search-row"><div class="iac-search-txt"><div class="iac-search-name">No results</div></div></div>';
      resultsBox.innerHTML = html;
      resultsBox.hidden = false;
    }

    async function runSearch(q){
      if (!q){ hideResults(); return; }
      try{
        const r1 = await postForm('ia_connect_user_search', {nonce: nonces.user_search||'', q});
        const users = (r1 && r1.success ? (r1.data.results||[]) : []);
        const r2 = await postForm('ia_connect_wall_search', {nonce: nonces.wall_search||'', q});
        const posts = (r2 && r2.success ? (r2.data.posts||[]) : []);
        const comments = (r2 && r2.success ? (r2.data.comments||[]) : []);
        renderSearch(users, posts, comments);
      } catch(e){
        // Silent
      }
    }

    if (searchInput){
      searchInput.addEventListener('input', ()=>{
        clearTimeout(searchTimer);
        const q = String(searchInput.value||'').trim();
        searchTimer = setTimeout(()=>runSearch(q), 200);
      });
      searchInput.addEventListener('focus', ()=>{
        const q = String(searchInput.value||'').trim();
        if (q) runSearch(q);
      });
      document.addEventListener('click', (e)=>{
        if (!resultsBox) return;
        if (e.target.closest('.iac-search')) return;
        hideResults();
      });
    }

    if (resultsBox){
      resultsBox.addEventListener('click', (e)=>{
        const row = e.target.closest('.iac-search-row');
        if (!row) return;
        if (row.getAttribute('data-iac-go-profile')){
          hideResults();
          const uname = row.getAttribute('data-username') || '';
          const pid = parseInt(row.getAttribute('data-phpbb')||'0',10)||0;
          const url = new URL(location.href);
          url.searchParams.set('tab','connect');
          if (pid) url.searchParams.set('ia_profile', String(pid));
          if (uname) url.searchParams.set('ia_profile_name', uname);
          location.href = url.toString();
          return;
        }

        const postId = parseInt(row.getAttribute('data-iac-go-post')||'0',10)||0;
        if (postId){
          hideResults();
          const contentTab = qs(root,'[data-iac-tab="content"]');
          if (contentTab) contentTab.click();
          openPostModal(postId, true);
        }
      });
    }

    // Username links (cards, comments, etc.)
    document.addEventListener('click', (e)=>{
      const btn = e.target.closest('[data-iac-userlink]');
      if (!btn) return;
      e.preventDefault();
      const pid = parseInt(btn.getAttribute('data-phpbb')||'0',10)||0;
      const uname = btn.getAttribute('data-username') || '';
      const url = new URL(location.href);
      url.searchParams.set('tab','connect');
      // Leaving the post modal => remove deep-link param.
      url.searchParams.delete('ia_post');
      if (pid) url.searchParams.set('ia_profile', String(pid));
      if (uname) url.searchParams.set('ia_profile_name', uname);
      // If a fullscreen post modal is open, close it first so the profile is visible.
      try{
        if (postModal && !postModal.hidden) {
          closePostModal(false);
        }
      }catch(_){ }

      // Navigate to profile.
      location.href = url.toString();
    });

    // Feed
    const wallWp = parseInt(root.getAttribute('data-wall-wp')||'0',10)||0;
    const wallPhpbb = parseInt(root.getAttribute('data-wall-phpbb')||'0',10)||0;

    const feedInner = qs(root, '[data-iac-feed-inner]');
    const loadMoreBtn = qs(root, '[data-iac-loadmore]');
    let oldestId = 0;
    let loading = false;

    function renderAttachmentGallery(atts){
      if (!atts || !atts.length) return '';
      const safe = atts.map(a=>({url:a.url, kind:a.kind, name:a.name}));
      const data = esc(JSON.stringify(safe));
      return (
        '<div class="iac-gallery" data-iac-gallery data-items="' + data + '">' +
          '<div class="iac-g-stage">' +
            '<div class="iac-g-media" data-iac-g-media></div>' +
            '<button type="button" class="iac-g-nav iac-g-prev" data-iac-g-prev aria-label="Prev">‹</button>' +
            '<button type="button" class="iac-g-nav iac-g-next" data-iac-g-next aria-label="Next">›</button>' +
            '<div class="iac-g-count" data-iac-g-count></div>' +
          '</div>' +
        '</div>'
      );
    }

    function renderCard(p, opts){
      opts = opts || {};
      if (!p || !p.id) return '';
      const title = esc(p.title||'');
	  const bodyRaw = (p.body||'');
	  const bodyClean = stripEmbeddableVideoUrls(bodyRaw);
	  const body = linkMentions(bodyClean);
	  const embeds = renderVideoEmbedsFromText(bodyRaw);
      const when = esc(p.created_at||'');
      const isRepost = (p.type === 'repost' || p.type === 'mention') && p.parent_post;

      // Repost chains can nest repost -> repost -> original post. Resolve to the first
      // non-repost so the nested card shows real content.
      function resolveOriginal(post){
        let cur = post;
        let guard = 0;
        while (cur && (cur.type === 'repost' || cur.type === 'mention') && cur.parent_post && guard < 8){
          cur = cur.parent_post;
          guard++;
        }
        return cur || post;
      }

      const repostLine = (()=>{
        if (!isRepost) return '';
        if (p.type === 'mention') {
          return '<div class="iac-card-text iac-repostline">Mentioned you</div>';
        }
        const sameWall = (parseInt(p.wall_owner_phpbb_id||0,10)||0) === (parseInt(p.author_phpbb_id||0,10)||0);
        return '<div class="iac-card-text iac-repostline">' + (sameWall ? 'Shared a post' : 'Shared a post with you') + '</div>';
      })();

      let nested = '';
      if (isRepost){
        const op = resolveOriginal(p.parent_post);
	    const opBodyRaw = (op.body||'');
	    const opEmbeds = renderVideoEmbedsFromText(opBodyRaw);
	    const opBodyClean = stripEmbeddableVideoUrls(opBodyRaw);
        nested = '<div class="iac-card" style="margin-top:10px;opacity:.98">' +
          '<div class="iac-card-head"><img class="iac-card-ava" src="' + esc(op.author_avatar||BLANK_AVA) + '" alt="" />' +
          '<div class="iac-card-hmeta"><div class="iac-card-author">' + userLinkHtml(op.author||'User', op.author_phpbb_id, op.author_username) + '</div>' +
          '<div class="iac-card-time">Shared post · #' + esc(op.id) + '</div></div></div>' +
          '<div class="iac-card-body">' +
            (op.title ? '<div class="iac-card-title">' + esc(op.title) + '</div>' : '') +
	        (opBodyClean ? '<div class="iac-card-text">' + linkMentions(opBodyClean) + '</div>' : '') +
            (opEmbeds || '') +
            renderAttachmentGallery(op.attachments||[]) +
          '</div></div>';
      }

      const gallery = (!isRepost ? renderAttachmentGallery(p.attachments||[]) : '');

      const preview = (p.comments_preview||[]).map(c=>{
        return '<div class="iac-comment">' +
          '<img class="iac-comment-ava" src="' + esc(c.author_avatar||BLANK_AVA) + '" alt="" />' +
          '<div class="iac-comment-bub"><div class="iac-comment-a">' + userLinkHtml(c.author||'User', c.author_phpbb_id, c.author_username, 'is-comment') + '</div>' +
          '<div class="iac-comment-t">' + linkMentions(c.body||'') + '</div></div></div>';
      }).join('');

      const inModal = (opts.mode === 'modal');
      const actions = inModal
        ? ('<button type="button" class="iac-act" data-iac-share>Share</button>')
        : (
            '<button type="button" class="iac-act" data-iac-comment-open>Comment (' + esc(p.comment_count||0) + ')</button>' +
            '<button type="button" class="iac-act" data-iac-share>Share</button>' +
            '<button type="button" class="iac-act" data-iac-open>Open</button>'
          );

      return (
        '<article class="iac-card" data-post-id="' + esc(p.id) + '">' +
          '<div class="iac-card-head">' +
            '<img class="iac-card-ava" src="' + esc(p.author_avatar||BLANK_AVA) + '" alt="" />' +
            '<div class="iac-card-hmeta">' +
              '<div class="iac-card-author">' + userLinkHtml(p.author||'User', p.author_phpbb_id, p.author_username) + '</div>' +
              '<div class="iac-card-time">' + when + '</div>' +
            '</div>' +
          '</div>' +
          '<div class="iac-card-body">' +
            repostLine +
            (title ? '<div class="iac-card-title">' + title + '</div>' : '') +
            (body ? '<div class="iac-card-text">' + body + '</div>' : '') +
            (embeds || '') +
            gallery +
            nested +
          '</div>' +
          '<div class="iac-card-actions">' + actions + '</div>' +
          '<div class="iac-comments" hidden>' +
            '<div class="iac-comments-preview">' + preview + '</div>' +
            '<div class="iac-comment-form">' +
              '<input class="iac-comment-input" type="text" placeholder="Write a comment..." />' +
              '<button type="button" class="iac-comment-send">Send</button>' +
            '</div>' +
          '</div>' +
        '</article>'
      );
    }

    function hydrateGalleries(scope){
      qsa(scope, '[data-iac-gallery]').forEach(g=>{
        if (g.__iacHydrated) return;
        g.__iacHydrated = true;
        let items = [];
        try{ items = JSON.parse(g.getAttribute('data-items')||'[]') || []; }catch(e){}
        let idx = 0;
        const media = qs(g, '[data-iac-g-media]');
        const count = qs(g, '[data-iac-g-count]');

        function render(){
          if (!media) return;
          const it = items[idx];
          media.innerHTML = '';
          if (!it) return;
          if (it.kind === 'video'){
            const v = document.createElement('video');
            v.src = it.url;
            v.controls = true;
            v.playsInline = true;
            v.setAttribute('data-iac-view', it.url);
            media.appendChild(v);
          } else if (it.kind === 'image'){
            const img = document.createElement('img');
            img.src = it.url;
            img.alt = '';
            img.setAttribute('data-iac-view', it.url);
            media.appendChild(img);
          } else {
            const a = document.createElement('a');
            a.href = it.url;
            a.target = '_blank';
            a.rel = 'noopener';
            a.textContent = it.name || 'Open file';
            a.style.color = 'var(--iac-ac)';
            media.appendChild(a);
          }
          if (count) count.textContent = (idx+1) + ' / ' + items.length;
        }

        function safeNav(fn){
          const se = document.scrollingElement || document.documentElement;
          const st = se ? se.scrollTop : (window.pageYOffset||0);
          try{ fn(); } finally {
            // Prevent browser scroll anchoring/focus adjustments when media swaps.
            const restore = ()=>{ if (se) se.scrollTop = st; else window.scrollTo(0, st); };
            requestAnimationFrame(()=>{
              restore();
              requestAnimationFrame(restore);
              setTimeout(restore, 120);
            });
          }
        }

        function prev(){ idx = (idx - 1 + items.length) % items.length; safeNav(render); }
        function next(){ idx = (idx + 1) % items.length; safeNav(render); }

        const p = qs(g, '[data-iac-g-prev]');
        const n = qs(g, '[data-iac-g-next]');
        const bindNav = (el, fn)=>{
          if (!el) return;
          el.addEventListener('pointerdown', (e)=>{ e.preventDefault(); e.stopPropagation(); }, {passive:false});
          el.addEventListener('mousedown', (e)=>{ e.preventDefault(); e.stopPropagation(); });
          el.addEventListener('click', (e)=>{ e.preventDefault(); e.stopPropagation(); try{ el.blur && el.blur(); }catch(_){} fn(); });
        };
        bindNav(p, prev);
        bindNav(n, next);

        // Touch swipe
        let sx = 0; let dx = 0;
        g.addEventListener('touchstart', (e)=>{ sx = e.touches[0]?.clientX || 0; dx = 0; }, {passive:true});
        g.addEventListener('touchmove', (e)=>{ dx = (e.touches[0]?.clientX || 0) - sx; }, {passive:true});
        g.addEventListener('touchend', ()=>{
          if (Math.abs(dx) > 40){ dx < 0 ? next() : prev(); }
          sx = 0; dx = 0;
        });

        render();
      });
    }

    async function loadPosts(){
      if (loading) return;
      loading = true;
      if (loadMoreBtn) loadMoreBtn.disabled = true;
      try{
        const r = await postForm('ia_connect_post_list', {
          nonce: nonces.post_list||'',
          wall_wp: wallWp,
          wall_phpbb: wallPhpbb,
          before_id: oldestId||0,
          limit: 10
        });
        if (!r || !r.success) throw r;
        const posts = r.data.posts || [];
        if (posts.length){
          const html = posts.map(renderCard).join('');
          feedInner.insertAdjacentHTML('beforeend', html);
          hydrateGalleries(feedInner);
          // track oldest
          posts.forEach(p=>{ if (p && p.id) oldestId = oldestId ? Math.min(oldestId, p.id) : p.id; });
        } else {
          if (loadMoreBtn) loadMoreBtn.textContent = 'No more';
        }
      } catch(e){
        // silent
      } finally {
        loading = false;
        if (loadMoreBtn) loadMoreBtn.disabled = false;
      }
    }

    if (loadMoreBtn) loadMoreBtn.addEventListener('click', ()=>loadPosts());
    // initial load
    loadPosts();

    // Deep link to a post
    try{
      const u = new URL(location.href);
      const pid = parseInt(u.searchParams.get('ia_post')||'0',10)||0;
      if (pid > 0) openPostModal(pid, false);
    }catch(_){ }

    // Composer
    const postBtn = qs(root, '[data-iac-post]');
    const titleEl = qs(root, '[data-iac-title]');
    const bodyEl  = qs(root, '[data-iac-body]');
    const filesEl = qs(root, '[data-iac-files]');
    const metaEl  = qs(root, '[data-iac-files-meta]');
    const prevEl  = qs(root, '[data-iac-preview]');
    let picked = [];

    // Mentions on composer + modal comment box
    attachMention(bodyEl);
    attachMention(postCommentInput);

    // Enable Enter-to-send in fullscreen modal comment box.
    if (postCommentInput && postCommentSend){
      postCommentInput.addEventListener('keydown', (e)=>{
        if (e.key === 'Enter'){
          e.preventDefault();
          postCommentSend.click();
        }
      });
    }

    function refreshPreview(){
      if (!metaEl || !prevEl) return;
      metaEl.textContent = picked.length ? (picked.length + ' file' + (picked.length>1?'s':'') + ' selected') : '';
      prevEl.innerHTML = '';
      if (!picked.length){ prevEl.hidden = true; return; }
      prevEl.hidden = false;
      picked.slice(0,12).forEach(f=>{
        const box = document.createElement('div');
        box.className = 'iac-prev';
        const t = (f.type||'').toLowerCase();
        if (t.startsWith('image/')){
          const img = document.createElement('img');
          img.src = URL.createObjectURL(f);
          box.appendChild(img);
        } else if (t.startsWith('video/')){
          const v = document.createElement('video');
          v.src = URL.createObjectURL(f);
          v.muted = true;
          v.playsInline = true;
          box.appendChild(v);
        } else {
          box.textContent = (f.name||'file').slice(0,12);
        }
        prevEl.appendChild(box);
      });
    }

    if (filesEl){
      filesEl.addEventListener('change', ()=>{
        picked = filesEl.files ? Array.from(filesEl.files) : [];
        refreshPreview();
      });
    }

    if (postBtn){
      postBtn.addEventListener('click', async ()=>{
        const title = String(titleEl?.value||'').trim();
        const body = String(bodyEl?.value||'').trim();
        if (!title && !body && !picked.length) return;

        postBtn.disabled = true;
        try{
          const r = await postForm('ia_connect_post_create', {
            nonce: nonces.post_create||'',
            wall_wp: wallWp,
            wall_phpbb: wallPhpbb,
            title,
            body
          }, picked);
          if (!r || !r.success) throw r;
          const p = r.data.post;
          // prepend
          feedInner.insertAdjacentHTML('afterbegin', renderCard(p));
          hydrateGalleries(feedInner);

          // reset
          if (titleEl) titleEl.value = '';
          if (bodyEl) bodyEl.value = '';
          picked = [];
          if (filesEl) filesEl.value = '';
          refreshPreview();
        } catch(e){
          alert(e?.data?.message || 'Post failed');
        } finally {
          postBtn.disabled = false;
        }
      });
    }

    // Card actions: comment open, share, open
    feedInner.addEventListener('click', async (e)=>{
      const card = e.target.closest('.iac-card[data-post-id]');
      if (!card) return;
      const pid = parseInt(card.getAttribute('data-post-id')||'0',10)||0;

      if (e.target.closest('[data-iac-open]')){
        e.preventDefault();
        openPostModal(pid, true);
      }

      if (e.target.closest('[data-iac-comment-open]')){
        const comm = qs(card, '.iac-comments');
        if (comm) comm.hidden = !comm.hidden;
      }

      if (e.target.closest('[data-iac-share]')){
        e.preventDefault();
        openShareModal(pid);
      }

      if (e.target.closest('.iac-comment-send')){
        const input = qs(card, '.iac-comment-input');
        const txt = String(input?.value||'').trim();
        if (!txt) return;
        try{
          const r = await postForm('ia_connect_comment_create', {
            nonce: nonces.comment_create||'',
            post_id: pid,
            parent_comment_id: 0,
            body: txt
          });
          if (!r || !r.success) throw r;
          const c = r.data.comment;
          const box = qs(card, '.iac-comments-preview');
          if (box){
            box.insertAdjacentHTML('beforeend',
              '<div class="iac-comment">' +
                '<img class="iac-comment-ava" src="' + esc(c.author_avatar||BLANK_AVA) + '" alt="" />' +
                '<div class="iac-comment-bub"><div class="iac-comment-a">' + userLinkHtml(c.author||'User', c.author_phpbb_id, c.author_username, 'is-comment') + '</div>' +
                '<div class="iac-comment-t">' + linkMentions(c.body||'') + '</div></div></div>'
            );
          }
          if (input) input.value = '';
        } catch(err){
          alert(err?.data?.message || 'Comment failed');
        }
      }

      // If the user clicked the card (not a control/input/media), open the fullscreen post modal.
      if (!e.defaultPrevented &&
          !e.target.closest('button, a, input, textarea, select, [data-iac-view], .iac-mention, [data-iac-userlink]')){
        openPostModal(pid, true);
      }
    });

    feedInner.addEventListener('focusin', (e)=>{
      const el = e.target;
      if (el && el.classList && el.classList.contains('iac-comment-input')) {
        attachMention(el);
      }
    });

    // Clicking attachment media should open viewer
    feedInner.addEventListener('click', (e)=>{
      const el = e.target.closest('[data-iac-view]');
      if (!el) return;
      // Prevent opening viewer for cover/avatar handled elsewhere? this is inside feed.
      const src = el.getAttribute('data-iac-view');
      if (!src) return;
      e.preventDefault();
      openViewer(src);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();

// Account settings (Connect → Settings tab)
(function(){
  "use strict";
  function qs(sel){ return document.querySelector(sel); }
  function setStatus(el, msg, isErr){
    if (!el) return;
    el.textContent = String(msg||"");
    el.style.opacity = msg ? "1" : "0";
    el.style.color = isErr ? "var(--iac-red, #ff6b6b)" : "var(--iac-muted, #bdbdbd)";
  }
  async function post(action, data){
    const cfg = window.IA_CONNECT || {};
    const url = cfg.ajaxUrl || (window.ajaxurl || "/wp-admin/admin-ajax.php");
    const fd = new FormData();
    fd.append("action", action);
    Object.keys(data||{}).forEach(k=>fd.append(k, data[k]));
    const res = await fetch(url, { method:"POST", credentials:"same-origin", body:fd });
    const txt = await res.text();
    let json;
    try{ json = JSON.parse(txt); }catch(_){ throw new Error("Non-JSON response"); }
    if (!json || !json.success) throw new Error((json && json.data && json.data.message) ? json.data.message : "Request failed");
    return json.data || {};
  }

  function init(){
    const cfg = window.IA_CONNECT || {};
    const nonces = (cfg.nonces || {});

    const chkDeact = qs("[data-iac-acct-deactivate-confirm]");
    const btnDeact = qs("[data-iac-acct-deactivate]");
    if (chkDeact && btnDeact){
      chkDeact.addEventListener("change", ()=>{ btnDeact.disabled = !chkDeact.checked; });
    }

    const chkDel = qs("[data-iac-acct-delete-confirm]");
    const btnDel = qs("[data-iac-acct-delete]");
    if (chkDel && btnDel){
      chkDel.addEventListener("change", ()=>{ btnDel.disabled = !chkDel.checked; });
    }

    // Password reset is handled by IA Auth (ia_auth_forgot). Connect simply triggers the existing flow.
    const btnReset = qs("[data-iac-acct-reset]");
    if (btnReset){
      btnReset.addEventListener("click", async ()=>{
        const st = qs("[data-iac-acct-reset-status]");
        const me = (cfg.me || {});
        const identifier = (me.email || me.login || "");
        setStatus(st, "Sending reset email…", false);

        // IA Auth provides its own nonce.
        const authNonce = (window.IA_AUTH && window.IA_AUTH.nonce) ? String(window.IA_AUTH.nonce) : "";
        if (!authNonce){
          return setStatus(st, "IA Auth nonce not found on the page.", true);
        }
        if (!identifier){
          return setStatus(st, "Cannot determine your account email.", true);
        }

        try{
          // ia-auth ajax_forgot expects POST field "login".
          // It sets $_POST['user_login'] and calls retrieve_password().
          const r = await post("ia_auth_forgot", { nonce: authNonce, login: identifier, redirect_to: location.href });
          setStatus(st, (r && r.message) ? r.message : "If an account exists, a reset email has been sent.", false);
        }catch(e){
          // IA Auth returns generic success; if we got here, it was likely a nonce or transport issue.
          setStatus(st, e.message || "Request failed.", true);
        }
      });
    }

    const btnExport = qs("[data-iac-acct-export]");
    if (btnExport){
      btnExport.addEventListener("click", async ()=>{
        const st = qs("[data-iac-acct-export-status]");
        setStatus(st, "Generating export…", false);
        try{
          // Server action is ia_connect_export_data, nonce key is export_data.
          const r = await post("ia_connect_export_data", { nonce: nonces.export_data||"" });
          if (r && r.url){
            setStatus(st, "Export ready. Downloading…", false);
            window.location.href = r.url;
          } else {
            setStatus(st, "Export generated.", false);
          }
        }catch(e){
          setStatus(st, e.message || "Export failed.", true);
        }
      });
    }

    if (btnDeact){
      btnDeact.addEventListener("click", async ()=>{
        const st = qs("[data-iac-acct-deactivate-status]");
        setStatus(st, "Deactivating…", false);
        try{
          const r = await post("ia_connect_account_deactivate", { nonce: nonces.account_deactivate||"" });
          setStatus(st, r.message || "Deactivated.", false);
          // Force reload to show logged-out / auth modal state
          setTimeout(()=>{ try{ location.reload(); }catch(_){ } }, 800);
        }catch(e){
          setStatus(st, e.message || "Deactivation failed.", true);
        }
      });
    }

    if (btnDel){
      btnDel.addEventListener("click", async ()=>{
        const st = qs("[data-iac-acct-delete-status]");
        const pass = (qs("[data-iac-acct-delete-pass]")||{}).value || "";
        setStatus(st, "Deleting…", false);
        if (!pass) return setStatus(st, "Enter your current password.", true);
        try{
          const r = await post("ia_connect_account_delete", { nonce: nonces.account_delete||"", current_password: pass, delete_peertube: 0 });
          setStatus(st, r.message || "Deleted.", false);
          setTimeout(()=>{ try{ location.reload(); }catch(_){ } }, 800);
        }catch(e){
          setStatus(st, e.message || "Delete failed.", true);
        }
      });
    }
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
