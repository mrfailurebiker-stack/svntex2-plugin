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

  // Wallet Top-up form
  const topupForm = document.querySelector('[data-topup-form]');
  if(topupForm){
    const msgEl = topupForm.querySelector('[data-topup-msg]');
    topupForm.addEventListener('submit', async (e)=>{
      e.preventDefault();
      if(!SVNTEX2Dash){ return; }
      const amtInput = topupForm.querySelector('input[name=topup_amount]');
      const raw = parseFloat(amtInput.value || '0');
      if(!raw || raw <=0){ msgEl.textContent = 'Enter amount'; return; }
      msgEl.textContent = 'Processing...';
      try {
        const res = await fetch(SVNTEX2Dash.rest_url.replace(/wallet\/balance$/,'wallet/topup'), {
          method:'POST',
          headers:{ 'X-WP-Nonce': SVNTEX2Dash.nonce, 'Content-Type':'application/json' },
          body: JSON.stringify({ amount: raw })
        });
        const data = await res.json();
        if(!res.ok || data.error){ msgEl.textContent = data.error || 'Failed'; return; }
        msgEl.textContent = 'Top-up successful';
        amtInput.value='';
        fetchBalance();
      } catch(err){ msgEl.textContent = 'Error'; }
    });
  }

  // PB META wiring
  async function fetchPBMeta(){
    if(!SVNTEX2Dash || !SVNTEX2Dash.pb_meta_url) return;
    const statusEl = document.querySelector('[data-pb-status]');
    if(!statusEl) return;
    try {
      statusEl.textContent = '…';
      const res = await fetch(SVNTEX2Dash.pb_meta_url, { headers: { 'X-WP-Nonce': SVNTEX2Dash.nonce } });
      if(!res.ok) throw new Error('HTTP '+res.status);
      const data = await res.json();
      statusEl.textContent = data.status || 'inactive';
  // status badge classes
  statusEl.className = ''; // reset
  if(data.status){ statusEl.classList.add('is-'+data.status); }
      const ci = document.querySelector('[data-pb-cycle-index]');
      const cs = document.querySelector('[data-pb-cycle-start]');
      const cr = document.querySelector('[data-pb-cycle-refs]');
      const act = document.querySelector('[data-pb-activation]');
      const incl = document.querySelector('[data-pb-inclusion]');
      const spendEl = document.querySelector('[data-pb-spend]');
      const slabEl = document.querySelector('[data-pb-slab]');
      const nextThrEl = document.querySelector('[data-pb-next-threshold]');
      const suspWrap = document.querySelector('[data-pb-suspense-wrap]');
      const suspTotal = document.querySelector('[data-pb-suspense-total]');
      const suspCount = document.querySelector('[data-pb-suspense-count]');
      const lastSync = document.querySelector('[data-pb-last-sync]');
      const prog = document.querySelector('[data-pb-progress]');
      const progHint = document.querySelector('[data-pb-progress-hint]');
      if(ci) ci.textContent = data.cycle && data.cycle.month_index ? data.cycle.month_index : '—';
      if(cs) cs.textContent = data.cycle && data.cycle.start ? '('+data.cycle.start+')' : '';
      if(cr) cr.textContent = data.cycle && typeof data.cycle.referrals_in_cycle!=='undefined' ? data.cycle.referrals_in_cycle : '0';
      if(act) act.textContent = data.cycle && data.cycle.activation_month ? data.cycle.activation_month : '—';
      if(incl) incl.textContent = data.cycle && data.cycle.inclusion_start_month ? data.cycle.inclusion_start_month : '—';
      if(spendEl) spendEl.textContent = (data.spend && typeof data.spend.current_month !== 'undefined') ? Number(data.spend.current_month).toFixed(2) : '0.00';
      if(slabEl) slabEl.textContent = data.spend && data.spend.slab_percent ? (data.spend.slab_percent*100).toFixed(0)+'%' : '0%';
      if(nextThrEl){
        if(data.spend && data.spend.next_threshold){
          const cur = data.spend.current_month || 0;
            nextThrEl.textContent = data.spend.next_threshold + ' (+'+(data.spend.next_threshold - cur)+')';
        } else { nextThrEl.textContent = 'Max'; }
      }
      if(data.suspense){
        if(data.suspense.held_count>0){
          suspWrap && (suspWrap.hidden = false);
          suspTotal && (suspTotal.textContent = data.suspense.held_total.toFixed(2));
          suspCount && (suspCount.textContent = data.suspense.held_count);
        }
      }
      if(lastSync){ lastSync.textContent = 'Updated '+ new Date().toLocaleTimeString(); }
      // Progress bar: referrals toward 6
      if(prog && data.cycle){
        const refs = data.cycle.referrals_in_cycle || 0;
        const pct = Math.min(100, (refs/6)*100);
        prog.style.width = pct+'%';
        if(progHint){
          if(refs < 2) progHint.textContent = (2-refs)+' more to Activate';
          else if(refs < 6) progHint.textContent = (6-refs)+' more to become Active';
          else progHint.textContent = 'Active – maintain & renew';
        }
      }
    }catch(e){ console.warn('PB meta failed', e); }
  }
  setTimeout(fetchPBMeta, 600);
  // Refresh PB meta every 60s
  setInterval(fetchPBMeta, 60000);

})(jQuery);
