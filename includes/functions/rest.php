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
    // Wallet transactions list (admin) and user summary
    register_rest_route('svntex2/v1','/wallet/transactions', [
        'methods' => 'GET',
        'permission_callback' => 'svntex2_api_can_manage',
        'callback' => function( WP_REST_Request $r ){
            global $wpdb; $t = $wpdb->prefix.'svntex_wallet_transactions';
            $uid = (int) $r->get_param('user_id');
            $limit = min( (int) ($r->get_param('per_page') ?: 50), 200 );
            $rows = $uid ? $wpdb->get_results( $wpdb->prepare("SELECT * FROM $t WHERE user_id=%d ORDER BY id DESC LIMIT %d", $uid, $limit), ARRAY_A )
                         : $wpdb->get_results( $wpdb->prepare("SELECT * FROM $t ORDER BY id DESC LIMIT %d", $limit), ARRAY_A );
            return [ 'items' => $rows ?: [] ];
        }
    ]);
    // Referrals admin endpoints
    register_rest_route('svntex2/v1','/referrals', [
        'methods' => 'GET',
        'permission_callback' => 'svntex2_api_can_manage',
        'callback' => function( WP_REST_Request $r ){
            global $wpdb; $t = $wpdb->prefix.'svntex_referrals';
            $uid = (int) $r->get_param('referrer_id');
            $rows = $uid ? $wpdb->get_results( $wpdb->prepare("SELECT * FROM $t WHERE referrer_id=%d ORDER BY id DESC", $uid), ARRAY_A )
                         : $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC LIMIT 200", ARRAY_A );
            return [ 'items' => $rows ?: [] ];
        }
    ]);
    register_rest_route('svntex2/v1','/referrals/link', [
        'methods' => 'POST',
        'permission_callback' => 'svntex2_api_can_manage',
        'callback' => function( WP_REST_Request $r ){
            $ref = (int) $r->get_param('referrer_id'); $ree = (int) $r->get_param('referee_id');
            if(!$ref || !$ree) return new WP_REST_Response(['error'=>'referrer_id and referee_id required'],400);
            $ok = svntex2_referrals_link($ref,$ree);
            return [ 'ok' => (bool)$ok ];
        }
    ]);
    register_rest_route('svntex2/v1','/referrals/qualify', [
        'methods' => 'POST',
        'permission_callback' => 'svntex2_api_can_manage',
        'callback' => function( WP_REST_Request $r ){
            $ref = (int) $r->get_param('referrer_id'); $ree = (int) $r->get_param('referee_id'); $amt = (float) $r->get_param('amount');
            if(!$ref || !$ree) return new WP_REST_Response(['error'=>'referrer_id and referee_id required'],400);
            svntex2_referrals_mark_qualified($ref,$ree,$amt);
            return [ 'ok' => true ];
        }
    ]);
    // KYC endpoints (admin)
    register_rest_route('svntex2/v1','/kyc', [
        'methods' => 'GET',
        'permission_callback' => 'svntex2_api_can_manage',
        'callback' => function( WP_REST_Request $r ){
            global $wpdb; $t=$wpdb->prefix.'svntex_kyc_submissions';
            $rows = $wpdb->get_results("SELECT id,user_id,status,created_at,updated_at FROM $t ORDER BY id DESC LIMIT 200", ARRAY_A);
            return [ 'items' => $rows ?: [] ];
        }
    ]);
    register_rest_route('svntex2/v1','/kyc/(?P<user_id>\d+)', [
        'methods' => 'POST',
        'permission_callback' => 'svntex2_api_can_manage',
        'callback' => function( WP_REST_Request $r ){
            $uid = (int) $r['user_id']; $status = sanitize_key($r->get_param('status'));
            if(!$uid || !in_array($status,['pending','approved','rejected'], true)) return new WP_REST_Response(['error'=>'Invalid'],400);
            svntex2_kyc_set_status($uid, $status);
            return [ 'user_id' => $uid, 'status' => $status ];
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
                    $cats = wp_get_object_terms($p->ID, 'svntex_category', [ 'fields' => 'ids' ]);
                    $thumb_id = (int) get_post_thumbnail_id($p->ID);
                    $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';
                    $items[] = [
                        'id' => $p->ID,
                        'title' => get_the_title($p),
                        'link' => get_permalink($p),
                        'vendor_id' => (int) get_post_meta($p->ID,'vendor_id', true),
                        'featured_media' => $thumb_id,
                        'thumbnail_url' => $thumb_url,
                        'categories' => array_map('intval', is_wp_error($cats)?[]:$cats),
            'mrp' => (float) get_post_meta($p->ID,'mrp', true),
            'tax_percent' => (float) get_post_meta($p->ID,'tax_percent', true),
            'company_profit' => (float) get_post_meta($p->ID,'company_profit', true),
            'sku' => (string) get_post_meta($p->ID,'sku', true),
            'video_media' => (int) get_post_meta($p->ID,'video_media', true),
            'video_url' => (string) get_post_meta($p->ID,'video_url', true),
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
                // Categories
                $cats = $r->get_param('categories'); if(null !== $cats){
                    $cat_ids = array_map('intval', (array)$cats);
                    wp_set_object_terms($pid, $cat_ids, 'svntex_category', false);
                }
                // Featured image
                $fm = (int) $r->get_param('featured_media'); if($fm>0) set_post_thumbnail($pid, $fm);
                // Custom fields
                $nums = [ 'mrp','tax_percent','company_profit' ];
                foreach($nums as $k){ if(null !== $r->get_param($k)){ update_post_meta($pid,$k, (float) $r->get_param($k)); } }
                if(null !== $r->get_param('sku')) update_post_meta($pid,'sku', sanitize_text_field($r->get_param('sku')) );
                if(null !== $r->get_param('video_media')) update_post_meta($pid,'video_media', (int) $r->get_param('video_media') );
                if(null !== $r->get_param('video_url')) update_post_meta($pid,'video_url', esc_url_raw($r->get_param('video_url')) );
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
                // Categories
                if( null !== $r->get_param('categories') ){
                    $cat_ids = array_map('intval', (array) $r->get_param('categories'));
                    wp_set_object_terms($id, $cat_ids, 'svntex_category', false);
                }
                // Featured image
                if( null !== $r->get_param('featured_media') ){
                    $fm = (int) $r->get_param('featured_media');
                    if($fm>0) set_post_thumbnail($id, $fm); else delete_post_thumbnail($id);
                }
                // Custom fields
                $nums = [ 'mrp','tax_percent','company_profit' ];
                foreach($nums as $k){ if(null !== $r->get_param($k)){ $val = (float) $r->get_param($k); if($val>0) update_post_meta($id,$k,$val); else delete_post_meta($id,$k); } }
                if(null !== $r->get_param('sku')){ $sku = sanitize_text_field($r->get_param('sku')); if($sku!=='') update_post_meta($id,'sku',$sku); else delete_post_meta($id,'sku'); }
                if(null !== $r->get_param('video_media')){ $vm=(int)$r->get_param('video_media'); if($vm>0) update_post_meta($id,'video_media',$vm); else delete_post_meta($id,'video_media'); }
                if(null !== $r->get_param('video_url')){ $vu = esc_url_raw($r->get_param('video_url')); if($vu!=='') update_post_meta($id,'video_url',$vu); else delete_post_meta($id,'video_url'); }
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

// Users management (list and update) for admins
function svntex2_api_can_manage_users(){ return current_user_can('edit_users'); }
function svntex2_format_user_for_admin($u){
    return [
        'id' => (int)$u->ID,
        'username' => $u->user_login,
        'email' => $u->user_email,
        'first_name' => get_user_meta($u->ID,'first_name', true),
        'last_name' => get_user_meta($u->ID,'last_name', true),
        'display_name' => $u->display_name,
        'roles' => $u->roles,
        'registered' => $u->user_registered,
        'meta' => [
            'customer_id' => get_user_meta($u->ID,'customer_id', true),
            'mobile' => get_user_meta($u->ID,'mobile', true),
            'gender' => get_user_meta($u->ID,'gender', true),
            'dob' => get_user_meta($u->ID,'dob', true),
            'age' => (int) get_user_meta($u->ID,'age', true),
            'employee_id' => get_user_meta($u->ID,'employee_id', true),
            'referral_source' => get_user_meta($u->ID,'referral_source', true),
        ]
    ];
}

add_action('rest_api_init', function(){
    register_rest_route('svntex2/v1','/users', [
        [
            'methods' => 'GET',
            'permission_callback' => 'svntex2_api_can_manage_users',
            'callback' => function( WP_REST_Request $r ){
                $args = [ 'number' => (int) ($r->get_param('per_page') ?: 20), 'paged' => (int) ($r->get_param('page') ?: 1), 'orderby'=>'registered', 'order'=>'DESC' ];
                $search = (string) $r->get_param('search'); if($search){ $args['search'] = '*'.esc_attr($search).'*'; $args['search_columns']=['user_login','user_email','user_nicename']; }
                $q = new WP_User_Query($args);
                $items = array_map('svntex2_format_user_for_admin', $q->get_results());
                return [ 'items'=>$items, 'total' => (int)$q->get_total() ];
            }
        ],
    ]);
    register_rest_route('svntex2/v1','/users/(?P<id>\d+)', [
        [
            'methods' => 'GET',
            'permission_callback' => 'svntex2_api_can_manage_users',
            'callback' => function( WP_REST_Request $r ){
                $u = get_user_by('id', (int)$r['id']); if(!$u) return new WP_REST_Response(['error'=>'Not found'],404);
                return svntex2_format_user_for_admin($u);
            }
        ],
        [
            'methods' => 'POST',
            'permission_callback' => 'svntex2_api_can_manage_users',
            'callback' => function( WP_REST_Request $r ){
                $id = (int) $r['id']; $u = get_user_by('id',$id); if(!$u) return new WP_REST_Response(['error'=>'Not found'],404);
                $payload = [];
                if( null !== $r->get_param('first_name') ) $payload['first_name'] = sanitize_text_field($r->get_param('first_name'));
                if( null !== $r->get_param('last_name') ) $payload['last_name'] = sanitize_text_field($r->get_param('last_name'));
                if( null !== $r->get_param('display_name') ) $payload['display_name'] = sanitize_text_field($r->get_param('display_name'));
                if( null !== $r->get_param('email') ){
                    $email = sanitize_email($r->get_param('email')); if($email && $email !== $u->user_email && email_exists($email)) return new WP_REST_Response(['error'=>'Email already in use'],400); $payload['user_email']=$email;
                }
                if($payload){ $payload['ID']=$id; $res = wp_update_user($payload); if(is_wp_error($res)) return new WP_REST_Response(['error'=>$res->get_error_message()],500); }
                // Update metas (cannot change customer_id by request)
                $meta_keys = ['mobile','gender','dob','age','employee_id','referral_source'];
                foreach($meta_keys as $mk){ if( null !== $r->get_param($mk) ) update_user_meta($id,$mk, sanitize_text_field($r->get_param($mk)) ); }
                return svntex2_format_user_for_admin( get_user_by('id',$id) );
            }
        ],
    ]);
});
?>
