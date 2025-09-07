<?php
require 'customer_db.php';
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $otp = $_POST['otp'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (!preg_match('/^\d{10}$/', $mobile)) {
        $error = 'Invalid mobile number.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($otp !== ($_SESSION['otp'] ?? '')) {
        $error = 'Invalid OTP.';
    } else {
        $customerId = generateCustomerId();
        $user = [
            'email' => $email,
            'mobile' => $mobile,
            'password' => hashPassword($password),
            'customerId' => $customerId
        ];
        saveUser($user);
        $success = 'Registration successful! Your Customer ID: ' . $customerId;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    session_start();
    $_SESSION['otp'] = generateOTP();
    $success = 'OTP sent: ' . $_SESSION['otp']; // Simulate OTP
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="customer.css">
    <script src="customer.js"></script>
</head>
<body>
<div class="form-container">
    <h2>Customer Registration</h2>
    <form method="post">
        <div class="error" id="reg-error"><?php echo $error; ?></div>
        <div style="color:green;"><?php echo $success; ?></div>
        <input type="email" name="email" placeholder="Email" required>
        <input type="text" name="mobile" placeholder="Mobile (10 digits)" required>
        <input type="password" name="password" placeholder="Password" required>
        <input type="text" name="otp" placeholder="Enter OTP" required>
        <button type="submit" name="register">Register</button>
        <button type="submit" name="send_otp">Send OTP</button>
    </form>
</div>
</body>
</html>
