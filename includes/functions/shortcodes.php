<?php
// Additional shortcodes placeholder (wallet history etc.)
if (!defined('ABSPATH')) exit;

// Wallet history shortcode (scaffold)
add_shortcode('svntex_wallet_history', function($atts){
    if(!is_user_logged_in()) return '<p>Please log in.</p>';
    global $wpdb; $table = $wpdb->prefix.'svntex_wallet_transactions';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY id DESC LIMIT 25", get_current_user_id()));
    if(!$rows) return '<p>No transactions yet.</p>';
    $out = '<table class="svntex2-wallet-history"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance</th></tr></thead><tbody>';
    foreach($rows as $r){
        $out .= '<tr><td>'.esc_html(mysql2date('Y-m-d H:i', $r->created_at)).'</td><td>'.esc_html($r->type).'</td><td>'.esc_html(number_format($r->amount,2)).'</td><td>'.esc_html(number_format($r->balance_after,2)).'</td></tr>';
    }
    $out .= '</tbody></table>';
    return $out;
});
