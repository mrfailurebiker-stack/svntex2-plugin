<?php
// Quick deploy debug helper (temporary)
// Visit: /wp-content/plugins/svntex2-plugin/deploy-debug.php
if (!defined('ABSPATH')) {
    // If loaded directly, attempt to bootstrap minimal WP: try to include wp-load
    $wp_load = dirname(__FILE__, 4) . '/wp-load.php';
    if (file_exists($wp_load)) require $wp_load;
}
header('Content-Type: text/plain; charset=utf-8');
if (!function_exists('wp_get_current_user')) {
    echo "WP not available.\n";
    echo "Commit: d23567c\n"; // commit stamp from local
    echo "Time: 2025-09-09 14:34:01 +0530\n";
    exit;
}
$current_user = wp_get_current_user();
$wallet = function_exists('svntex2_wallet_get_balance') ? svntex2_wallet_get_balance($current_user->ID) : '(no wallet fn)';
$kyc = get_user_meta($current_user->ID,'kyc_status', true) ?: 'None';
$ref = get_user_meta($current_user->ID,'referral_count', true) ?: 0;
echo "Commit: d23567c\n";
echo "Commit time: 2025-09-09 14:34:01 +0530\n";
echo "User ID: " . intval($current_user->ID) . "\n";
echo "Display name: " . esc_html($current_user->display_name) . "\n";
echo "KYC status: " . esc_html($kyc) . "\n";
echo "Wallet raw: " . esc_html($wallet) . "\n";
echo "Referral count: " . esc_html($ref) . "\n";
echo "wc_get_orders: " . (function_exists('wc_get_orders') ? 'available' : 'missing') . "\n";
echo "wc_price: " . (function_exists('wc_price') ? 'available' : 'missing') . "\n";

// Note: This file is temporary for debugging deploys.
