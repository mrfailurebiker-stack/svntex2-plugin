<?php
require 'customer_db.php';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $user = getUser($email);
    if (!$user) {
        $error = 'User not found.';
    } elseif (!verifyPassword($password, $user['password'])) {
        $error = 'Incorrect password.';
    } else {
        $success = 'Login successful! Welcome, Customer ID: ' . $user['customerId'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="customer.css">
    <script src="customer.js"></script>
</head>
<body>
<div class="form-container">
    <h2>Customer Login</h2>
    <form method="post">
        <div class="error" id="login-error"><?php echo $error; ?></div>
        <div style="color:green;"><?php echo $success; ?></div>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
</div>
</body>
</html>
