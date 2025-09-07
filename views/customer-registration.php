<?php
if (!defined('ABSPATH')) exit;
wp_enqueue_style('svntex2-style');
?>
<div class="svntex-auth-page">
  <form id="svntex2RegForm" class="svntex2-card" autocomplete="off">
    <h2 class="svntex2-title">Create Account</h2>
    <?php wp_nonce_field('svntex2_auth','svntex2_nonce'); ?>
    <div class="field inline-flex">
      <label style="flex:1">Mobile*<br><input type="text" name="mobile" maxlength="15" required></label>
      <button type="button" id="svntex2SendOtp" class="btn-secondary" style="align-self:flex-end;margin-left:.5rem;">Send OTP</button>
    </div>
    <div class="field"><label>OTP*<br><input type="text" name="otp" maxlength="6" required></label></div>
    <div class="field"><label>Email*<br><input type="email" name="email" required></label></div>
    <div class="field"><label>Password*<br><input type="password" name="password" required minlength="8"></label></div>
    <div class="field"><label>Referral ID<br><input type="text" name="referral"></label></div>
    <div class="form-messages" aria-live="polite"></div>
    <button type="submit" class="btn-primary">Register</button>
    <div class="links-row"><a href="<?php echo esc_url( site_url('/customer-login') ); ?>">Already have an account?</a></div>
  </form>
</div>
<script>
(function(){
  const f=document.getElementById('svntex2RegForm'); if(!f) return; const msg=f.querySelector('.form-messages'); const otpBtn=document.getElementById('svntex2SendOtp');
  function flash(c,t){ msg.className='form-messages '+c; msg.innerHTML=t; }
  otpBtn.addEventListener('click', async ()=>{ const mobile=f.querySelector('input[name=mobile]').value.trim(); if(mobile.length<10){flash('error','Enter valid mobile'); return;} otpBtn.disabled=true; otpBtn.textContent='Sending...';
    const fd=new FormData(); fd.append('action','svntex2_send_otp'); fd.append('nonce','<?php echo wp_create_nonce('svntex2_auth'); ?>'); fd.append('mobile',mobile);
    const r=await fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',{method:'POST',body:fd}); const j=await r.json();
    if(j.success) flash('success',j.data.message); else flash('error', j.data.message||'OTP failed');
    otpBtn.disabled=false; otpBtn.textContent='Send OTP'; });
  f.addEventListener('submit', async e=>{e.preventDefault(); flash('',''); const fd=new FormData(f); fd.append('action','svntex2_register'); fd.append('nonce','<?php echo wp_create_nonce('svntex2_auth'); ?>');
    const r=await fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',{method:'POST',body:fd}); const j=await r.json(); if(j.success){ flash('success','Registered! ID: '+j.data.customer_id); f.reset(); }
    else { const errs=(j.data.errors||[]).join('<br>')||'Failed'; flash('error',errs); }
  });
})();
</script>
