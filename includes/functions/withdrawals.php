<?php
/** Withdrawals handling (scaffold) */
if (!defined('ABSPATH')) exit;

function svntex2_withdraw_request($user_id, $amount, $method = 'bank', $destination = null){
    $amount = (float)$amount; if ($amount <= 0) return new WP_Error('amount','Invalid amount');
    // Only income category is withdrawable
    $income_balance = svntex2_wallet_get_category_balance($user_id,'income');
    if ($amount > $income_balance) return new WP_Error('funds','Insufficient withdrawable (income) balance');
    // Ledger holds funds until processed: create negative pending entry
    $new_balance = svntex2_wallet_add_transaction($user_id,'withdraw_hold', -$amount, null, ['stage'=>'hold'],'withdraw');
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
        svntex2_wallet_add_transaction($row->user_id,'withdraw_refund', (float)$row->amount, 'withdraw:'.$withdrawal_id, ['stage'=>'refund'],'withdraw');
    } else { // approved: compute fees
        $gross = (float)$row->amount;
        $tds = round( $gross * SVNTEX2_TDS_RATE, 2 );
        $amc = round( $gross * SVNTEX2_AMC_RATE, 2 );
        $net = $gross - $tds - $amc;
        if ( $net < 0 ) { $net = 0; }
        svntex2_wallet_add_transaction($row->user_id,'withdraw_complete', 0, 'withdraw:'.$withdrawal_id, [ 'gross'=>$gross,'tds'=>$tds,'amc'=>$amc,'net'=>$net ],'withdraw');
        // Store fees in table columns
        $wpdb->update($table,[ 'tds_amount'=>$tds,'amc_amount'=>$amc,'net_amount'=>$net ],['id'=>$withdrawal_id]);
    }
    $wpdb->update($table, [
        'status' => $status,
        'admin_note' => $admin_note,
        'processed_at' => current_time('mysql')
    ], [ 'id' => $withdrawal_id ]);
    return true;
}

?>
