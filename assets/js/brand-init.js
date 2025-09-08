// SVNTeX brand initialization (future: theme toggle, animations, telemetry opt-in)
(function(){
  try {
    const body = document.body;
    // Simple prefers-color-scheme sync (optional enhancement placeholder)
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      body.classList.add('dark');
    }
    // Hook for dynamic enhancements
    document.dispatchEvent(new CustomEvent('svntex:brand:init', { detail: window.SVNTEX2_BRAND || {} }));
  } catch(e){ /* silent */ }
})();
