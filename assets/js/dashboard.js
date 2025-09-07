(function($){
  'use strict';
  const root = document.documentElement;
  const body = document.body;
  const dashWrap = document.querySelector('[data-svntex2-dashboard]');
  if(!dashWrap) return;

  // Dark Mode Toggle
  const toggleBtn = document.getElementById('svntex2DarkToggle');
  const LS_KEY = 'svntex2.dark';
  function applyMode(mode){
    if(mode === 'dark'){ body.classList.add('dark'); toggleBtn && toggleBtn.setAttribute('aria-pressed','true'); }
    else { body.classList.remove('dark'); toggleBtn && toggleBtn.setAttribute('aria-pressed','false'); }
  }
  let stored = localStorage.getItem(LS_KEY);
  if(stored){ applyMode(stored); }
  if(toggleBtn){
    toggleBtn.addEventListener('click', ()=>{
      const dark = body.classList.toggle('dark');
      localStorage.setItem(LS_KEY, dark ? 'dark':'light');
      toggleBtn.setAttribute('aria-pressed', dark ? 'true':'false');
    });
  }

  // Wallet Refresh
  const refreshBtn = document.getElementById('svntex2WalletRefresh');
  const balanceWrap = document.querySelector('[data-wallet-balance]');
  const loadingEl = balanceWrap ? balanceWrap.querySelector('.loading-indicator') : null;
  function formatAmount(raw){ return raw; }
  async function fetchBalance(){
    if(!SVNTEX2Dash) return; // localized object
    try {
      loadingEl && (loadingEl.hidden = false);
      const res = await fetch(SVNTEX2Dash.rest_url, { headers: { 'X-WP-Nonce': SVNTEX2Dash.nonce } });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      if(data && typeof data.balance !== 'undefined'){
        const amountEl = balanceWrap.querySelector('[data-amount]');
        if(amountEl){ amountEl.textContent = formatAmount(data.balance_display || data.balance); }
      }
    }catch(e){ console.warn('Balance refresh failed', e); }
    finally { loadingEl && (loadingEl.hidden = true); }
  }
  if(refreshBtn){ refreshBtn.addEventListener('click', fetchBalance); }

  // Auto refresh once on load after slight delay
  setTimeout(fetchBalance, 400);

})(jQuery);
