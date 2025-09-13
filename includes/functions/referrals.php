<?php
/**
 * Referrals domain logic (scaffold)
 */
if (!defined('ABSPATH')) exit;

// Ensure referrals table exists
add_action('plugins_loaded', function(){
    global $wpdb; $table = $wpdb->prefix.'svntex_referrals';
    $exists = $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table) );
    if($exists !== $table){
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (\n".
               " id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n".
               " referrer_id BIGINT UNSIGNED NOT NULL,\n".
               " referee_id BIGINT UNSIGNED NOT NULL,\n".
               " qualified TINYINT(1) NOT NULL DEFAULT 0,\n".
               " first_purchase_amount DECIMAL(12,2) NULL,\n".
               " created_at DATETIME NOT NULL,\n".
               " UNIQUE KEY uniq_pair (referrer_id, referee_id),\n".
               " KEY referrer (referrer_id),\n".
               " KEY referee (referee_id)\n".
               ") $charset";
        dbDelta($sql);
    }
}, 20);

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

/**
 * Determine commission rate for a referrer using tiered slabs.
 * Default slabs are based on number of qualified referrals (can be filtered).
 * Each slab: [ 'min' => int, 'max' => int|INF, 'rate' => float ]
 */
function svntex2_referrals_get_commission_rate( $referrer_id, $order = null, $referee_id = null ){
    $qualified = svntex2_referrals_get_qualified_count( $referrer_id );
    $slabs = apply_filters('svntex2_referral_commission_slabs', [
        [ 'min' => 0,  'max' => 4,  'rate' => 0.05 ],
        [ 'min' => 5,  'max' => 9,  'rate' => 0.07 ],
        [ 'min' => 10, 'max' => 19, 'rate' => 0.08 ],
        [ 'min' => 20, 'max' => INF,'rate' => 0.10 ],
    ], $referrer_id, $qualified, $order, $referee_id );
    foreach ( $slabs as $slab ) {
        $min = isset($slab['min']) ? (int)$slab['min'] : 0;
        $max = isset($slab['max']) ? $slab['max'] : INF;
        if ( $qualified >= $min && $qualified <= $max ) {
            return (float) $slab['rate'];
        }
    }
    return defined('SVNTEX2_REFERRAL_RATE') ? (float) SVNTEX2_REFERRAL_RATE : 0;
}

?>
