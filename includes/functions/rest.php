<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function(){
    register_rest_route('svntex2/v1','/wallet/balance', [
        'methods' => 'GET',
        'permission_callback' => function(){ return is_user_logged_in(); },
        'callback' => function(){ return ['balance' => svntex2_wallet_get_balance(get_current_user_id())]; }
    ]);
    // Wallet top-up endpoint (direct credit; integrate payment gateway externally later)
    register_rest_route('svntex2/v1','/wallet/topup', [
        'methods' => 'POST',
        'permission_callback' => function(){ return is_user_logged_in(); },
        'callback' => function( WP_REST_Request $req ){
            $uid = get_current_user_id();
            $amount = (float) $req->get_param('amount');
            $min = (float) apply_filters('svntex2_wallet_topup_min', 100 );
            $max = (float) apply_filters('svntex2_wallet_topup_max', 100000 );
            if( $amount < $min ) return new WP_REST_Response( [ 'error' => 'Minimum top-up is '.$min ], 400 );
            if( $amount > $max ) return new WP_REST_Response( [ 'error' => 'Maximum top-up is '.$max ], 400 );
            // Simple rate limit: max 10 top-ups per hour
            $rl_key = 'svntex2_topup_rl_'.$uid; $bucket = get_transient($rl_key); if(!is_array($bucket)) $bucket=[];
            $now = time(); $bucket = array_filter($bucket, function($ts) use ($now){ return ($now - $ts) < HOUR_IN_SECONDS; });
            if( count($bucket) >= 10 ) return new WP_REST_Response( [ 'error' => 'Too many top-ups, try later' ], 429 );
            $allow = apply_filters('svntex2_wallet_topup_allowed', true, $uid, $amount );
            if( ! $allow ) return new WP_REST_Response( [ 'error' => 'Top-up not allowed' ], 403 );
            $balance = svntex2_wallet_add_transaction( $uid, 'wallet_topup', $amount, 'topup:'.uniqid(), [ 'source'=>'dashboard_rest' ], 'topup' );
            $bucket[] = $now; set_transient($rl_key, $bucket, HOUR_IN_SECONDS );
            return [ 'ok'=> true, 'balance' => $balance, 'amount' => $amount, 'display' => function_exists('wc_price') ? wc_price($balance) : number_format($balance,2) ];
        }
    ]);
    // PB Dashboard meta endpoint
    register_rest_route('svntex2/v1','/pb/meta', [
        'methods' => 'GET',
        'permission_callback' => function(){ return is_user_logged_in(); },
        'callback' => function(){
            $uid = get_current_user_id();
            // Force evaluation so status reflects latest cycle transitions
            if( function_exists('svntex2_pb_evaluate_lifecycle') ) { svntex2_pb_evaluate_lifecycle($uid); }
            $status = get_user_meta($uid,'_svntex2_pb_status', true ); if(!$status) $status='inactive';
            $cycle_start = get_user_meta($uid,'_svntex2_pb_cycle_start', true );
            $cycle_ref_total = (int) get_user_meta($uid,'_svntex2_pb_cycle_ref_total', true );
            $activation_month = get_user_meta($uid,'_svntex2_pb_cycle_activation_month', true );
            $inclusion_start = get_user_meta($uid,'_svntex2_pb_inclusion_start_month', true );
            $month12_prev = get_user_meta($uid,'_svntex2_pb_cycle_prev_month12_refs', true );
            $lifetime_since = get_user_meta($uid,'_svntex2_pb_active_since', true );
            $now_month = date('Y-m');
            // Determine current cycle month index
            $cycle_month_index = null;
            if( $cycle_start ){
                try {
                    $start_dt = new DateTime($cycle_start.'-01');
                    $now_dt = new DateTime($now_month.'-01');
                    $diff = $start_dt->diff($now_dt);
                    $cycle_month_index = $diff->y * 12 + $diff->m + 1; // 1-based
                } catch(Exception $e){ $cycle_month_index = null; }
            }
            // Monthly spend + slab for current and last month
            $current_spend = function_exists('svntex2_pb_get_monthly_spend') ? svntex2_pb_get_monthly_spend($uid,$now_month) : 0.0;
            $last_month = date('Y-m', strtotime('first day of last month'));
            $last_spend = function_exists('svntex2_pb_get_monthly_spend') ? svntex2_pb_get_monthly_spend($uid,$last_month) : 0.0;
            $current_slab = function_exists('svntex2_pb_resolve_slab_percent') ? svntex2_pb_resolve_slab_percent($current_spend) : 0.0;
            // Next slab threshold (simple scan)
            $next_threshold = null; $slabs = function_exists('svntex2_pb_get_slabs') ? svntex2_pb_get_slabs() : [];
            foreach( $slabs as $thr=>$pct ){ if( $current_spend < $thr ){ $next_threshold = $thr; break; } }
            // Suspense summary
            global $wpdb; $susp_tbl = $wpdb->prefix.'svntex_pb_suspense';
            $held_total = (float) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM $susp_tbl WHERE user_id=%d AND status='held'", $uid));
            $held_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $susp_tbl WHERE user_id=%d AND status='held'", $uid));
            return [
                'status' => $status,
                'cycle' => [
                    'start' => $cycle_start,
                    'month_index' => $cycle_month_index,
                    'referrals_in_cycle' => $cycle_ref_total,
                    'activation_month' => $activation_month,
                    'inclusion_start_month' => $inclusion_start,
                    'prev_cycle_month12_refs' => $month12_prev,
                ],
                'lifetime' => [ 'since_ts' => $lifetime_since ? (int)$lifetime_since : null ],
                'spend' => [
                    'current_month' => $current_spend,
                    'last_month' => $last_spend,
                    'slab_percent' => $current_slab,
                    'next_threshold' => $next_threshold,
                ],
                'suspense' => [ 'held_total' => $held_total, 'held_count' => $held_count ],
                'server_time' => current_time('mysql', true),
                'version' => SVNTEX2_VERSION,
            ];
        }
    ]);
});

