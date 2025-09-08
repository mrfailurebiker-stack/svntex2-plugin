<?php
/** Partnership / Profit Bonus (PB) logic (new rules) */
if(!defined('ABSPATH')) exit;

// Slab definition helper (ordered ascending threshold)
function svntex2_pb_get_slabs(){
    $slabs = [
        2499 => 0.10,
        3499 => 0.15,
        4499 => 0.20,
        5499 => 0.25,
        6499 => 0.30,
        7499 => 0.35,
        8499 => 0.40,
        9499 => 0.45,
        10499 => 0.50,
        11499 => 0.55,
        12499 => 0.60,
        13499 => 0.62,
        14499 => 0.65,
        15499 => 0.67,
        16499 => 0.68,
        17499 => 0.69,
        19999 => 0.70,
    ];
    return apply_filters('svntex2_pb_slabs', $slabs);
}

/** Determine slab percent for a monthly spend value */
function svntex2_pb_resolve_slab_percent( $monthly_spend ){ $percent = 0.0; foreach( svntex2_pb_get_slabs() as $threshold=>$p ){ if( $monthly_spend >= $threshold ) { $percent = $p; } } return $percent; }

/** Record or return PB lifecycle meta */
function svntex2_pb_get_status( $user_id ){
    $status = get_user_meta($user_id,'_svntex2_pb_status', true );
    if(!$status){ $status = 'inactive'; }
    return $status;
}
function svntex2_pb_set_status($user_id,$status){ update_user_meta($user_id,'_svntex2_pb_status',$status); }

/**
 * Record monthly referral qualification count snapshot for maintenance.
 * Called when a referral qualifies and by monthly maintenance cron to ensure metric existence.
 */
function svntex2_pb_increment_month_referral($user_id){
    $month = date('Y-m');
    $key = '_svntex2_pb_ref_m_'.$month;
    $cur = (int) get_user_meta($user_id,$key, true );
    update_user_meta($user_id,$key,$cur+1);
}

add_action('svntex2_referral_qualified', function($referrer_id){ svntex2_pb_increment_month_referral($referrer_id); }, 20, 1);

/** Evaluate lifecycle (rolling 12 months) */
function svntex2_pb_evaluate_lifecycle($user_id){
    $status = svntex2_pb_get_status($user_id);
    $now_month = date('Y-m');
    $total12 = 0; $months = [];
    for($i=0;$i<12;$i++){
        $m = date('Y-m', strtotime("first day of -$i month"));
        $months[]=$m;
        $c = (int) get_user_meta($user_id,'_svntex2_pb_ref_m_'.$m, true );
        $total12 += $c;
    }
    // Lifetime if maintained active >= 24 consecutive months (tracked by meta start timestamp)
    $life_at = (int) get_user_meta($user_id,'_svntex2_pb_active_since', true );
    if($status==='active' && !$life_at){ update_user_meta($user_id,'_svntex2_pb_active_since', time()); }
    if($status==='active' && $life_at && ( time() - $life_at ) >= (24*30*DAY_IN_SECONDS ) ){
        svntex2_pb_set_status($user_id,'lifetime');
        return 'lifetime';
    }
    // Suspension rule: if not lifetime and total12 < 4 after initial activation period (> 6 months since activation)
    $activated_on = (int) get_user_meta($user_id,'_svntex2_pb_activated_on', true );
    if($status==='active' && $activated_on && ( time() - $activated_on ) > (6*30*DAY_IN_SECONDS ) ){
        if( $total12 < 4 ){
            svntex2_pb_set_status($user_id,'suspended');
            return 'suspended';
        }
    }
    if($status==='suspended' && $total12 >= 4){ svntex2_pb_set_status($user_id,'active'); return 'active'; }
    return svntex2_pb_get_status($user_id);
}

/** Monthly lifecycle maintenance cron (runs prior to distribution) */
add_action('svntex2_monthly_distribution','svntex2_pb_run_lifecycle_maintenance',5);
function svntex2_pb_run_lifecycle_maintenance(){
    // Evaluate all users who ever had provisional/active/lifetime/suspended
    $query = new WP_User_Query([
        'meta_query' => [ [ 'key'=>'_svntex2_pb_status','compare'=>'EXISTS' ] ],
        'fields'=>'ID', 'number'=>5000
    ]);
    foreach( $query->get_results() as $uid ){
        svntex2_pb_evaluate_lifecycle($uid);
    }
}

/** Increment qualifying referral count for PB and manage activation / maintenance */
function svntex2_pb_track_referral($referrer_id){
    $count = (int) get_user_meta($referrer_id,'_svntex2_pb_referral_count_total', true );
    $count++;
    update_user_meta($referrer_id,'_svntex2_pb_referral_count_total',$count);
    $activated_on = get_user_meta($referrer_id,'_svntex2_pb_activated_on', true );
    $status = svntex2_pb_get_status($referrer_id);
    if( $status==='inactive' && $count >= 2 ){
        // initial activation condition requires 2 qualifying referrals
        svntex2_pb_set_status($referrer_id,'provisional');
        update_user_meta($referrer_id,'_svntex2_pb_activated_on', time());
    }
    // After activation, need total 6 (2+4) within 12 months
    if( $status!=='lifetime' ){
        if( $count >= 6 ){
            svntex2_pb_set_status($referrer_id,'active');
        }
    }
}
add_action('svntex2_referral_qualified', function($referrer_id,$referee_id){ svntex2_pb_track_referral($referrer_id); },10,2);

