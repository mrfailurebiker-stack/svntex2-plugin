<?php
/** Cron + Profit distribution scaffold */
if (!defined('ABSPATH')) exit;

// Schedule monthly distribution event
add_action('init', function(){
    if (!wp_next_scheduled('svntex2_monthly_distribution')) {
        wp_schedule_event(strtotime('first day of next month 00:05:00'), 'monthly', 'svntex2_monthly_distribution');
    }
});

// Register custom schedule if not exists (monthly)
add_filter('cron_schedules', function($schedules){
    if (!isset($schedules['monthly'])) {
        $schedules['monthly'] = [ 'interval' => 30 * DAY_IN_SECONDS, 'display' => __('Once Monthly','svntex2') ];
    }
    if(!isset($schedules['svntex2_five_minutes'])){
        $schedules['svntex2_five_minutes'] = [ 'interval' => 300, 'display' => __('Every 5 Minutes','svntex2') ];
    }
    return $schedules;
});

// Auto schedule suspense release cron
add_action('init', function(){
    if( get_option('svntex2_pb_auto_release') ){
        if( ! wp_next_scheduled('svntex2_auto_release_suspense') ){
            wp_schedule_event(time()+120,'svntex2_five_minutes','svntex2_auto_release_suspense');
        }
    } else {
        $ts = wp_next_scheduled('svntex2_auto_release_suspense');
        if($ts){ wp_unschedule_event($ts,'svntex2_auto_release_suspense'); }
    }
});

add_action('svntex2_auto_release_suspense', function(){
    if( ! get_option('svntex2_pb_auto_release') ) return;
    global $wpdb; $susp = $wpdb->prefix.'svntex_pb_suspense';
    $rows = $wpdb->get_results("SELECT * FROM $susp WHERE status='held' ORDER BY id ASC LIMIT 50");
    if(!$rows) return;
    foreach($rows as $r){
        $ustatus = get_user_meta($r->user_id,'_svntex2_pb_status', true );
        if( in_array($ustatus,['active','lifetime'], true) ){
            svntex2_wallet_add_transaction( $r->user_id, 'profit_bonus', (float)$r->amount, 'pb_release:'.$r->month_year, [ 'auto_release'=>1,'original_month'=>$r->month_year ], 'income' );
            $wpdb->update($susp,[ 'status'=>'released','released_at'=>current_time('mysql', true) ],['id'=>$r->id]);
        }
    }
});

add_action('svntex2_monthly_distribution','svntex2_run_monthly_distribution');
/**
 * Monthly Distribution Runner.
 * - Fires generic distribution hook (future use).
 * - Executes Profit Bonus (PB) distribution for the PREVIOUS month.
 *
 * PB (Profit Bonus) Logic (clear, proportional system):
 * 1. Base Month: Always the previous calendar month relative to runtime (cron scheduled early in new month).
 * 2. Base Income: Sum of referral-based income types ('referral_commission','referral_bonus') with category 'income'.
 * 3. Eligible Users: Those whose base month income >= configurable minimum (default 0).
 * 4. Pool Amount: Derived from total base income via filter 'svntex2_profit_bonus_pool'.
 *      Default pool = 10% of total base income (can be overridden or set fixed externally).
 *      If you want to inject a manual company profit amount, hook the filter and return your figure.
 * 5. Per-User Share: user_base / total_base; payout = pool * share (rounded 2 decimals).
 * 6. Remainder Handling: Any rounding remainder cents are added to the highest-earning user payout.
 * 7. Ledger Entry: Creates wallet transaction type 'profit_bonus' (category 'income').
 * 8. Audit Tables:
 *      - Records month aggregate in svntex_profit_distributions (company_profit = pool, profit_value = average payout).
 *      - Records each user payout in svntex_pb_payouts (slab_percent column repurposed to store share_percent * 100).
 * 9. Idempotency: Transient lock per month & duplicate checks per user (pb_payouts row existing) prevent re-run.
 *
 * Filters:
 *  - svntex2_profit_bonus_pool( float $default_pool, float $total_base, string $month ) : float
 *  - svntex2_profit_bonus_min_income( float $min_income, string $month ) : float
 *  - svntex2_profit_bonus_eligible_users( array $eligible_rows, string $month ) : array
 *  - svntex2_profit_bonus_share( float $share, object $row, float $total_base, string $month ) : float
 *
 * Actions:
 *  - svntex2_profit_bonus_payout( int $user_id, string $month, float $share_percent, float $amount, float $base_amount )
 *  - svntex2_profit_bonus_completed( string $month, float $pool, int $eligible_count )
 */
