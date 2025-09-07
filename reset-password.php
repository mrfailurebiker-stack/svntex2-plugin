<?php
require 'customer_db.php';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $user = getUser($email);
    if (!$user) {
        $error = 'User not found.';
    } else {
        $otp = generateOTP();
        session_start();
        $_SESSION['otp'] = $otp;
        $success = 'OTP sent: ' . $otp; // Simulate OTP
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    session_start();
    if ($_POST['otp'] === ($_SESSION['otp'] ?? '')) {
        $user = getUser($_POST['email']);
        $user['password'] = hashPassword($_POST['new_password']);
        saveUser($user);
        $success = 'Password reset successful!';
    } else {
        $error = 'Invalid OTP.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="customer.css">
    <script src="customer.js"></script>
</head>
<body>
<div class="form-container">
    <h2>Reset Password</h2>
    <form method="post">
        <div class="error" id="reset-error"><?php echo $error; ?></div>
        <div style="color:green;"><?php echo $success; ?></div>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="otp" placeholder="Enter OTP">
        <input type="password" name="new_password" placeholder="New Password">
        <button type="submit" name="send_otp">Send OTP</button>
        <button type="submit" name="reset">Reset Password</button>
    </form>
</div>
</body>
</html>