// Simple capability gate for management actions
function svntex2_api_can_manage(){ return current_user_can('edit_posts'); }

// Product CRUD for svntex_product (admin only)
add_action('rest_api_init', function(){
    register_rest_route('svntex2/v1','/products',[
        [
            'methods' => 'GET',
            'permission_callback' => 'svntex2_api_can_manage',
            'callback' => function( WP_REST_Request $r ){
                $q = new WP_Query([
                    'post_type' => 'svntex_product',
                    'posts_per_page' => (int) ($r->get_param('per_page') ?: 20),
                    'paged' => (int) ($r->get_param('page') ?: 1),
                    's' => (string) $r->get_param('search'),
                ]);
                $items = [];
                while($q->have_posts()){ $q->the_post(); $p = get_post();
                    $items[] = [
                        'id' => $p->ID,
                        'title' => get_the_title($p),
                        'link' => get_permalink($p),
                        'vendor_id' => (int) get_post_meta($p->ID,'vendor_id', true),
                    ];
                }
                wp_reset_postdata();
                return [ 'items' => $items, 'total' => (int)$q->found_posts ];
            }
        ],
        [
            'methods' => 'POST',
            'permission_callback' => 'svntex2_api_can_manage',
            'callback' => function( WP_REST_Request $r ){
                $title = sanitize_text_field($r->get_param('title'));
                if(!$title) return new WP_REST_Response(['error'=>'Title required'],400);
                $content = wp_kses_post($r->get_param('content'));
                $pid = wp_insert_post([
                    'post_type' => 'svntex_product',
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => 'publish',
                ]);
                if(is_wp_error($pid)) return new WP_REST_Response(['error'=>$pid->get_error_message()],500);
                $vendor_id = (int) $r->get_param('vendor_id'); if($vendor_id) update_post_meta($pid,'vendor_id',$vendor_id);
                return [ 'id' => (int)$pid ];
            }
        ],
    ]);
    register_rest_route('svntex2/v1','/products/(?P<id>\d+)',[
        [
            'methods' => 'POST',
            'permission_callback' => 'svntex2_api_can_manage',
            'callback' => function( WP_REST_Request $r ){
                $id = (int) $r['id']; if(!$id) return new WP_REST_Response(['error'=>'Invalid id'],400);
                $payload = [];
                if( null !== $r->get_param('title') ) $payload['post_title'] = sanitize_text_field($r->get_param('title'));
                if( null !== $r->get_param('content') ) $payload['post_content'] = wp_kses_post($r->get_param('content'));
                if($payload){ $payload['ID']=$id; $res = wp_update_post($payload,true); if(is_wp_error($res)) return new WP_REST_Response(['error'=>$res->get_error_message()],500); }
                if( null !== $r->get_param('vendor_id') ){
                    $vid = (int) $r->get_param('vendor_id'); if($vid) update_post_meta($id,'vendor_id',$vid); else delete_post_meta($id,'vendor_id');
                }
                return [ 'id' => $id ];
            }
        ],
        [
            'methods' => 'DELETE',
            'permission_callback' => 'svntex2_api_can_manage',
            'callback' => function( WP_REST_Request $r ){
                $id = (int) $r['id']; if(!$id) return new WP_REST_Response(['error'=>'Invalid id'],400);
                $res = wp_delete_post($id, true);
                return [ 'deleted' => (bool)$res ];
            }
        ],
    ]);
});
?>
