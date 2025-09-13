<?php
if (!defined('ABSPATH')) exit;

/** Generate a customer id of the form SVNXXXXXX */
function svntex2_generate_customer_id(){
    return 'SVN' . str_pad((string) wp_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function svntex2_wallet_add_transaction($user_id, $type, $amount, $reference_id = null, $meta = [], $category = 'general') {
    global $wpdb; $table = $wpdb->prefix.'svntex_wallet_transactions';
    $amount = (float)$amount;
    $current = svntex2_wallet_get_balance($user_id);
    $new = $current + $amount;
    $wpdb->insert($table, [
        'user_id' => $user_id,
        'type' => sanitize_key($type),
        'category' => sanitize_key($category),
        'amount' => $amount,
        'balance_after' => $new,
        'reference_id' => $reference_id,
        'meta' => wp_json_encode($meta),
        'created_at' => current_time('mysql')
    ], ['%d','%s','%s','%f','%f','%s','%s','%s']);
    // Fire action for observers (e.g., referral bonus on first top-up)
    do_action('svntex2_wallet_transaction_created', $user_id, $type, $amount, $reference_id, $meta, $new);
    return $new;
}

function svntex2_wallet_get_balance($user_id){
    global $wpdb; $table = $wpdb->prefix.'svntex_wallet_transactions';
    $row = $wpdb->get_row($wpdb->prepare("SELECT balance_after FROM $table WHERE user_id=%d ORDER BY id DESC LIMIT 1", $user_id));
    return $row ? (float)$row->balance_after : 0.0;
}

/** Get balance for a single category (reconstruct by summing) */
function svntex2_wallet_get_category_balance($user_id, $category){
    global $wpdb; $table = $wpdb->prefix.'svntex_wallet_transactions';
    $sum = $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $table WHERE user_id=%d AND category=%s", $user_id, $category));
    return (float)$sum;
}

/** Get structured balances (income, topup, withdraw, general) */
function svntex2_wallet_get_balances($user_id){
    return [
        'income'   => svntex2_wallet_get_category_balance($user_id,'income'),
        'topup'    => svntex2_wallet_get_category_balance($user_id,'topup'),
        'withdraw' => svntex2_wallet_get_category_balance($user_id,'withdraw'),
        'general'  => svntex2_wallet_get_category_balance($user_id,'general'),
        'total'    => svntex2_wallet_get_balance($user_id)
    ];
}

function svntex2_is_pb_eligible($user_id){
    // Placeholder: will implement referral + KYC + maintenance rules.
    return (bool) get_user_meta($user_id,'kyc_approved', true);
}

?>
