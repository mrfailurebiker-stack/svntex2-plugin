<?php
/** Withdrawals handling (scaffold) */
if (!defined('ABSPATH')) exit;

function svntex2_withdraw_request($user_id, $amount, $method = 'bank', $destination = null){
    $amount = (float)$amount; if ($amount <= 0) return new WP_Error('amount','Invalid amount');
    $balance = svntex2_wallet_get_balance($user_id);
    if ($amount > $balance) return new WP_Error('funds','Insufficient balance');
    // Ledger holds funds until processed: create negative pending entry
    $new_balance = svntex2_wallet_add_transaction($user_id,'withdraw_hold', -$amount, null, []);
    global $wpdb; $table = $wpdb->prefix.'svntex_withdrawals';
    $wpdb->insert($table, [
        'user_id' => $user_id,
        'amount'  => $amount,
        'status'  => 'requested',
        'method'  => $method,
        'destination' => $destination,
        'requested_at' => current_time('mysql')
    ], ['%d','%f','%s','%s','%s','%s']);
    return [ 'withdrawal_id' => $wpdb->insert_id, 'balance' => $new_balance ];
}

function svntex2_withdraw_process($withdrawal_id, $status, $admin_note = null){
    global $wpdb; $table = $wpdb->prefix.'svntex_withdrawals';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $withdrawal_id));
    if(!$row) return false;
    if(!in_array($status, ['approved','rejected'], true)) return false;
    // If rejected, refund hold
    if ($status === 'rejected') {
        svntex2_wallet_add_transaction($row->user_id,'withdraw_refund', (float)$row->amount, 'withdraw:'.$withdrawal_id, []);
    } else { // approved finalization
        svntex2_wallet_add_transaction($row->user_id,'withdraw_complete', 0, 'withdraw:'.$withdrawal_id, []); // marker
    }
    $wpdb->update($table, [
        'status' => $status,
        'admin_note' => $admin_note,
        'processed_at' => current_time('mysql')
    ], [ 'id' => $withdrawal_id ]);
    return true;
}

?>
