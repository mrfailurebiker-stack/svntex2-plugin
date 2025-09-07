// customer.js - Form validation and interactivity
function showError(id, msg) {
    document.getElementById(id).textContent = msg;
}
function clearError(id) {
    document.getElementById(id).textContent = '';
}
function validateEmail(email) {
    return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email);
}
function validateMobile(mobile) {
    return /^\d{10}$/.test(mobile);
}
function validatePassword(pw) {
    return pw.length >= 6;
}
// Add more JS as needed for OTP, AJAX, etc.
