<?php
/**
 * Referrals domain logic (scaffold)
 */
if (!defined('ABSPATH')) exit;

/**
 * Record a referral relationship (referrer -> referee) if not exists.
 */
function svntex2_referrals_link($referrer_id, $referee_id){
    if ($referrer_id == $referee_id) return false;
    global $wpdb; $table = $wpdb->prefix.'svntex_referrals';
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE referrer_id=%d AND referee_id=%d", $referrer_id, $referee_id));
    if ($exists) return false;
    $wpdb->insert($table, [
        'referrer_id' => $referrer_id,
        'referee_id'  => $referee_id,
        'qualified'   => 0,
        'first_purchase_amount' => null,
        'created_at'  => current_time('mysql')
    ], ['%d','%d','%d','%f','%s']);
    // Increment meta counter
    $count = (int) get_user_meta($referrer_id,'referral_count', true);
    update_user_meta($referrer_id,'referral_count', $count + 1);
    return true;
}

/** Determine if a referee qualifies the referral (placeholder). */
function svntex2_referrals_mark_qualified($referrer_id, $referee_id, $amount){
    global $wpdb; $table = $wpdb->prefix.'svntex_referrals';
    $wpdb->update($table, [ 'qualified' => 1, 'first_purchase_amount' => (float)$amount ], [
        'referrer_id' => $referrer_id,
        'referee_id'  => $referee_id
    ], ['%d','%f'], ['%d','%d']);
}

/** Simple query: total qualified referrals. */
function svntex2_referrals_get_qualified_count($user_id){
    global $wpdb; $table = $wpdb->prefix.'svntex_referrals';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE referrer_id=%d AND qualified=1", $user_id));
}

?>