function svntex2_run_monthly_distribution(){
    do_action('svntex2_distribution_run'); // placeholder for other distribution modules

    // Target previous month (e.g., if today 2025-09-01 -> target 2025-08)
    $ts_prev = strtotime('first day of last month 00:00:00');
    $month = gmdate('Y-m', $ts_prev);
    $start = gmdate('Y-m-01 00:00:00', $ts_prev);
    $end   = gmdate('Y-m-t 23:59:59', $ts_prev);
    $lock_key = 'svntex2_profit_bonus_done_'.$month;
    if ( get_transient($lock_key) ) { return; }

    global $wpdb; $pref = $wpdb->prefix;
    $wallet_table = $pref.'svntex_wallet_transactions';
    $payouts_table = $pref.'svntex_pb_payouts';
    $dist_table = $pref.'svntex_profit_distributions';

    // If already recorded a distribution row for this month, lock & exit (idempotent)
    $existing_dist = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $dist_table WHERE month_year = %s LIMIT 1", $month) );
    if ( $existing_dist ) { set_transient($lock_key,1, 7 * DAY_IN_SECONDS); return; }

    // Aggregate base incomes for the month
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT user_id, SUM(amount) AS total_income
           FROM $wallet_table
          WHERE category='income'
            AND type IN ('referral_commission','referral_bonus')
            AND created_at BETWEEN %s AND %s
          GROUP BY user_id HAVING total_income>0",
        $start, $end
    ) );
    if ( empty($rows) ) { set_transient($lock_key,1, 7 * DAY_IN_SECONDS); return; }

    $total_base = 0.0; foreach($rows as $r){ $total_base += (float)$r->total_income; }
    if ( $total_base <= 0 ) { set_transient($lock_key,1, 7 * DAY_IN_SECONDS); return; }

    $min_income = (float) apply_filters('svntex2_profit_bonus_min_income', 0.0, $month);
    $eligible = array_filter($rows, function($r) use ($min_income){ return (float)$r->total_income >= $min_income; });
    $eligible = apply_filters('svntex2_profit_bonus_eligible_users', $eligible, $month);
    if ( empty($eligible) ) { set_transient($lock_key,1, 7 * DAY_IN_SECONDS); return; }

    // Determine pool
    $default_pool = round( $total_base * 0.10, 2 ); // default 10% of base income
    $pool = (float) apply_filters('svntex2_profit_bonus_pool', $default_pool, $total_base, $month);
    if ( $pool <= 0 ) { set_transient($lock_key,1, 7 * DAY_IN_SECONDS); return; }

    // Prepare distribution (proportional)
    $payouts = [];
    $sum_payouts = 0.0;
    $top_user_id = 0; $top_base = 0.0;
    foreach( $eligible as $row ) {
        $base_amount = (float)$row->total_income;
        if ( $base_amount > $top_base ) { $top_base = $base_amount; $top_user_id = (int)$row->user_id; }
        $share = $base_amount / $total_base; // 0..1
        $share = (float) apply_filters('svntex2_profit_bonus_share', $share, $row, $total_base, $month );
        if ( $share <= 0 ) { continue; }
        $amount = round( $pool * $share, 2 );
        if ( $amount <= 0 ) { continue; }
        $payouts[] = [ 'user_id'=>(int)$row->user_id, 'share'=>$share, 'amount'=>$amount, 'base'=>$base_amount ];
        $sum_payouts += $amount;
    }
    if ( empty($payouts) ) { set_transient($lock_key,1, 7 * DAY_IN_SECONDS); return; }

    // Distribute rounding remainder to top earner
    $remainder = round( $pool - $sum_payouts, 2 );
    if ( abs($remainder) >= 0.01 && $top_user_id ) {
        foreach($payouts as &$p){ if($p['user_id'] === $top_user_id){ $p['amount'] = round($p['amount'] + $remainder,2); break; } }
        unset($p); // break reference
    }

    // Insert distribution header row
    $eligible_count = count($payouts);
    $average = round( $pool / max(1,$eligible_count), 4 );
    $wpdb->insert( $dist_table, [
        'month_year'        => $month,
        'company_profit'    => $pool,
        'eligible_members'  => $eligible_count,
        'profit_value'      => $average,
        'created_at'        => current_time('mysql', true)
    ], [ '%s','%f','%d','%f','%s' ] );

    // Process individual payouts (avoid duplicates)
    foreach( $payouts as $p ) {
        $already = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $payouts_table WHERE user_id=%d AND month_year=%s LIMIT 1", $p['user_id'], $month ) );
        if ( $already ) { continue; }
        svntex2_wallet_add_transaction( $p['user_id'], 'profit_bonus', $p['amount'], 'pb:'.$month, [
            'month'       => $month,
            'pool'        => $pool,
            'share'       => $p['share'],
            'share_pct'   => round($p['share']*100,4),
            'base_amount' => $p['base'],
            'total_base'  => $total_base
        ], 'income' );
        // Store in pb_payouts (slab_percent used as share percent for audit)
        $wpdb->insert( $payouts_table, [
            'user_id'      => $p['user_id'],
            'month_year'   => $month,
            'slab_percent' => round($p['share']*100,2),
            'payout_amount'=> $p['amount'],
            'created_at'   => current_time('mysql', true)
        ], [ '%d','%s','%f','%f','%s' ] );
        do_action('svntex2_profit_bonus_payout', $p['user_id'], $month, round($p['share']*100,4), $p['amount'], $p['base'] );
    }

    set_transient($lock_key,1, 14 * DAY_IN_SECONDS); // lock for two weeks
    do_action('svntex2_profit_bonus_completed', $month, $pool, $eligible_count );
}

// Lightweight weekly maintenance: trim support logs and clean expired transients
add_action('init', function(){
    if (!wp_next_scheduled('svntex2_weekly_maintenance')) {
        wp_schedule_event(time()+180, 'weekly', 'svntex2_weekly_maintenance');
    }
});
add_action('svntex2_weekly_maintenance', function(){
    // Trim support log to last 200 items
    $log = get_option('svntex2_support_log'); if(is_array($log) && count($log) > 200){ $log = array_slice($log, -200); update_option('svntex2_support_log', $log, false); }
    // Cleanup expired transients (WordPress auto-cleans, but we can trigger)
    if ( function_exists('delete_expired_transients') ) { delete_expired_transients(); }
});

?>
