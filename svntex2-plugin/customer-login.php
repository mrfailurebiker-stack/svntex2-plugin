<?php
// SVNTeX 2.0 Customer Login System
// Security: input validation, password_verify, prepared statements, session management
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
	require_once 'db_connect.php'; // Add your DB connection here
	$response = ["success" => false, "errors" => []];

	// Sanitize inputs
	$login = trim($_POST['login'] ?? '');
	$password = $_POST['password'] ?? '';

	if (!$login) $response['errors'][] = 'Customer ID, email, or mobile required.';
	if (!$password) $response['errors'][] = 'Password required.';

	if ($response['errors']) { echo json_encode($response); exit; }

	// Find user by customer_id, email, or mobile
	$stmt = $pdo->prepare('SELECT * FROM svntex_customers WHERE customer_id = ? OR email = ? OR mobile = ?');
	$stmt->execute([$login, $login, $login]);
	$user = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$user) {
		$response['errors'][] = 'User not found.';
		echo json_encode($response); exit;
	}

	// Account lockout check
	if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
		$response['errors'][] = 'Account locked. Try again later.';
		echo json_encode($response); exit;
	}

	// Password check
	if (!password_verify($password, $user['password_hash'])) {
		// Increment failed attempts
		$stmt = $pdo->prepare('UPDATE svntex_customers SET failed_attempts = failed_attempts + 1 WHERE id = ?');
		$stmt->execute([$user['id']]);
		if ($user['failed_attempts'] + 1 >= 5) {
			$lockTime = date('Y-m-d H:i:s', strtotime('+15 minutes'));
			$stmt = $pdo->prepare('UPDATE svntex_customers SET locked_until = ? WHERE id = ?');
			$stmt->execute([$lockTime, $user['id']]);
			$response['errors'][] = 'Account locked due to multiple failed attempts.';
		} else {
			$response['errors'][] = 'Invalid password.';
		}
		echo json_encode($response); exit;
	}

	// Reset failed attempts
	$stmt = $pdo->prepare('UPDATE svntex_customers SET failed_attempts = 0, locked_until = NULL WHERE id = ?');
	$stmt->execute([$user['id']]);

	// Optional: 2FA check placeholder
	// ...

	// Set session
	$_SESSION['customer_id'] = $user['customer_id'];
	$_SESSION['customer_email'] = $user['email'];

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
