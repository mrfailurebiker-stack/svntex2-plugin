<?php
// SVNTeX 2.0 Customer Registration System
// Security: CSRF, input validation, password_hash, prepared statements, session management
session_start();
header('Content-Type: application/json');

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle AJAX registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    require_once 'db_connect.php'; // Add your DB connection here
    $response = ["success" => false, "errors" => []];

    // Sanitize inputs
    $mobile = preg_replace('/\D/', '', $_POST['mobile'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $fname = htmlspecialchars(trim($_POST['fname'] ?? ''));
    $lname = htmlspecialchars(trim($_POST['lname'] ?? ''));
    $referral = htmlspecialchars(trim($_POST['referral'] ?? ''));
    $employee = htmlspecialchars(trim($_POST['employee'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $otp = $_POST['otp'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    // CSRF check
    if ($csrf !== $_SESSION['csrf_token']) {
        $response['errors'][] = 'Invalid CSRF token.';
        echo json_encode($response); exit;
    }

    // Validate required fields
    if (!$mobile || strlen($mobile) < 10) $response['errors'][] = 'Valid mobile required.';
    if (!$email) $response['errors'][] = 'Valid email required.';
    if (!$fname) $response['errors'][] = 'First name required.';
    if (!$lname) $response['errors'][] = 'Last name required.';
    if (strlen($password) < 8) $response['errors'][] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $response['errors'][] = 'Passwords do not match.';

    // OTP verification (simulate for demo)
    if ($otp !== ($_SESSION['otp_code'] ?? '123456')) {
        $response['errors'][] = 'Invalid OTP.';
    }

    // Check for duplicates
    $stmt = $pdo->prepare('SELECT id FROM svntex_customers WHERE email = ? OR mobile = ?');
    $stmt->execute([$email, $mobile]);
    if ($stmt->fetch()) $response['errors'][] = 'Email or mobile already registered.';

    if ($response['errors']) { echo json_encode($response); exit; }

    // Generate unique Customer ID
    do {
        $customer_id = 'SVN-' . str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT id FROM svntex_customers WHERE customer_id = ?');
        $stmt->execute([$customer_id]);
    } while ($stmt->fetch());

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insert customer
    $stmt = $pdo->prepare('INSERT INTO svntex_customers (customer_id, email, mobile, password_hash, referral_id, employee_id, otp_verified, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())');
    $stmt->execute([$customer_id, $email, $mobile, $password_hash, $referral, $employee]);

    // Log registration
    error_log("Customer registered: $customer_id, $email, $mobile");

    // Optional: 2FA setup placeholder
    // ...

    $response['success'] = true;
    $response['customer_id'] = $customer_id;
    echo json_encode($response); exit;
}

// HTML Registration Form (for direct access)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVNTeX Customer Registration</title>
    <link rel="stylesheet" href="customer-ui.css">
</head>
<body>
<div class="registration-container">
    <form id="registrationForm" autocomplete="off">
        <h2>Register</h2>
        <div class="form-group">
            <label>Mobile Number*</label>
            <input type="text" name="mobile" id="mobile" maxlength="15" required>
            <button type="button" id="sendOtpBtn">Send OTP</button>
            <input type="text" name="otp" id="otp" maxlength="6" placeholder="Enter OTP" required>
        </div>
        <div class="form-group">
            <label>Email*</label>
            <input type="email" name="email" id="email" required>
        </div>
        <div class="form-group">
            <label>First Name*</label>
            <input type="text" name="fname" id="fname" required>
        </div>
        <div class="form-group">
            <label>Last Name*</label>
            <input type="text" name="lname" id="lname" required>
        </div>
        <div class="form-group">
            <label>Referral ID (optional)</label>
            <input type="text" name="referral" id="referral">
        </div>
        <div class="form-group">
            <label>Employee ID (optional)</label>
            <input type="text" name="employee" id="employee">
        </div>
        <div class="form-group">
            <label>Password*</label>
            <input type="password" name="password" id="password" required>
        </div>
        <div class="form-group">
            <label>Confirm Password*</label>
            <input type="password" name="confirm" id="confirm" required>
        </div>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <button type="submit" class="submit-btn">Register</button>
        <div id="formErrors" class="form-errors"></div>
    </form>
</div>
<script src="customer-auth.js"></script>
</body>
</html>
