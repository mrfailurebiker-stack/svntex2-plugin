
<?php
// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

function svntex2_customer_login_form() {
	ob_start();
	?>
	<div class="login-container">
		<form id="loginForm" autocomplete="off">
			<h2>Login</h2>
			<div class="form-group">
				<label>Email or Mobile</label>
				<input type="text" name="login" id="login" required>
			</div>
			<div class="form-group">
				<label>Password</label>
				<input type="password" name="password" id="password" required>
			</div>
			<button type="submit" class="submit-btn">Login</button>
			<div id="loginErrors" class="form-errors"></div>
		</form>
	</div>
	<script src="<?php echo plugin_dir_url(__FILE__); ?>customer-auth.js"></script>
	<?php
	return ob_get_clean();
}
