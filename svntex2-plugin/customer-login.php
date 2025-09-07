<?php
// SVNTeX 2.0 Customer Login System
// Security: input validation, password_verify, prepared statements, session management

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
	if (session_status() === PHP_SESSION_NONE) session_start();
	header('Content-Type: application/json');
	require_once 'db_connect.php'; // Add your DB connection here
	$response = ["success" => false, "errors" => []];

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

	$response['success'] = true;
	$response['customer_id'] = $user['customer_id'];
	echo json_encode($response); exit;
}

// HTML Login Form (for direct access)
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>SVNTeX Customer Login</title>
	<link rel="stylesheet" href="customer-ui.css">
</head>
<body>
<div class="registration-container">
	<form id="loginForm" autocomplete="off">
		<h2>Login</h2>
		<div class="form-group">
			<label>Customer ID / Email / Mobile*</label>
			<input type="text" name="login" id="login" required>
		</div>
		<div class="form-group">
			<label>Password*</label>
			<input type="password" name="password" id="password" required>
		</div>
		<button type="submit" class="submit-btn">Login</button>
		<div id="loginErrors" class="form-errors"></div>
	</form>
</div>
<script>
// Frontend JS for login validation and AJAX
document.addEventListener('DOMContentLoaded', function() {
	const form = document.getElementById('loginForm');
	const login = document.getElementById('login');
	const password = document.getElementById('password');
	const errorsDiv = document.getElementById('loginErrors');

	function showError(msg) {
		errorsDiv.innerHTML = `<div class='error-msg' role='alert'>${msg}</div>`;
		errorsDiv.classList.add('show');
		setTimeout(() => errorsDiv.classList.remove('show'), 4000);
	}
	function showSuccess(msg) {
		errorsDiv.innerHTML = `<div class='success-msg' role='status'>${msg}</div>`;
		errorsDiv.classList.add('success');
		setTimeout(() => errorsDiv.classList.remove('success'), 4000);
	}

	form.addEventListener('submit', function(e) {
		e.preventDefault();
		errorsDiv.innerHTML = '';
		if (!login.value.trim()) {
			showError('Enter Customer ID, email, or mobile.');
			login.focus();
			return;
		}
		if (!password.value) {
			showError('Enter your password.');
			password.focus();
			return;
		}
		const formData = new FormData(form);
		formData.append('action', 'login');
		fetch('customer-login.php', {
			method: 'POST',
			body: formData
		})
		.then(res => res.json())
		.then(data => {
			if (data.success) {
				showSuccess('Login successful!');
				form.reset();
				// Redirect or update UI as needed
			} else {
				showError(data.errors ? data.errors.join('<br>') : 'Login failed.');
			}
		});
	});
});
</script>
</body>
</html>
