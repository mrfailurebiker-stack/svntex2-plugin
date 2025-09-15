<?php
if (!defined('ABSPATH')) exit;

/**
 * Lightweight commerce layer (cart + orders) independent of Woo UI.
 * - Cart stored in user meta (logged in) or transient (guest, keyed by session cookie)
 * - REST endpoints for AJAX add/remove/update, checkout, and order retrieval
 */

// Ensure session cookie for guests (moved to send_headers to avoid "headers already sent" warnings)
function svntex2_ensure_guest_session_cookie(){
    if ( is_user_logged_in() ) return;
    if ( isset($_COOKIE['svntex2_sid']) && $_COOKIE['svntex2_sid'] !== '' ) return;
    $sid = wp_generate_uuid4();
    if ( ! headers_sent() ) {
        setcookie('svntex2_sid', $sid, time()+DAY_IN_SECONDS*7, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
    } else {
        // Fallback: defer via JS if headers already sent (edge case)
        add_action('wp_footer', function() use ($sid){
            echo '<script>if(!document.cookie.match(/(^|; )svntex2_sid=/)){document.cookie="svntex2_sid='.esc_js($sid).';path=/;max-age='.(DAY_IN_SECONDS*7).'"}</script>';
        });
    }
    $_COOKIE['svntex2_sid'] = $sid; // make available this request
}
add_action('send_headers','svntex2_ensure_guest_session_cookie');

/**
 * Public (guest-friendly) rolling nonce used to mitigate CSRF / blind POST abuse.
 * - Derived from session cookie (guest) or user id (logged in) + current hour.
 * - Accepts current hour and previous hour to reduce clock boundary failures.
 * - Not a replacement for auth; just lightweight hardening for open cart endpoints.
 */
function svntex2_public_nonce_source_key(){
    if ( is_user_logged_in() ) return 'u:'.get_current_user_id();
    $sid = $_COOKIE['svntex2_sid'] ?? '';
    if ( ! $sid ) { // guarantee a sid for guests
        $sid = wp_generate_uuid4();
        setcookie('svntex2_sid', $sid, time()+DAY_IN_SECONDS*7, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE['svntex2_sid'] = $sid;
    }
    return 'g:'.$sid;
}
function svntex2_public_nonce(){
    $key = svntex2_public_nonce_source_key();
    $hour = gmdate('Y-m-d-H');
    return substr( wp_hash( 'svntex2|'.$key.'|'.$hour ), 0, 16 );
}
function svntex2_verify_public_nonce( $nonce ){
    if ( ! $nonce ) return false;
    $key = svntex2_public_nonce_source_key();
    $hours = [ gmdate('Y-m-d-H'), gmdate('Y-m-d-H', time()-3600) ];
    foreach ( $hours as $h ) {
        $calc = substr( wp_hash( 'svntex2|'.$key.'|'.$h ), 0, 16 );
        if ( hash_equals( $calc, $nonce ) ) return true;
    }
    return false;
}

function svntex2_commerce_get_cart_key(){
    if ( is_user_logged_in() ) return 'svntex2_cart_u_'.get_current_user_id();
    $sid = $_COOKIE['svntex2_sid'] ?? '';
    return $sid ? 'svntex2_cart_g_'.$sid : 'svntex2_cart_tmp';
}

function svntex2_commerce_get_cart(){
    $k = svntex2_commerce_get_cart_key();
    $raw = get_transient($k);
    if (!$raw && is_user_logged_in()) { $raw = get_user_meta(get_current_user_id(),'svntex2_cart', true); }
    $cart = is_array($raw)? $raw : [];
    // Normalize lines: key productID:variantID (variant optional)
    return $cart;
}

function svntex2_commerce_save_cart($cart){
    $k = svntex2_commerce_get_cart_key();
    set_transient($k, $cart, 12 * HOUR_IN_SECONDS);
    if ( is_user_logged_in() ) update_user_meta(get_current_user_id(),'svntex2_cart',$cart);
}

function svntex2_commerce_cart_add($product_id,$variant_id,$qty){
    $cart = svntex2_commerce_get_cart();
    $key = $product_id.':'.(int)$variant_id;
    $cart[$key] = ($cart[$key] ?? 0) + max(1,(int)$qty);
    svntex2_commerce_save_cart($cart);
    return $cart;
}

function svntex2_commerce_cart_remove($product_id,$variant_id){
    $cart = svntex2_commerce_get_cart();
    $key = $product_id.':'.(int)$variant_id;
    unset($cart[$key]);
    svntex2_commerce_save_cart($cart);
    return $cart;
}

function svntex2_commerce_cart_update($product_id,$variant_id,$qty){
    $cart = svntex2_commerce_get_cart();
    $key = $product_id.':'.(int)$variant_id;
    if ($qty <= 0) unset($cart[$key]); else $cart[$key] = (int)$qty;
    svntex2_commerce_save_cart($cart);
    return $cart;
}

function svntex2_commerce_cart_totals(){
    global $wpdb; $vt=$wpdb->prefix.'svntex_product_variants';
    $cart = svntex2_commerce_get_cart();
    $lines=[]; $items_total=0; $delivery_total=0;
    foreach($cart as $k=>$q){ list($pid,$vid)=array_map('intval',explode(':',$k));
        $price = $vid ? $wpdb->get_var($wpdb->prepare("SELECT price FROM $vt WHERE id=%d", $vid)) : null;
        if ($price===null){ // fallback to first variant
            $price = $wpdb->get_var($wpdb->prepare("SELECT price FROM $vt WHERE product_id=%d ORDER BY id ASC LIMIT 1", $pid));
        }
        $price = $price!==null ? (float)$price : 0;
        $qty = max(1,(int)$q);
        $subtotal = $price * $qty; $items_total += $subtotal;
        // Delivery fee per line using delivery rules helper (graceful if function missing)
    $fee = function_exists('svntex2_delivery_compute') ? svntex2_delivery_compute($pid, $subtotal, ($vid ?: null)) : 0.0;
        $delivery_total += $fee;
        $lines[] = [ 'product_id'=>$pid,'variant_id'=>$vid,'qty'=>$qty,'price'=>$price,'subtotal'=>round($subtotal,2),'delivery'=>round($fee,2) ];
    }
    $grand = $items_total + $delivery_total;
    return [ 'lines'=>$lines,'items_total'=>round($items_total,2),'delivery_total'=>round($delivery_total,2),'grand_total'=>round($grand,2) ];
}

function svntex2_commerce_checkout($address, $payment = []){
    global $wpdb; $orders=$wpdb->prefix.'svntex_orders'; $itemsT=$wpdb->prefix.'svntex_order_items';
    $totals = svntex2_commerce_cart_totals();
    if (empty($totals['lines'])) return new WP_Error('empty_cart','Cart empty');
    // Build variant -> qty map for stock enforcement (skip lines with no variant)
    $variant_qty = [];
    foreach($totals['lines'] as $ln){ if($ln['variant_id']){ $variant_qty[$ln['variant_id']] = ($variant_qty[$ln['variant_id']] ?? 0) + (int)$ln['qty']; } }
    if(!empty($variant_qty) && function_exists('svntex2_inventory_batch_decrement')){
        $dec = svntex2_inventory_batch_decrement($variant_qty);
        if(is_wp_error($dec)) return $dec; // do not proceed, stock insufficient
    }
    $meta = [ 'payment' => $payment ];
    $wpdb->insert($orders,[
        'user_id'=> is_user_logged_in()? get_current_user_id(): null,
        'status'=>'pending',
        'items_total'=>$totals['items_total'],
        'delivery_total'=>$totals['delivery_total'],
        'grand_total'=>$totals['grand_total'],
        'address'=> wp_json_encode($address),
        'meta'=> wp_json_encode($meta),
    ]);
    $order_id = (int)$wpdb->insert_id;
    foreach($totals['lines'] as $ln){
        $wpdb->insert($itemsT,[
            'order_id'=>$order_id,
            'product_id'=>$ln['product_id'],
            'variant_id'=>$ln['variant_id'],
            'qty'=>$ln['qty'],
            'price'=>$ln['price'],
            'subtotal'=>$ln['subtotal'],
            'meta'=> null,
        ]);
    }
    // Clear cart
    svntex2_commerce_save_cart([]);
    return [ 'order_id'=>$order_id,'status'=>'pending','totals'=>$totals ];
}

// REST endpoints
add_action('rest_api_init', function(){
    register_rest_route('svntex2/v1','/cart', [ [ 'methods'=>'GET','callback'=>function(){ return svntex2_commerce_cart_totals(); },'permission_callback'=>'__return_true' ] ]); // read-only can stay public
    // Public nonce endpoint so static apps can call cart/checkout endpoints
    register_rest_route('svntex2/v1','/public-nonce', [ [ 'methods'=>'GET','callback'=>function(){ return [ 'nonce' => svntex2_public_nonce() ]; },'permission_callback'=>'__return_true' ] ]);
    $secure_cb = function( $request ){ $nonce = $request->get_header('X-SVNTeX2-Nonce'); if ( ! $nonce ) $nonce = $request['nonce'] ?? ''; if ( svntex2_verify_public_nonce( $nonce ) ) return true; return new WP_Error('forbidden','Invalid nonce',['status'=>403]); };
    register_rest_route('svntex2/v1','/cart/add', [ [ 'methods'=>'POST','callback'=>function($r){
        $pid=(int)$r['product_id']; $vid= isset($r['variant_id'])?(int)$r['variant_id']:0; $qty=max(1,(int)($r['qty']??1));
        svntex2_commerce_cart_add($pid,$vid,$qty); return svntex2_commerce_cart_totals();
    },'permission_callback'=>$secure_cb ] ]);
    register_rest_route('svntex2/v1','/cart/update', [ [ 'methods'=>'POST','callback'=>function($r){
        $pid=(int)$r['product_id']; $vid=(int)($r['variant_id']??0); $qty=(int)($r['qty']??0);
        svntex2_commerce_cart_update($pid,$vid,$qty); return svntex2_commerce_cart_totals();
    },'permission_callback'=>$secure_cb ] ]);
    register_rest_route('svntex2/v1','/cart/remove', [ [ 'methods'=>'POST','callback'=>function($r){
        $pid=(int)$r['product_id']; $vid=(int)($r['variant_id']??0);
        svntex2_commerce_cart_remove($pid,$vid); return svntex2_commerce_cart_totals();
    },'permission_callback'=>$secure_cb ] ]);
    register_rest_route('svntex2/v1','/checkout', [ [ 'methods'=>'POST','callback'=>function($r){
        $address = [
            'name'=>sanitize_text_field($r['name']??''),
            'line1'=>sanitize_text_field($r['line1']??''),
            'line2'=>sanitize_text_field($r['line2']??''),
            'city'=>sanitize_text_field($r['city']??''),
            'state'=>sanitize_text_field($r['state']??''),
            'zip'=>sanitize_text_field($r['zip']??''),
            'country'=>sanitize_text_field($r['country']??'') ?: 'IN',
            'phone'=>sanitize_text_field($r['phone']??''),
        ];
        if(!$address['name'] || !$address['line1'] || !$address['city'] || !$address['zip']) return new WP_Error('bad_address','Missing required address fields', ['status'=>400]);
        $payment = [ 'method' => sanitize_key($r['payment_method'] ?? 'cod') ];
        // Optional wallet immediate capture (deduct up to grand total)
        if ($payment['method'] === 'wallet' && is_user_logged_in()) {
            $totals = svntex2_commerce_cart_totals();
            $uid = get_current_user_id();
            $need = (float)$totals['grand_total'];
            if ( function_exists('svntex2_wallet_get_balance') && function_exists('svntex2_wallet_add_transaction') ) {
                $bal = (float) svntex2_wallet_get_balance($uid);
                if ($bal < $need) return new WP_Error('insufficient_wallet','Insufficient wallet balance', ['status'=>400]);
                svntex2_wallet_add_transaction($uid, 'order_payment', -1 * $need, 'checkout:'.uniqid(), [ 'note'=>'Wallet pay' ], 'order_payment' );
                $payment['captured'] = $need;
            }
        }
        return svntex2_commerce_checkout($address, $payment);
    },'permission_callback'=>$secure_cb ] ]);
    register_rest_route('svntex2/v1','/order/(?P<id>\d+)', [ [ 'methods'=>'GET','callback'=>function($r){
        global $wpdb; $oid=(int)$r['id']; if(!$oid) return new WP_Error('bad_request','id required',['status'=>400]);
        $orders=$wpdb->prefix.'svntex_orders'; $itemsT=$wpdb->prefix.'svntex_order_items';
        $ord = $wpdb->get_row($wpdb->prepare("SELECT * FROM $orders WHERE id=%d", $oid)); if(!$ord) return new WP_Error('not_found','Order not found',['status'=>404]);
        // Basic ownership check (if order has user_id ensure current matches) – guests open
        if ($ord->user_id && (int)$ord->user_id !== get_current_user_id() && ! current_user_can('manage_options')) return new WP_Error('forbidden','Not allowed',['status'=>403]);
        if ( ! $ord->user_id && ! current_user_can('manage_options') ) return new WP_Error('forbidden','Guest order lookup disabled',['status'=>403]);
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $itemsT WHERE order_id=%d", $oid));
        return [
            'id'=>(int)$ord->id,
            'status'=>$ord->status,
            'items_total'=>(float)$ord->items_total,
            'delivery_total'=>(float)$ord->delivery_total,
            'grand_total'=>(float)$ord->grand_total,
            'address'=> $ord->address ? json_decode($ord->address,true):null,
            'items'=> array_map(function($it){ return [ 'product_id'=>(int)$it->product_id,'variant_id'=>(int)$it->variant_id,'qty'=>(int)$it->qty,'price'=>(float)$it->price,'subtotal'=>(float)$it->subtotal ]; }, $items ?: [] )
        ];
    },'permission_callback'=>$secure_cb ] ]);
});

?>
