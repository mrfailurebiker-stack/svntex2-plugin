<?php if ( ! defined( 'ABSPATH' ) ) exit; wp_enqueue_style('svntex2-style'); ?>
<div class="svntex-brand-bar">
  <a class="brand" href="<?php echo esc_url( home_url('/') ); ?>">SVNTeX</a>
  <div class="svntex-brand-links">
    <a href="<?php echo esc_url( site_url('/'.SVNTEX2_REGISTER_SLUG.'/') ); ?>">Sign Up</a>
    <a class="btn-brand sm" href="<?php echo esc_url( site_url('/'.SVNTEX2_LOGIN_SLUG.'/') ); ?>">Login</a>
  </div>
</div>
<main class="svntex-auth-shell fade-in">
  <section class="svntex-auth-card" role="form" aria-labelledby="svntexLoginTitle">
    <h1 id="svntexLoginTitle" class="svntex-auth-title">Welcome Back</h1>
  <p class="svntex-auth-sub">Sign in to continue to your dashboard</p>
    <form id="svntex2LoginForm" class="svntex-auth-form" method="post" novalidate autocomplete="on">
      <?php wp_nonce_field('svntex2_login','svntex2_login_nonce'); ?>
      <div class="field">
        <label for="login_id">Email / Username / Customer ID</label>
        <input type="text" id="login_id" name="login_id" placeholder="you@example.com" autocomplete="username" required />
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required />
      </div>
      <div class="svntex-remember-row">
        <label><input type="checkbox" name="remember" value="1" /> Remember Me</label>
        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgot password?</a>
      </div>
      <div class="svntex-auth-actions">
        <button type="submit" class="btn-accent">Sign In</button>
      </div>
      <div class="form-messages" aria-live="polite"></div>
      <div class="svntex-auth-foot">No account? <a href="<?php echo esc_url( site_url('/'.SVNTEX2_REGISTER_SLUG.'/') ); ?>">Create one</a></div>
    </form>
  </section>
</main>
<script>
(function(){
  const f=document.getElementById('svntex2LoginForm'); if(!f) return; const msg=f.querySelector('.form-messages');
  const flash=(c,t)=>{ msg.className='form-messages '+c; msg.textContent=t; };
  f.addEventListener('submit', async e => {
    e.preventDefault(); flash('', 'Signing you in...');
    const fd=new FormData(f); fd.append('action','svntex2_do_login');
    try {
      const r= await fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',{method:'POST',body:fd});
      const j= await r.json();
      if(j.success){ flash('success','Success! Redirecting...'); setTimeout(()=>location.href=j.data.redirect,350); }
      else { flash('error', (j.data && j.data.message) || 'Login failed'); }
    } catch(err){ flash('error','Network error – try again.'); }
  });
})();
</script>
