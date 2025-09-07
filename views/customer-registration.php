<?php if ( ! defined( 'ABSPATH' ) ) exit; wp_enqueue_style('svntex2-style'); ?>
<main class="svntex-auth-shell">
  <section class="svntex-auth-card" role="form" aria-labelledby="svntexRegTitle">
    <h1 id="svntexRegTitle" class="svntex-auth-title">Get Started</h1>
    <p class="svntex-auth-sub">Create your account to access the SVNTeX platform</p>
    <form id="svntex2RegForm" class="svntex-auth-form" novalidate autocomplete="off">
      <?php wp_nonce_field('svntex2_auth','svntex2_nonce'); ?>
      <div class="field inline-split">
        <div style="flex:1">
          <label for="reg_mobile">Mobile *</label>
          <input type="text" id="reg_mobile" name="mobile" placeholder="10 digit mobile" maxlength="15" required />
          <small class="helper">We'll send an OTP</small>
        </div>
        <div style="align-self:flex-end">
          <button type="button" id="svntex2SendOtp" class="btn-accent" style="padding:.85rem 1.1rem; font-size:.8rem; white-space:nowrap;">Send OTP</button>
        </div>
      </div>
      <div class="field">
        <label for="reg_otp">OTP *</label>
        <input type="text" id="reg_otp" name="otp" placeholder="Enter 6 digit code" maxlength="6" required />
      </div>
      <div class="field">
        <label for="reg_email">Email *</label>
        <input type="email" id="reg_email" name="email" placeholder="you@example.com" required />
      </div>
      <div class="field">
        <label for="reg_pass">Password *</label>
        <input type="password" id="reg_pass" name="password" placeholder="Min 8 characters" minlength="8" required />
      </div>
      <div class="field">
        <label for="reg_ref">Referral ID (optional)</label>
        <input type="text" id="reg_ref" name="referral" placeholder="Referrer ID if any" />
      </div>
      <div class="policy-row">
        <input type="checkbox" id="policy_accept" required />
        <label for="policy_accept">I agree to the <a href="/privacy-policy" target="_blank">Privacy Policy</a></label>
      </div>
      <div class="svntex-auth-actions">
        <button type="submit" class="btn-accent">Sign Up</button>
      </div>
      <div class="form-messages" aria-live="polite"></div>
      <div class="svntex-auth-foot">Have an account? <a href="<?php echo esc_url( site_url('/'.SVNTEX2_LOGIN_SLUG.'/') ); ?>">Sign in</a></div>
    </form>
  </section>
</main>
<script>
(function(){
  const form=document.getElementById('svntex2RegForm'); if(!form) return; const msg=form.querySelector('.form-messages'); const otpBtn=document.getElementById('svntex2SendOtp');
  const flash=(c,t)=>{ msg.className='form-messages '+c; msg.innerHTML=t; };
  otpBtn.addEventListener('click', async ()=>{
    const mobile=form.mobile.value.trim(); if(mobile.length < 10){ flash('error','Enter valid mobile number'); return; }
    otpBtn.disabled=true; const original=otpBtn.textContent; otpBtn.textContent='Sending...';
    try {
      const fd=new FormData(); fd.append('action','svntex2_send_otp'); fd.append('nonce','<?php echo wp_create_nonce('svntex2_auth'); ?>'); fd.append('mobile',mobile);
      const r=await fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',{method:'POST',body:fd}); const j=await r.json();
      if(j.success) flash('success', j.data.message); else flash('error', (j.data && j.data.message) || 'OTP failed');
    } catch(e){ flash('error','Network error sending OTP'); }
    otpBtn.disabled=false; otpBtn.textContent=original;
  });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    if(!document.getElementById('policy_accept').checked){ flash('error','You must agree to the Privacy Policy'); return; }
    flash('', 'Creating account...');
    const fd=new FormData(form); fd.append('action','svntex2_register'); fd.append('nonce','<?php echo wp_create_nonce('svntex2_auth'); ?>');
    try {
      const r=await fetch('<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',{method:'POST',body:fd}); const j=await r.json();
      if(j.success){ flash('success','Registered! Your ID: '+ j.data.customer_id + '<br>You may now log in.'); form.reset(); }
      else { const errs=(j.data && j.data.errors) ? j.data.errors.join('<br>') : (j.data && j.data.message) || 'Registration failed'; flash('error', errs); }
    } catch(e){ flash('error','Server error. Try again.'); }
  });
})();
</script>
