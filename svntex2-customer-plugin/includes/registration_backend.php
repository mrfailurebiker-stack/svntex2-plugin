<?php
// SVNTeX 2.0 Registration/Login Backend Functions

// Secure password hashing
function svntex2_hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Password verification
function svntex2_verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Generate unique Customer ID
function svntex2_generate_customer_id() {
    return 'SVN-' . rand(100000, 999999);
}

// OTP generation (simulate SMS sending)
function svntex2_generate_otp() {
    return rand(100000, 999999);
}
function svntex2_send_otp($mobile, $otp) {
    // Simulate SMS sending (log or display)
    error_log("OTP for $mobile: $otp");
    return true;
}

// Validate and sanitize input
function svntex2_sanitize($data) {
    return htmlspecialchars(trim($data));
}

// Store customer data in custom table
function svntex2_store_customer($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'svntex_customers';
    $wpdb->insert($table, $data);
    return $wpdb->insert_id;
}

// Retrieve customer by login (ID, email, or mobile)
function svntex2_get_customer($login_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'svntex_customers';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE customer_id=%s OR email=%s OR mobile=%s",
        $login_id, $login_id, $login_id
    ), ARRAY_A);
}

// Test script: Simulate registration
function svntex2_test_registration() {
    $customer_id = svntex2_generate_customer_id();
    $password = 'Test@1234';
    $hash = svntex2_hash_password($password);
    $otp = svntex2_generate_otp();
    svntex2_send_otp('9999999999', $otp);
    $data = [
        'customer_id' => $customer_id,
        'mobile' => '9999999999',
        'email' => 'test@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'password' => $hash,
        'created_at' => current_time('mysql')
    ];
    $id = svntex2_store_customer($data);
    return $id ? 'Registration successful' : 'Registration failed';
}

// Test script: Simulate login
function svntex2_test_login() {
    $customer = svntex2_get_customer('test@example.com');
    if (!$customer) return 'Customer not found';
    $valid = svntex2_verify_password('Test@1234', $customer['password']);
    return $valid ? 'Login successful' : 'Login failed';
}

?>
