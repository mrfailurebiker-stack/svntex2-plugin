<?php
if (!defined('ABSPATH')) exit;
if ( is_user_logged_in() ) { echo '<p>You are already logged in.</p>'; return; }
?>
<form id="svntex2LoginForm" class="svntex2-login" method="post">
  <?php wp_nonce_field('svntex2_login','svntex2_login_nonce'); ?>
  <div><label>Customer ID / Email <input type="text" name="login_id" required></label></div>
  <div><label>Password <input type="password" name="password" required></label></div>
  <div class="form-messages" aria-live="polite"></div>
  <button type="submit">Login</button>
</form>
