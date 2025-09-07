<?php
if (!defined('ABSPATH')) exit;
wp_enqueue_style('svntex2-style');
?>
<div class="svntex-auth-page">
  <form id="svntex2LoginForm" class="svntex2-card" method="post" autocomplete="off">
    <h2 class="svntex2-title">Account Login</h2>
    <?php wp_nonce_field('svntex2_login','svntex2_login_nonce'); ?>
    <div class="field"><label>Customer ID / Email<br><input type="text" name="login_id" required></label></div>
    <div class="field"><label>Password<br><input type="password" name="password" required></label></div>
    <div class="form-messages" aria-live="polite"></div>
    <button type="submit" class="btn-primary">Login</button>
    <div class="links-row"><a href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgot password?</a> | <a href="<?php echo esc_url( site_url('/customer-register') ); ?>">Create account</a></div>
  </form>
</div>
<script>
(function(){
  const f=document.getElementById('svntex2LoginForm'); if(!f) return; const msg=f.querySelector('.form-messages');
  function flash(c,t){ msg.className='form-messages '+c; msg.innerHTML=t; }
  f.addEventListener('submit',async e=>{e.preventDefault(); flash('','');
    const fd=new FormData(f); fd.append('action','svntex2_do_login');
    const r=await fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',{method:'POST',body:fd});
    const j=await r.json(); if(j.success){ flash('success','Login successful, redirecting...'); location.href=j.data.redirect; }
    else { flash('error', (j.data && j.data.message) || 'Login failed'); }
  });
})();
</script>
