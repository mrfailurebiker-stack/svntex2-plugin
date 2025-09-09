<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="svntex-brand-bar">
  <a class="brand" href="<?php echo esc_url( home_url('/') ); ?>">SVNTeX</a>
  <div class="svntex-brand-links">
    <a href="<?php echo esc_url( site_url('/'.SVNTEX2_LOGIN_SLUG.'/') ); ?>">Log In</a>
    <a class="btn-brand sm" href="<?php echo esc_url( site_url('/'.SVNTEX2_REGISTER_SLUG.'/') ); ?>">Register</a>
  </div>
</div>
<main class="svntex-auth-shell fade-in">
  <section class="svntex-auth-card" role="form" aria-labelledby="svntexRegTitle">
    <h1 id="svntexRegTitle" class="svntex-auth-title">Get Started</h1>
    <p class="svntex-auth-sub">Create your account to access the SVNTeX platform</p>
    <form id="svntex2RegForm" class="svntex-auth-form" novalidate autocomplete="off">
      <?php wp_nonce_field('svntex2_auth','svntex2_nonce'); ?>
      <div class="field inline-split">
        <div style="flex:1">
          <label for="reg_fname">First name *</label>
          <input type="text" id="reg_fname" name="first_name" placeholder="First name" required />
        </div>
        <div style="flex:1">
          <label for="reg_lname">Last name *</label>
          <input type="text" id="reg_lname" name="last_name" placeholder="Last name" required />
        </div>
      </div>
      <div class="field inline-split">
        <div style="flex:1">
          <label for="reg_age">Age *</label>
          <input type="number" id="reg_age" name="age" placeholder="Age" min="1" max="120" required />
        </div>
        <div style="flex:1">
          <label for="reg_gender">Gender *</label>
          <select id="reg_gender" name="gender" required>
            <option value="">Select…</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="other">Other</option>
            <option value="prefer_not_to_say">Prefer not to say</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="reg_mobile">Mobile *</label>
        <input type="text" id="reg_mobile" name="mobile" placeholder="10 digit mobile" maxlength="15" required />
      </div>
      <div class="field inline-split">
        <div style="flex:1">
          <label for="reg_ref">Referral ID (optional)</label>
          <input type="text" id="reg_ref" name="referral" placeholder="Referrer ID if any" />
        </div>
        <div style="flex:1">
          <label for="reg_emp">Employee ID (optional)</label>
          <input type="text" id="reg_emp" name="employee_id" placeholder="Employee ID" />
        </div>
      </div>
      <div class="field inline-split">
        <div style="flex:1">
          <label for="reg_pass">Password *</label>
          <input type="password" id="reg_pass" name="password" placeholder="Min 4 characters" minlength="4" required />
        </div>
        <div style="flex:1">
          <label for="reg_confirm">Confirm password *</label>
          <input type="password" id="reg_confirm" name="confirm" placeholder="Repeat password" minlength="4" required />
        </div>
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
  const form=document.getElementById('svntex2RegForm'); if(!form) return; const msg=form.querySelector('.form-messages');
  const flash=(c,t)=>{ msg.className='form-messages '+c; msg.innerHTML=t; };
  form.addEventListener('submit', async e => {
    e.preventDefault();
    if(!document.getElementById('policy_accept').checked){ flash('error','You must agree to the Privacy Policy'); return; }
    if(form.password.value !== form.confirm.value){ flash('error','Passwords do not match'); return; }
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
