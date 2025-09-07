<?php
if (!defined('ABSPATH')) exit;
?>
<form id="svntex2RegForm" class="svntex2-registration" novalidate>
  <?php wp_nonce_field('svntex2_auth','svntex2_nonce'); ?>
  <div><label>Mobile* <input type="text" name="mobile" maxlength="15" required></label> <button type="button" id="svntex2SendOtp" class="btn-otp">Send OTP</button></div>
  <div><label>OTP* <input type="text" name="otp" maxlength="6" required></label></div>
  <div><label>Email* <input type="email" name="email" required></label></div>
  <div><label>Password* <input type="password" name="password" required minlength="8"></label></div>
  <div><label>Referral ID <input type="text" name="referral"></label></div>
  <div class="form-messages" aria-live="polite"></div>
  <button type="submit">Create Account</button>
</form>
