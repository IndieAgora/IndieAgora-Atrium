(function(){
  const memo = new WeakMap();

  function S(shell){
    if (!memo.has(shell)) memo.set(shell, {
      threads: [],
      activeId: 0,
      me: 0,
      sendBusy: false,
      userTimer: null,
      fwdTimer: null,
      fwdSelected: [],
      replyToId: 0,
      replyToMeta: null,
      msgLimit: 15,
      nextOffset: 0,
      hasMore: false,
      loadingMore: false,
      atBottom: true,
      justOpened: false,
      groupTimer: null,
      groupSelected: [],
      groupLastResults: [],
      groupAvatarUrl: '',
    });
    return memo.get(shell);
  }

  window.IAMessageState = window.IAMessageState || {};
  window.IAMessageState.memo = memo;
  window.IAMessageState.S = S;
})();
