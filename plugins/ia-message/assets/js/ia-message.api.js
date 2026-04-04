(function(){
  function post(action, payload){
    const fd = new FormData();
    fd.append('action', action);
    for (const k in payload) fd.append(k, payload[k]);
    return fetch(IA_MESSAGE.ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' }).then(res => res.json());
  }

  function postFile(action, file, extraPayload){
    const fd = new FormData();
    fd.append('action', action);
    fd.append('nonce', IA_MESSAGE.nonceBoot);
    fd.append('file', file);
    if (extraPayload) {
      for (const k in extraPayload) fd.append(k, extraPayload[k]);
    }
    return fetch(IA_MESSAGE.ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' }).then(res => res.json());
  }

  function postFileProgress(action, file, extraPayload, onProgress){
    return new Promise((resolve, reject) => {
      try {
        const fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', IA_MESSAGE.nonceBoot);
        fd.append('file', file);
        if (extraPayload) {
          for (const k in extraPayload) fd.append(k, extraPayload[k]);
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', IA_MESSAGE.ajaxUrl, true);
        xhr.withCredentials = true;

        xhr.upload.addEventListener('progress', (ev) => {
          if (!onProgress) return;
          if (ev && ev.lengthComputable) {
            const pct = Math.max(0, Math.min(100, Math.round((ev.loaded / ev.total) * 100)));
            try { onProgress(pct, ev.loaded, ev.total); } catch(_) {}
          } else {
            try { onProgress(null, ev.loaded || 0, ev.total || 0); } catch(_) {}
          }
        });

        xhr.onreadystatechange = () => {
          if (xhr.readyState !== 4) return;
          if (xhr.status >= 200 && xhr.status < 300) {
            try { resolve(JSON.parse(xhr.responseText || '{}')); }
            catch(e){ reject(e); }
          } else {
            reject(new Error('Upload failed (' + xhr.status + ')'));
          }
        };

        xhr.onerror = () => reject(new Error('Upload failed'));
        xhr.send(fd);
      } catch (e) { reject(e); }
    });
  }

  window.IAMessageApi = window.IAMessageApi || {};
  window.IAMessageApi.post = post;
  window.IAMessageApi.postFile = postFile;
  window.IAMessageApi.postFileProgress = postFileProgress;
})();
