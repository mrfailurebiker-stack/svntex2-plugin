<?php
if (!defined('ABSPATH')) exit;

function svntex2_wallet_add_transaction($user_id, $type, $amount, $reference_id = null, $meta = []) {
    global $wpdb; $table = $wpdb->prefix.'svntex_wallet_transactions';
    $amount = (float)$amount;
    $current = svntex2_wallet_get_balance($user_id);
    $new = $current + $amount;
    $wpdb->insert($table, [
        'user_id' => $user_id,
        'type' => sanitize_key($type),
        'amount' => $amount,
        'balance_after' => $new,
        'reference_id' => $reference_id,
        'meta' => wp_json_encode($meta),
        'created_at' => current_time('mysql')
    ], ['%d','%s','%f','%f','%s','%s','%s']);
    return $new;
}

function svntex2_wallet_get_balance($user_id){
    global $wpdb; $table = $wpdb->prefix.'svntex_wallet_transactions';
    $row = $wpdb->get_row($wpdb->prepare("SELECT balance_after FROM $table WHERE user_id=%d ORDER BY id DESC LIMIT 1", $user_id));
    return $row ? (float)$row->balance_after : 0.0;
}

function svntex2_is_pb_eligible($user_id){
    // Placeholder: will implement referral + KYC + maintenance rules.
    return (bool) get_user_meta($user_id,'kyc_approved', true);
}

?>
