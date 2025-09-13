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
        <?php
        if (!defined('ABSPATH')) exit;

        // Simple capability gate for management actions
        function svntex2_api_can_manage(){ return current_user_can('edit_posts'); }

        // Wallet + PB + referrals + KYC base endpoints
        add_action('rest_api_init', function(){
            register_rest_route('svntex2/v1','/wallet/balance', [
                'methods' => 'GET',
                'permission_callback' => function(){ return is_user_logged_in(); },
                'callback' => function(){ return ['balance' => svntex2_wallet_get_balance(get_current_user_id())]; }
            ]);
            register_rest_route('svntex2/v1','/wallet/topup', [
                'methods' => 'POST',
                'permission_callback' => function(){ return is_user_logged_in(); },
                'callback' => function( WP_REST_Request $req ){
                    $uid = get_current_user_id();
                    $amount = (float) $req->get_param('amount');
                    if($amount <= 0) return new WP_REST_Response(['error'=>'Invalid amount'],400);
                    $balance = svntex2_wallet_add_transaction( $uid, 'wallet_topup', $amount, 'topup:'.uniqid(), [ 'source'=>'dashboard_rest' ], 'topup' );
                    return [ 'ok'=> true, 'balance' => $balance, 'amount' => $amount ];
                }
            ]);
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
            // Referrals
            register_rest_route('svntex2/v1','/referrals', [
                'methods' => 'GET', 'permission_callback' => 'svntex2_api_can_manage',
                'callback' => function( WP_REST_Request $r ){
                    global $wpdb; $t = $wpdb->prefix.'svntex_referrals';
                    $uid = (int) $r->get_param('referrer_id');
                    $rows = $uid ? $wpdb->get_results( $wpdb->prepare("SELECT * FROM $t WHERE referrer_id=%d ORDER BY id DESC", $uid), ARRAY_A )
                                 : $wpdb->get_results( "SELECT * FROM $t ORDER BY id DESC LIMIT 200", ARRAY_A );
                    return [ 'items' => $rows ?: [] ];
                }
            ]);
            register_rest_route('svntex2/v1','/referrals/link', [
                'methods' => 'POST','permission_callback' => 'svntex2_api_can_manage',
                'callback' => function( WP_REST_Request $r ){
                    $ref = (int) $r->get_param('referrer_id'); $ree = (int) $r->get_param('referee_id');
                    if(!$ref || !$ree) return new WP_REST_Response(['error'=>'referrer_id and referee_id required'],400);
                    $ok = function_exists('svntex2_referrals_link') ? svntex2_referrals_link($ref,$ree) : false;
                    return [ 'ok' => (bool)$ok ];
                }
            ]);
            register_rest_route('svntex2/v1','/referrals/qualify', [
                'methods' => 'POST','permission_callback' => 'svntex2_api_can_manage',
                'callback' => function( WP_REST_Request $r ){
                    $ref = (int) $r->get_param('referrer_id'); $ree = (int) $r->get_param('referee_id'); $amt = (float) $r->get_param('amount');
                    if(!$ref || !$ree) return new WP_REST_Response(['error'=>'referrer_id and referee_id required'],400);
                    if(function_exists('svntex2_referrals_mark_qualified')) svntex2_referrals_mark_qualified($ref,$ree,$amt);
                    return [ 'ok' => true ];
                }
            ]);
            // KYC list and status
            register_rest_route('svntex2/v1','/kyc', [
                'methods' => 'GET','permission_callback' => 'svntex2_api_can_manage',
                'callback' => function(){
                    global $wpdb; $t=$wpdb->prefix.'svntex_kyc_submissions';
                    $rows = $wpdb->get_results("SELECT id,user_id,status,created_at,updated_at FROM $t ORDER BY id DESC LIMIT 200", ARRAY_A);
                    return [ 'items' => $rows ?: [] ];
                }
            ]);
            register_rest_route('svntex2/v1','/kyc/(?P<user_id>\d+)', [
                'methods' => 'POST', 'permission_callback' => 'svntex2_api_can_manage',
                'callback' => function( WP_REST_Request $r ){
                    $uid = (int) $r['user_id']; $status = sanitize_key($r->get_param('status'));
                    if(!$uid || !in_array($status,['pending','approved','rejected'], true)) return new WP_REST_Response(['error'=>'Invalid'],400);
                    if(function_exists('svntex2_kyc_set_status')) svntex2_kyc_set_status($uid, $status);
                    return [ 'user_id' => $uid, 'status' => $status ];
                }
            ]);
        });

        // Products CRUD + extras
        add_action('rest_api_init', function(){
            register_rest_route('svntex2/v1','/products',[
                [
                    'methods' => 'GET', 'permission_callback' => 'svntex2_api_can_manage',
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
                            $brands = wp_get_object_terms($p->ID, 'svntex_brand', [ 'fields' => 'ids' ]);
                            $tags = wp_get_object_terms($p->ID, 'svntex_tag', [ 'fields' => 'ids' ]);
                            $ship_cls = wp_get_object_terms($p->ID, 'svntex_shipping_class', [ 'fields' => 'ids' ]);
                            $thumb_id = (int) get_post_thumbnail_id($p->ID);
                            $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';
                            $items[] = [
                                'id' => $p->ID,
                                'title' => get_the_title($p),
                                'slug' => $p->post_name,
                                'status' => $p->post_status,
                                'link' => get_permalink($p),
                                'vendor_id' => (int) get_post_meta($p->ID,'vendor_id', true),
                                'featured_media' => $thumb_id,
                                'thumbnail_url' => $thumb_url,
                                'categories' => array_map('intval', is_wp_error($cats)?[]:$cats),
                                'brands' => array_map('intval', is_wp_error($brands)?[]:$brands),
                                'tags' => array_map('intval', is_wp_error($tags)?[]:$tags),
                                'shipping_class' => array_map('intval', is_wp_error($ship_cls)?[]:$ship_cls),
                                'base_price' => (float) get_post_meta($p->ID,'base_price', true),
                                'discount_price' => (float) get_post_meta($p->ID,'discount_price', true),
                                'mrp' => (float) get_post_meta($p->ID,'mrp', true),
                                'tax_percent' => (float) get_post_meta($p->ID,'tax_percent', true),
                                'company_profit' => (float) get_post_meta($p->ID,'company_profit', true),
                                'sku' => (string) get_post_meta($p->ID,'sku', true),
                                'stock_qty' => (int) get_post_meta($p->ID,'stock_qty', true),
                                'stock_status' => (string) get_post_meta($p->ID,'stock_status', true),
                                'low_stock_threshold' => (int) get_post_meta($p->ID,'low_stock_threshold', true),
                                'video_media' => (int) get_post_meta($p->ID,'video_media', true),
                                'video_url' => (string) get_post_meta($p->ID,'video_url', true),
                                'gallery' => (array) get_post_meta($p->ID,'gallery', true),
                                'weight' => (float) get_post_meta($p->ID,'weight', true),
                                'length' => (float) get_post_meta($p->ID,'length', true),
                                'width' => (float) get_post_meta($p->ID,'width', true),
                                'height' => (float) get_post_meta($p->ID,'height', true),
                                'meta_title' => (string) get_post_meta($p->ID,'meta_title', true),
                                'meta_description' => (string) get_post_meta($p->ID,'meta_description', true),
                                'is_featured' => (bool) get_post_meta($p->ID,'is_featured', true),
                                'visibility' => (string) get_post_meta($p->ID,'visibility', true),
                                'archived' => (bool) get_post_meta($p->ID,'archived', true),
                                'approved' => (bool) get_post_meta($p->ID,'approved', true),
                                'attributes' => (array) get_post_meta($p->ID,'attributes', true),
                                'variations' => (array) get_post_meta($p->ID,'variations', true),
                                'view_count' => (int) get_post_meta($p->ID,'view_count', true),
                                'sales_count' => (int) get_post_meta($p->ID,'sales_count', true),
                                'returns_count' => (int) get_post_meta($p->ID,'returns_count', true),
                            ];
                        }
                        wp_reset_postdata();
                        return [ 'items' => $items, 'total' => (int)$q->found_posts ];
                    }
                ],
                [
                    'methods' => 'POST','permission_callback' => 'svntex2_api_can_manage',
                    'callback' => function( WP_REST_Request $r ){
                        $title = sanitize_text_field($r->get_param('title'));
                        if(!$title) return new WP_REST_Response(['error'=>'Title required'],400);
                        $content = wp_kses_post($r->get_param('content'));
                        $status = $r->get_param('status');
                        $post_status = ($status==='draft') ? 'draft' : 'publish';
                        $slug = sanitize_title($r->get_param('slug'));
                        $pid = wp_insert_post([
                            'post_type' => 'svntex_product', 'post_title' => $title, 'post_content' => $content,
                            'post_status' => $post_status, 'post_name' => $slug ?: null,
                        ]);
                        if(is_wp_error($pid)) return new WP_REST_Response(['error'=>$pid->get_error_message()],500);
                        $vendor_id = (int) $r->get_param('vendor_id'); if($vendor_id) update_post_meta($pid,'vendor_id',$vendor_id);
                        $cats = $r->get_param('categories'); if(null !== $cats){ wp_set_object_terms($pid, array_map('intval',(array)$cats), 'svntex_category', false); }
                        $brands = $r->get_param('brands'); if(null !== $brands){ wp_set_object_terms($pid, array_map('intval',(array)$brands), 'svntex_brand', false); }
                        $tags = $r->get_param('tags'); if(null !== $tags){ wp_set_object_terms($pid, array_map('intval',(array)$tags), 'svntex_tag', false); }
                        $ship = $r->get_param('shipping_class'); if(null !== $ship){ wp_set_object_terms($pid, array_map('intval',(array)$ship), 'svntex_shipping_class', false); }
                        $fm = (int) $r->get_param('featured_media'); if($fm>0) set_post_thumbnail($pid, $fm);
                        $nums = [ 'base_price','discount_price','mrp','tax_percent','company_profit','stock_qty','low_stock_threshold','weight','length','width','height' ];
                        foreach($nums as $k){ if(null !== $r->get_param($k)){ update_post_meta($pid,$k, (float) $r->get_param($k)); } }
                        if(null !== $r->get_param('stock_status')) update_post_meta($pid,'stock_status', sanitize_text_field($r->get_param('stock_status')) );
                        if(null !== $r->get_param('sku')) update_post_meta($pid,'sku', sanitize_text_field($r->get_param('sku')) );
                        if(null !== $r->get_param('video_media')) update_post_meta($pid,'video_media', (int) $r->get_param('video_media') );
                        if(null !== $r->get_param('video_url')) update_post_meta($pid,'video_url', esc_url_raw($r->get_param('video_url')) );
                        if(null !== $r->get_param('gallery')) update_post_meta($pid,'gallery', array_map('intval', (array)$r->get_param('gallery')) );
                        if(null !== $r->get_param('meta_title')) update_post_meta($pid,'meta_title', sanitize_text_field($r->get_param('meta_title')) );
                        if(null !== $r->get_param('meta_description')) update_post_meta($pid,'meta_description', sanitize_textarea_field($r->get_param('meta_description')) );
                        if(null !== $r->get_param('is_featured')) update_post_meta($pid,'is_featured', (bool)$r->get_param('is_featured') ? 1 : 0 );
                        if(null !== $r->get_param('visibility')) update_post_meta($pid,'visibility', sanitize_text_field($r->get_param('visibility')) );
                        if(null !== $r->get_param('archived')) update_post_meta($pid,'archived', (bool)$r->get_param('archived') ? 1 : 0 );
                        if(null !== $r->get_param('approved')) update_post_meta($pid,'approved', (bool)$r->get_param('approved') ? 1 : 0 );
                        if(null !== $r->get_param('attributes')) update_post_meta($pid,'attributes', (array)$r->get_param('attributes') );
                        if(null !== $r->get_param('variations')) update_post_meta($pid,'variations', (array)$r->get_param('variations') );
                        return [ 'id' => (int)$pid ];
                    }
                ],
            ]);

            register_rest_route('svntex2/v1','/products/(?P<id>\d+)',[
                [
                    'methods' => 'POST', 'permission_callback' => 'svntex2_api_can_manage',
                    'callback' => function( WP_REST_Request $r ){
                        $id = (int) $r['id']; if(!$id) return new WP_REST_Response(['error'=>'Invalid id'],400);
                        $payload = [];
                        if( null !== $r->get_param('title') ) $payload['post_title'] = sanitize_text_field($r->get_param('title'));
                        if( null !== $r->get_param('content') ) $payload['post_content'] = wp_kses_post($r->get_param('content'));
                        if( null !== $r->get_param('slug') ) $payload['post_name'] = sanitize_title($r->get_param('slug'));
                        if( null !== $r->get_param('status') ){
                            $st = $r->get_param('status');
                            $payload['post_status'] = ($st==='draft') ? 'draft' : 'publish';
                        }
                        if($payload){ $payload['ID']=$id; $res = wp_update_post($payload,true); if(is_wp_error($res)) return new WP_REST_Response(['error'=>$res->get_error_message()],500); }
                        if( null !== $r->get_param('vendor_id') ){
                            $vid = (int) $r->get_param('vendor_id'); if($vid) update_post_meta($id,'vendor_id',$vid); else delete_post_meta($id,'vendor_id');
                        }
                        if( null !== $r->get_param('categories') ) wp_set_object_terms($id, array_map('intval',(array)$r->get_param('categories')), 'svntex_category', false);
                        if( null !== $r->get_param('brands') ) wp_set_object_terms($id, array_map('intval',(array)$r->get_param('brands')), 'svntex_brand', false);
                        if( null !== $r->get_param('tags') ) wp_set_object_terms($id, array_map('intval',(array)$r->get_param('tags')), 'svntex_tag', false);
                        if( null !== $r->get_param('shipping_class') ) wp_set_object_terms($id, array_map('intval',(array)$r->get_param('shipping_class')), 'svntex_shipping_class', false);
                        if( null !== $r->get_param('featured_media') ){
                            $fm = (int) $r->get_param('featured_media');
                            if($fm>0) set_post_thumbnail($id, $fm); else delete_post_thumbnail($id);
                        }
                        $nums = [ 'base_price','discount_price','mrp','tax_percent','company_profit','stock_qty','low_stock_threshold','weight','length','width','height' ];
                        foreach($nums as $k){ if(null !== $r->get_param($k)){ $val = (float) $r->get_param($k); if($val>0 || in_array($k,['tax_percent','company_profit'], true)){ update_post_meta($id,$k,$val); } else { delete_post_meta($id,$k); } } }
                        if(null !== $r->get_param('stock_status')){ $v = sanitize_text_field($r->get_param('stock_status')); if($v!=='') update_post_meta($id,'stock_status',$v); else delete_post_meta($id,'stock_status'); }
                        if(null !== $r->get_param('sku')){ $sku = sanitize_text_field($r->get_param('sku')); if($sku!=='') update_post_meta($id,'sku',$sku); else delete_post_meta($id,'sku'); }
                        if(null !== $r->get_param('video_media')){ $vm=(int)$r->get_param('video_media'); if($vm>0) update_post_meta($id,'video_media',$vm); else delete_post_meta($id,'video_media'); }
                        if(null !== $r->get_param('video_url')){ $vu = esc_url_raw($r->get_param('video_url')); if($vu!=='') update_post_meta($id,'video_url',$vu); else delete_post_meta($id,'video_url'); }
                        if(null !== $r->get_param('gallery')){ $g = array_map('intval',(array)$r->get_param('gallery')); update_post_meta($id,'gallery', $g ); }
                        if(null !== $r->get_param('meta_title')){ $v=sanitize_text_field($r->get_param('meta_title')); if($v!=='') update_post_meta($id,'meta_title',$v); else delete_post_meta($id,'meta_title'); }
                        if(null !== $r->get_param('meta_description')){ $v=sanitize_textarea_field($r->get_param('meta_description')); if($v!=='') update_post_meta($id,'meta_description',$v); else delete_post_meta($id,'meta_description'); }
                        if(null !== $r->get_param('is_featured')) update_post_meta($id,'is_featured', (bool)$r->get_param('is_featured') ? 1 : 0 );
                        if(null !== $r->get_param('visibility')){ $v=sanitize_text_field($r->get_param('visibility')); update_post_meta($id,'visibility',$v); }
                        if(null !== $r->get_param('archived')) update_post_meta($id,'archived', (bool)$r->get_param('archived') ? 1 : 0 );
                        if(null !== $r->get_param('approved')) update_post_meta($id,'approved', (bool)$r->get_param('approved') ? 1 : 0 );
                        if(null !== $r->get_param('attributes')) update_post_meta($id,'attributes', (array)$r->get_param('attributes') );
                        if(null !== $r->get_param('variations')) update_post_meta($id,'variations', (array)$r->get_param('variations') );
                        return [ 'id' => $id ];
                    }
                ],
                [
                    'methods' => 'DELETE','permission_callback' => 'svntex2_api_can_manage',
                    'callback' => function( WP_REST_Request $r ){
                        $id = (int) $r['id']; if(!$id) return new WP_REST_Response(['error'=>'Invalid id'],400);
                        $res = wp_delete_post($id, true);
                        return [ 'deleted' => (bool)$res ];
                    }
                ],
            ]);

            // Reviews moderation
            register_rest_route('svntex2/v1','/products/(?P<id>\d+)/reviews', [
                [ 'methods'=>'GET','permission_callback'=>'svntex2_api_can_manage',
                  'callback'=>function(WP_REST_Request $r){
                      $pid=(int)$r['id']; $comments = get_comments([ 'post_id'=>$pid, 'status'=>'all', 'number'=>100 ]);
                      $items=[]; foreach($comments as $c){ $items[]=[ 'id'=>$c->comment_ID, 'author'=>$c->comment_author, 'content'=>$c->comment_content, 'status'=>$c->comment_approved, 'rating'=>get_comment_meta($c->comment_ID,'rating',true), 'featured'=>(bool)get_comment_meta($c->comment_ID,'featured',true), 'date'=>$c->comment_date ]; }
                      return [ 'items'=>$items ];
                  }],
                [ 'methods'=>'POST','permission_callback'=>'svntex2_api_can_manage',
                  'callback'=>function(WP_REST_Request $r){
                      $cid=(int)$r->get_param('comment_id'); $action=sanitize_key($r->get_param('action'));
                      if(!$cid||!$action) return new WP_REST_Response(['error'=>'comment_id and action required'],400);
                      if($action==='approve') wp_set_comment_status($cid, 'approve');
                      elseif($action==='hold') wp_set_comment_status($cid, 'hold');
                      elseif($action==='trash') wp_trash_comment($cid);
                      elseif($action==='feature'){ update_comment_meta($cid,'featured',1); }
                      elseif($action==='unfeature'){ delete_comment_meta($cid,'featured'); }
                      else return new WP_REST_Response(['error'=>'invalid action'],400);
                      return [ 'ok'=>true ];
                  }]
            ]);

            // Bulk tools and Analytics
            register_rest_route('svntex2/v1','/products/export', [
                'methods'=>'GET','permission_callback'=>'svntex2_api_can_manage',
                'callback'=>function(){
                    $q = new WP_Query([ 'post_type'=>'svntex_product','posts_per_page'=>-1 ]);
                    $out = [ 'id,title,sku,base_price,discount_price,tax_percent,stock_qty,stock_status,status' ];
                    while($q->have_posts()){ $q->the_post(); $p=get_post();
                        $row = [
                            $p->ID,
                            '"'.str_replace('"','""',$p->post_title).'"',
                            get_post_meta($p->ID,'sku',true),
                            get_post_meta($p->ID,'base_price',true),
                            get_post_meta($p->ID,'discount_price',true),
                            get_post_meta($p->ID,'tax_percent',true),
                            get_post_meta($p->ID,'stock_qty',true),
                            get_post_meta($p->ID,'stock_status',true),
                            $p->post_status,
                        ];
                        $out[] = implode(',', array_map(function($v){ return is_numeric($v)?$v:(''.str_replace(["\n","\r"],' ',$v)); }, $row));
                    }
                    wp_reset_postdata();
                    return new WP_REST_Response( implode("\n", $out ), 200, [ 'Content-Type'=>'text/csv' ] );
                }
            ]);
            register_rest_route('svntex2/v1','/products/import', [
                'methods'=>'POST','permission_callback'=>'svntex2_api_can_manage',
                'callback'=>function(WP_REST_Request $r){
                    $csv = (string) $r->get_param('csv'); if(!$csv) return new WP_REST_Response(['error'=>'csv required'],400);
                    $lines = preg_split('/\r?\n/',$csv); array_shift($lines);
                    $imported=0; foreach($lines as $line){ if(!trim($line)) continue; $cols = str_getcsv($line);
                        list($id,$title,$sku,$base,$disc,$tax,$qty,$sstatus,$status) = array_pad($cols,9,null);
                        $title = trim($title,'"');
                        if($id){ wp_update_post([ 'ID'=>(int)$id, 'post_title'=>$title, 'post_status'=> $status?:'publish' ]); }
                        else { $id = wp_insert_post([ 'post_type'=>'svntex_product','post_title'=>$title,'post_status'=>$status?:'publish' ]); }
                        update_post_meta($id,'sku', sanitize_text_field($sku));
                        update_post_meta($id,'base_price', (float)$base);
                        update_post_meta($id,'discount_price', (float)$disc);
                        update_post_meta($id,'tax_percent', (float)$tax);
                        update_post_meta($id,'stock_qty', (int)$qty);
                        update_post_meta($id,'stock_status', sanitize_text_field($sstatus));
                        $imported++;
                    }
                    return [ 'imported'=>$imported ];
                }
            ]);
            register_rest_route('svntex2/v1','/products/bulk-update', [
                'methods'=>'POST','permission_callback'=>'svntex2_api_can_manage',
                'callback'=>function(WP_REST_Request $r){
                    $ids = array_map('intval', (array)$r->get_param('ids'));
                    $updates = (array)$r->get_param('updates'); if(empty($ids) || empty($updates)) return new WP_REST_Response(['error'=>'ids and updates required'],400);
                    foreach($ids as $id){
                        if(isset($updates['status'])){ wp_update_post([ 'ID'=>$id, 'post_status'=> $updates['status']=='draft'?'draft':'publish' ]); }
                        foreach(['base_price','discount_price','tax_percent','stock_qty'] as $k){ if(isset($updates[$k])) update_post_meta($id,$k, $k==='stock_qty' ? (int)$updates[$k] : (float)$updates[$k]); }
                        if(isset($updates['stock_status'])) update_post_meta($id,'stock_status', sanitize_text_field($updates['stock_status']));
                    }
                    return [ 'updated'=>count($ids) ];
                }
            ]);
            register_rest_route('svntex2/v1','/products/(?P<id>\d+)/analytics', [
                'methods'=>'GET','permission_callback'=>'svntex2_api_can_manage',
                'callback'=>function(WP_REST_Request $r){
                    $id=(int)$r['id'];
                    $views = (int) get_post_meta($id,'view_count', true);
                    $sales = (int) get_post_meta($id,'sales_count', true);
                    $returns = (int) get_post_meta($id,'returns_count', true);
                    $conv = $views>0 ? round(($sales/$views)*100,2) : 0.0;
                    return [ 'views'=>$views,'sales'=>$sales,'returns'=>$returns,'conversion_rate'=>$conv ];
                }
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
                    'methods' => 'GET','permission_callback' => 'svntex2_api_can_manage_users',
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
                [ 'methods' => 'GET','permission_callback' => 'svntex2_api_can_manage_users',
                  'callback' => function( WP_REST_Request $r ){
                      $u = get_user_by('id', (int)$r['id']); if(!$u) return new WP_REST_Response(['error'=>'Not found'],404);
                      return svntex2_format_user_for_admin($u);
                  }
                ],
                [ 'methods' => 'POST','permission_callback' => 'svntex2_api_can_manage_users',
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
                      $meta_keys = ['mobile','gender','dob','age','employee_id','referral_source'];
                      foreach($meta_keys as $mk){ if( null !== $r->get_param($mk) ) update_user_meta($id,$mk, sanitize_text_field($r->get_param($mk)) ); }
                      return svntex2_format_user_for_admin( get_user_by('id',$id) );
                  }
                ],
            ]);
        });
        ?>
                return [ 'id' => $id ];
