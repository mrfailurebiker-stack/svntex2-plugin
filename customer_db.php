<?php
// customer_db.php - Handles user data, password hashing, OTP simulation
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
function generateCustomerId() {
    return 'CUST' . rand(10000, 99999);
}
function generateOTP() {
    return rand(100000, 999999);
}
function saveUser($user) {
    $users = json_decode(file_get_contents('customers.json'), true) ?: [];
    $users[$user['email']] = $user;
    file_put_contents('customers.json', json_encode($users));
}
function getUser($email) {
    $users = json_decode(file_get_contents('customers.json'), true) ?: [];
    return $users[$email] ?? null;
}
?>