/** Monthly aggregation: purchases + top-ups - refunds (net spend) */
function svntex2_pb_get_monthly_spend($user_id, $month){
    global $wpdb; $wallet = $wpdb->prefix.'svntex_wallet_transactions';
    $start = $month.'-01 00:00:00'; $end = date('Y-m-t 23:59:59', strtotime($start));
    // Assume types 'wallet_topup' positive, 'purchase' positive (store separately later), refunds type 'refund'
    $credits = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $wallet WHERE user_id=%d AND amount>0 AND type IN ('wallet_topup','purchase') AND created_at BETWEEN %s AND %s", $user_id,$start,$end));
    $refunds = (float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM $wallet WHERE user_id=%d AND amount<0 AND type='refund' AND created_at BETWEEN %s AND %s", $user_id,$start,$end));
    return max(0, $credits - $refunds);
}

/** Determine company profit components (placeholders) */
function svntex2_pb_compute_company_profit($month){
    // Placeholder: integrate real revenue figures externally via filter
    $base_revenue = (float) apply_filters('svntex2_pb_month_revenue', 0.0, $month );
    $remaining_wallet = (float) apply_filters('svntex2_pb_remaining_wallet_total', 0.0, $month );
    $product_cost = (float) apply_filters('svntex2_pb_cogs', 0.0, $month );
    $maintenance_percent = (float) apply_filters('svntex2_pb_maintenance_percent', 0.0, $month );
    $maintenance_amount = round( $base_revenue * $maintenance_percent, 2 );
    $profit = $base_revenue - $remaining_wallet - $product_cost - $maintenance_amount;
    if ( $profit < 0 ) $profit = 0.0;
    return [ 'company_profit'=>$profit, 'revenue'=>$base_revenue, 'remaining_wallet'=>$remaining_wallet, 'cogs'=>$product_cost, 'maintenance'=>$maintenance_amount ];
}

/** Cron hook to perform PB distribution using ratio of slab percents */
add_action('svntex2_monthly_distribution','svntex2_pb_monthly_distribution_run',15);
function svntex2_pb_monthly_distribution_run(){
    $month = date('Y-m', strtotime('first day of last month'));
    $lock = 'svntex2_pb_new_done_'.$month; if ( get_transient($lock) ) return;
    global $wpdb; $payouts = $wpdb->prefix.'svntex_pb_payouts'; $dist = $wpdb->prefix.'svntex_profit_distributions';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $payouts WHERE month_year=%s LIMIT 1", $month));
    if ( $existing ) { set_transient($lock,1,5*DAY_IN_SECONDS); return; }

    // Collect all users with provisional or active status
    $user_query = new WP_User_Query([ 'meta_key'=>'_svntex2_pb_status','meta_compare'=>'IN','meta_value'=>['provisional','active','lifetime'], 'fields'=>'ID', 'number'=>5000 ]);
    $user_ids = $user_query->get_results(); if(!$user_ids) { set_transient($lock,1,DAY_IN_SECONDS); return; }

    $eligible = [];
    foreach($user_ids as $uid){
        // KYC gate & RB gate
        if( svntex2_kyc_get_status($uid) !== 'approved' ) continue;
        if( ! get_user_meta($uid,'_svntex2_rb_awarded', true ) ) continue; // RB must be awarded
        $spend = svntex2_pb_get_monthly_spend($uid,$month);
        if ( $spend < 2499 ) continue; // min slab threshold
        $percent = svntex2_pb_resolve_slab_percent($spend);
        if ( $percent <= 0 ) continue;
        $eligible[] = [ 'user_id'=>$uid, 'spend'=>$spend, 'percent'=>$percent ];
    }
    if ( empty($eligible) ) { set_transient($lock,1,DAY_IN_SECONDS); return; }

    $profit_data = svntex2_pb_compute_company_profit($month);
    $company_profit = $profit_data['company_profit'];
    if ( $company_profit <= 0 ) { set_transient($lock,1,DAY_IN_SECONDS); return; }

    // Normalize by total percent sum so total paid == company_profit
    $percent_sum = 0.0; foreach($eligible as $e){ $percent_sum += $e['percent']; }
    if ( $percent_sum <= 0 ) { set_transient($lock,1,DAY_IN_SECONDS); return; }

    // Insert distribution header (profit_value = company_profit / count)
    $wpdb->insert($dist,[ 'month_year'=>$month,'company_profit'=>$company_profit,'eligible_members'=>count($eligible),'profit_value'=> round($company_profit / count($eligible),4),'created_at'=> current_time('mysql', true) ]);

    foreach($eligible as $e){
        $share_ratio = $e['percent'] / $percent_sum; // 0..1
        $payout = round( $company_profit * $share_ratio, 2 );
        // If user status suspended (or not fully active) move to suspense instead of paying immediately
        $ustatus = svntex2_pb_get_status($e['user_id']);
        if( in_array($ustatus,['suspended','provisional'], true ) ){
            $susp = $wpdb->prefix.'svntex_pb_suspense';
            $wpdb->insert($susp,[ 'user_id'=>$e['user_id'],'month_year'=>$month,'slab_percent'=>$e['percent']*100,'amount'=>$payout,'reason'=>$ustatus,'status'=>'held','created_at'=> current_time('mysql', true) ]);
        } else {
            svntex2_wallet_add_transaction( $e['user_id'], 'profit_bonus', $payout, 'pb:'.$month, [ 'month'=>$month,'spend'=>$e['spend'],'slab_percent'=>$e['percent'],'ratio'=>$share_ratio,'company_profit'=>$company_profit ], 'income' );
            $wpdb->insert($payouts,[ 'user_id'=>$e['user_id'],'month_year'=>$month,'slab_percent'=>$e['percent']*100,'payout_amount'=>$payout,'created_at'=> current_time('mysql', true) ]);
        }
    }

    set_transient($lock,1, 10 * DAY_IN_SECONDS);
}

?>
