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

/**
 * Evaluate lifecycle using ANNUAL CYCLE model (2 + 4 maintenance + renewal 2 in month12) while preserving prior meta.
 * This supersedes the older rolling-12 logic but keeps lifetime elevation behavior.
 * Metadata introduced (non-destructive):
 *  - _svntex2_pb_cycle_start : YYYY-MM of current cycle (baseline)
 *  - _svntex2_pb_cycle_ref_total : integer referrals counted in current cycle
 *  - _svntex2_pb_inclusion_start_month : YYYY-MM month from which PB inclusion begins (post activation)
 *  - _svntex2_pb_cycle_last_transition : timestamp (for auditing)
 *  - _svntex2_pb_cycle_activation_month : YYYY-MM when activation (2nd referral) achieved in this cycle
 *  - _svntex2_pb_cycle_prev_month12_refs : number of referrals achieved in month12 of prior cycle (snapshot)
 */
function svntex2_pb_evaluate_lifecycle($user_id){
    $status = svntex2_pb_get_status($user_id);
    $now_month = date('Y-m');

    // 1. Establish / load cycle baseline
    $cycle_start = get_user_meta($user_id,'_svntex2_pb_cycle_start', true );
    if( empty($cycle_start) ){
        // Use first day of registration month if available else current month
        $u = get_userdata($user_id);
        $reg_month = $u? date('Y-m', strtotime($u->user_registered)) : $now_month;
        $cycle_start = $reg_month;
        update_user_meta($user_id,'_svntex2_pb_cycle_start',$cycle_start);
        if( get_user_meta($user_id,'_svntex2_pb_cycle_ref_total', true ) === '' ){
            update_user_meta($user_id,'_svntex2_pb_cycle_ref_total',0);
        }
    }

    // 2. Lifetime elevation (unchanged from previous logic)
    $life_at = (int) get_user_meta($user_id,'_svntex2_pb_active_since', true );
    if($status==='active' && !$life_at){ update_user_meta($user_id,'_svntex2_pb_active_since', time()); }
    if($status==='active' && $life_at && ( time() - $life_at ) >= (24*30*DAY_IN_SECONDS ) ){
        svntex2_pb_set_status($user_id,'lifetime');
        return 'lifetime';
    }

    // 3. Compute cycle month index (1..12) relative to cycle_start
    $start_dt = DateTime::createFromFormat('Y-m-d',$cycle_start.'-01');
    $now_dt = DateTime::createFromFormat('Y-m-d',$now_month.'-01');
    if(!$start_dt || !$now_dt){ return $status; }
    $diff = $start_dt->diff($now_dt);
    $cycle_month_index = $diff->y * 12 + $diff->m + 1; // month1 = cycle_start month

    // 4. Snapshot month12 referral counts of previous cycle (already stored when cycle rolled)
    // (No action here unless debugging)

    // 5. Renewal / rollover check: if we have moved into month > 12, finalize previous cycle
    if( $cycle_month_index > 12 ){
        // Determine previous cycle's month12 string
        $prev_month_dt = clone $now_dt; // current month belongs to NEW cycle after rollover
        // month12 was previous month relative to now_dt when index just exceeded 12
        $month12_dt = (clone $now_dt)->modify('-1 month');
        $month12_key = $month12_dt->format('Y-m');
        $month12_referrals = (int) get_user_meta($user_id,'_svntex2_pb_ref_m_'.$month12_key, true );
        update_user_meta($user_id,'_svntex2_pb_cycle_prev_month12_refs',$month12_referrals);

        $cycle_total = (int) get_user_meta($user_id,'_svntex2_pb_cycle_ref_total', true );

        $suspend = false;
        // Maintenance requirement: total >=6
        if( $cycle_total < 6 ){ $suspend = true; }
        // Renewal requirement: month12 new referrals >=2
        if( $month12_referrals < 2 ){ $suspend = true; }

        if( $suspend && $status !== 'lifetime'){
            svntex2_pb_set_status($user_id,'suspended');
            $status = 'suspended';
        } else {
            // Remain active or provisional depending on renewal seeding
            if( $status==='active' || $status==='lifetime'){
                // If month12 had >=2 new, seed new cycle as provisional (treated as activation in new cycle)
                if( $month12_referrals >=2 ){
                    svntex2_pb_set_status($user_id,'provisional');
                    $status='provisional';
                    update_user_meta($user_id,'_svntex2_pb_activated_on', time());
                    update_user_meta($user_id,'_svntex2_pb_cycle_activation_month',$now_month); // new cycle activation month
                    // Inclusion start month = next month
                    $incl = DateTime::createFromFormat('Y-m-d',$now_month.'-01');
                    $incl->modify('+1 month');
                    update_user_meta($user_id,'_svntex2_pb_inclusion_start_month',$incl->format('Y-m'));
                    update_user_meta($user_id,'_svntex2_pb_cycle_ref_total', 2); // seed first 2
                } else {
                    // Could not seed automatically; if not suspended (edge: lifetime) keep status
                    update_user_meta($user_id,'_svntex2_pb_cycle_ref_total', 0);
                }
            }
        }
        // Start new cycle baseline as current month
        update_user_meta($user_id,'_svntex2_pb_cycle_start', $now_month);
        update_user_meta($user_id,'_svntex2_pb_cycle_last_transition', time());
        // Recalculate index for clarity (now month1)
        $cycle_month_index = 1;
    }

    // 6. Enforce activation/inclusion for current cycle if provisional not yet set
    $cycle_ref_total = (int) get_user_meta($user_id,'_svntex2_pb_cycle_ref_total', true );
    $activation_month = get_user_meta($user_id,'_svntex2_pb_cycle_activation_month', true );

    // 7. Suspended recovery: needs fresh pattern inside current cycle
    if( $status==='suspended' ){
        if( $cycle_ref_total >= 2 && $cycle_ref_total < 6 ){
            svntex2_pb_set_status($user_id,'provisional');
            $status='provisional';
            if( empty($activation_month) ){
                update_user_meta($user_id,'_svntex2_pb_cycle_activation_month',$now_month);
                update_user_meta($user_id,'_svntex2_pb_activated_on', time());
                $incl = DateTime::createFromFormat('Y-m-d',$now_month.'-01');
                $incl->modify('+1 month');
                update_user_meta($user_id,'_svntex2_pb_inclusion_start_month',$incl->format('Y-m'));
            }
        }
        if( $cycle_ref_total >= 6 ){
            svntex2_pb_set_status($user_id,'active');
            $status='active';
        }
        return $status; // suspended path processed
    }

    // 8. Provisional -> Active within same cycle
    if( $status==='provisional' && $cycle_ref_total >= 6 ){
        svntex2_pb_set_status($user_id,'active');
        $status='active';
    }

    return $status;
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
    // Legacy cumulative total (do not remove – might be referenced elsewhere)
    $count = (int) get_user_meta($referrer_id,'_svntex2_pb_referral_count_total', true );
    $count++;
    update_user_meta($referrer_id,'_svntex2_pb_referral_count_total',$count);

    // Annual cycle scoped counters
    $cycle_start = get_user_meta($referrer_id,'_svntex2_pb_cycle_start', true );
    if( empty($cycle_start) ){
        $cycle_start = date('Y-m');
        update_user_meta($referrer_id,'_svntex2_pb_cycle_start',$cycle_start);
        update_user_meta($referrer_id,'_svntex2_pb_cycle_ref_total',0);
    }
    $cycle_ref_total = (int) get_user_meta($referrer_id,'_svntex2_pb_cycle_ref_total', true );
    $cycle_ref_total++;
    update_user_meta($referrer_id,'_svntex2_pb_cycle_ref_total',$cycle_ref_total);

    $status = svntex2_pb_get_status($referrer_id);

    // Activation threshold (2 in cycle)
    if( $status==='inactive' && $cycle_ref_total >= 2 ){
        svntex2_pb_set_status($referrer_id,'provisional');
        update_user_meta($referrer_id,'_svntex2_pb_activated_on', time());
        update_user_meta($referrer_id,'_svntex2_pb_cycle_activation_month', date('Y-m'));
        $incl = DateTime::createFromFormat('Y-m-d',date('Y-m').'-01');
        $incl->modify('+1 month');
        update_user_meta($referrer_id,'_svntex2_pb_inclusion_start_month',$incl->format('Y-m'));
    }

    // Provisional -> Active when reach 6 refs in cycle
    if( in_array($status,['provisional','active'], true) && $cycle_ref_total >=6 ){
        svntex2_pb_set_status($referrer_id,'active');
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
    global $wpdb; $inputs_table = $wpdb->prefix.'svntex_profit_inputs';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $inputs_table WHERE month_year=%s", $month) );
    $base_revenue = $row? (float)$row->revenue : 0.0;
    $remaining_wallet = $row? (float)$row->remaining_wallet : 0.0;
    $product_cost = $row? (float)$row->cogs : 0.0;
    $maintenance_percent = $row? (float)$row->maintenance_percent : 0.0;
    $maintenance_amount = round( $base_revenue * $maintenance_percent, 2 );
    $profit = $base_revenue - $remaining_wallet - $product_cost - $maintenance_amount;
    if ( $profit < 0 ) $profit = 0.0;
    // Allow override via option for current month manual profit
    if( $month === date('Y-m') ){
        $override = (float) get_option('svntex2_manual_profit_value',0);
        if( $override > 0 ) $profit = $override;
    }
    // Filters still allow external modification
    $profit = (float) apply_filters('svntex2_pb_company_profit', $profit, $month, $row );
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

    $mode = get_option('svntex2_pb_distribution_mode','normalized');
    $susp = $wpdb->prefix.'svntex_pb_suspense';
    if( $mode === 'direct_formula' ){
        // Store profit_value as average for info only
        $wpdb->insert($dist,[ 'month_year'=>$month,'company_profit'=>$company_profit,'eligible_members'=>count($eligible),'profit_value'=> round($company_profit / count($eligible),4),'created_at'=> current_time('mysql', true) ]);
        foreach($eligible as $e){
            $payout = round( $company_profit * $e['percent'], 2 ); // direct formula
            $ustatus = svntex2_pb_get_status($e['user_id']);
            if( in_array($ustatus,['suspended','provisional'], true ) ){
                $wpdb->insert($susp,[ 'user_id'=>$e['user_id'],'month_year'=>$month,'slab_percent'=>$e['percent']*100,'amount'=>$payout,'reason'=>$ustatus,'status'=>'held','created_at'=> current_time('mysql', true) ]);
            } else {
                svntex2_wallet_add_transaction( $e['user_id'], 'profit_bonus', $payout, 'pb:'.$month, [ 'month'=>$month,'spend'=>$e['spend'],'slab_percent'=>$e['percent'],'mode'=>'direct','company_profit'=>$company_profit ], 'income' );
                $wpdb->insert($payouts,[ 'user_id'=>$e['user_id'],'month_year'=>$month,'slab_percent'=>$e['percent']*100,'payout_amount'=>$payout,'created_at'=> current_time('mysql', true) ]);
            }
        }
    } else {
        // Normalized
        $percent_sum = 0.0; foreach($eligible as $e){ $percent_sum += $e['percent']; }
        if ( $percent_sum <= 0 ) { set_transient($lock,1,DAY_IN_SECONDS); return; }
        $wpdb->insert($dist,[ 'month_year'=>$month,'company_profit'=>$company_profit,'eligible_members'=>count($eligible),'profit_value'=> round($company_profit / count($eligible),4),'created_at'=> current_time('mysql', true) ]);
        foreach($eligible as $e){
            $share_ratio = $e['percent'] / $percent_sum; // 0..1
            $payout = round( $company_profit * $share_ratio, 2 );
            $ustatus = svntex2_pb_get_status($e['user_id']);
            if( in_array($ustatus,['suspended','provisional'], true ) ){
                $wpdb->insert($susp,[ 'user_id'=>$e['user_id'],'month_year'=>$month,'slab_percent'=>$e['percent']*100,'amount'=>$payout,'reason'=>$ustatus,'status'=>'held','created_at'=> current_time('mysql', true) ]);
            } else {
                svntex2_wallet_add_transaction( $e['user_id'], 'profit_bonus', $payout, 'pb:'.$month, [ 'month'=>$month,'spend'=>$e['spend'],'slab_percent'=>$e['percent'],'ratio'=>$share_ratio,'mode'=>'normalized','company_profit'=>$company_profit ], 'income' );
                $wpdb->insert($payouts,[ 'user_id'=>$e['user_id'],'month_year'=>$month,'slab_percent'=>$e['percent']*100,'payout_amount'=>$payout,'created_at'=> current_time('mysql', true) ]);
            }
        }
    }

    // Auto-release suspense for users now Active if option enabled
    if( get_option('svntex2_pb_auto_release',0) ){
        $held = $wpdb->get_results( "SELECT * FROM $susp WHERE status='held'" );
        if($held){
            foreach($held as $hr){
                $ustatus = svntex2_pb_get_status($hr->user_id);
                if( in_array($ustatus,['active','lifetime'], true) ){
                    svntex2_wallet_add_transaction( $hr->user_id, 'profit_bonus', (float)$hr->amount, 'pb_release:'.$hr->month_year, [ 'original_month'=>$hr->month_year,'released'=>current_time('mysql'),'reason'=>$hr->reason,'auto'=>1 ], 'income' );
                    $wpdb->update($susp,[ 'status'=>'released','released_at'=>current_time('mysql') ],['id'=>$hr->id]);
                }
            }
        }
    }

    set_transient($lock,1, 10 * DAY_IN_SECONDS);
}

?>
