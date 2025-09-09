<?php
// SVNTeX Custom Logout Handler
if (!defined('ABSPATH')) {
    require_once(dirname(__FILE__,2) . '/wp-load.php');
}

// Only allow logged-in users
if (!is_user_logged_in()) {
    wp_safe_redirect(home_url('/customer-login/'));
    exit;
}

// Log out user and destroy session
wp_logout();
if (isset($_COOKIE[LOGGED_IN_COOKIE])) {
    setcookie(LOGGED_IN_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
}
if (isset($_COOKIE[AUTH_COOKIE])) {
    setcookie(AUTH_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
}
if (isset($_COOKIE[SECURE_AUTH_COOKIE])) {
    setcookie(SECURE_AUTH_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
}
if (isset($_COOKIE[USER_COOKIE])) {
    setcookie(USER_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
}
// Destroy PHP session
if (session_id()) {
    session_destroy();
}
// Redirect to login page
wp_safe_redirect(home_url('/customer-login/'));
exit;
