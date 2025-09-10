<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function(){
    register_rest_route('svntex2/v1','/products', [
        [ 'methods'=>'GET','callback'=>'svntex2_api_products_list','permission_callback'=>'__return_true' ],
        [ 'methods'=>'POST','callback'=>'svntex2_api_products_create','permission_callback'=>'svntex2_api_can_manage' ],
    ]);
    register_rest_route('svntex2/v1','/products/(?P<id>\\d+)', [
        [ 'methods'=>'GET','callback'=>'svntex2_api_products_get','permission_callback'=>'__return_true' ],
        [ 'methods'=>'POST','callback'=>'svntex2_api_products_update','permission_callback'=>'svntex2_api_can_manage' ],
        [ 'methods'=>'DELETE','callback'=>'svntex2_api_products_delete','permission_callback'=>'svntex2_api_can_manage' ],
    ]);
    register_rest_route('svntex2/v1','/variants', [
        [ 'methods'=>'POST','callback'=>'svntex2_api_variant_upsert','permission_callback'=>'svntex2_api_can_manage' ],
    ]);
    register_rest_route('svntex2/v1','/inventory', [
        [ 'methods'=>'POST','callback'=>'svntex2_api_inventory_set','permission_callback'=>'svntex2_api_can_manage' ],
    ]);
    register_rest_route('svntex2/v1','/delivery', [
        [ 'methods'=>'GET','callback'=>'svntex2_api_delivery_get','permission_callback'=>'svntex2_api_can_manage' ],
        [ 'methods'=>'POST','callback'=>'svntex2_api_delivery_upsert','permission_callback'=>'svntex2_api_can_manage' ],
    ]);
    register_rest_route('svntex2/v1','/variant/(?P<id>\d+)/inventory', [
        [ 'methods'=>'GET','callback'=>'svntex2_api_variant_inventory','permission_callback'=>'svntex2_api_can_manage' ],
    ]);
    // Stock settings (read/update)
    register_rest_route('svntex2/v1','/stock/settings', [
        [ 'methods'=>'GET','callback'=>function(){
            return [
                'low_stock_threshold'=>(int) get_option('svntex2_low_stock_threshold',5),
                'notify_enabled'=>(int) get_option('svntex2_stock_notify_enabled',1),
                'notify_emails'=> get_option('svntex2_stock_notify_emails',''),
            ];
        }, 'permission_callback'=>'svntex2_api_can_manage' ],
        [ 'methods'=>'POST','callback'=>function($r){
            if(isset($r['low_stock_threshold'])) update_option('svntex2_low_stock_threshold', max(0,(int)$r['low_stock_threshold']) );
            if(isset($r['notify_enabled'])) update_option('svntex2_stock_notify_enabled', $r['notify_enabled']?1:0 );
            if(isset($r['notify_emails'])) update_option('svntex2_stock_notify_emails', sanitize_text_field($r['notify_emails']) );
            return [ 'updated'=>true ];
        }, 'permission_callback'=>'svntex2_api_can_manage' ],
    ]);
});

function svntex2_api_can_manage(){ return current_user_can('manage_options'); }

function svntex2_api_products_list(WP_REST_Request $r){
    $q = new WP_Query([
        'post_type'=>'svntex_product',
        'posts_per_page'=> intval($r->get_param('per_page') ?: 20),
        'paged'=> intval($r->get_param('page') ?: 1),
        's'=> $r->get_param('search') ?: '',
        'tax_query'=>[],
    ]);
    $items=[]; while($q->have_posts()){ $q->the_post(); $items[] = svntex2_format_product_response(get_post()); } wp_reset_postdata();
    return new WP_REST_Response([ 'items'=>$items, 'total'=> (int)$q->found_posts ]);
}

function svntex2_api_products_create(WP_REST_Request $r){
    $title = sanitize_text_field($r['title']);
    if(!$title) return new WP_Error('bad_request','title required', ['status'=>400]);
    $post_id = wp_insert_post([ 'post_type'=>'svntex_product','post_status'=>'publish','post_title'=>$title,'post_content'=>wp_kses_post($r['description'] ?? '') ]);
    if(is_wp_error($post_id)) return $post_id;
    if(!empty($r['categories'])) wp_set_object_terms($post_id, array_map('intval',(array)$r['categories']), 'svntex_category');
    if(!empty($r['tags'])) wp_set_object_terms($post_id, array_map('intval',(array)$r['tags']), 'svntex_tag');
    $map = [ 'unit','tax_class','profit_margin','product_sku','mrp','sale_price','gst_percent','cost_price' ];
    foreach($map as $m){ if(isset($r[$m])) update_post_meta($post_id,$m, is_numeric($r[$m]) ? (float)$r[$m] : sanitize_text_field($r[$m])); }
    if(isset($r['product_bullets'])) update_post_meta($post_id,'product_bullets', array_values(array_filter(array_map('sanitize_text_field',(array)$r['product_bullets']))));
    if(isset($r['product_videos'])) update_post_meta($post_id,'product_videos', array_values(array_filter(array_map('esc_url_raw',(array)$r['product_videos']))));
    return svntex2_api_products_get(new WP_REST_Request('GET',[ 'id'=>$post_id ]));
}

function svntex2_api_products_get(WP_REST_Request $r){
    $post = get_post((int)$r['id']); if(!$post || $post->post_type!=='svntex_product') return new WP_Error('not_found','Product not found',['status'=>404]);
    return new WP_REST_Response( svntex2_format_product_response($post) );
}

function svntex2_api_products_update(WP_REST_Request $r){
    $post = get_post((int)$r['id']); if(!$post || $post->post_type!=='svntex_product') return new WP_Error('not_found','Product not found',['status'=>404]);
    $data = [ 'ID'=>$post->ID ]; if(isset($r['title'])) $data['post_title']=sanitize_text_field($r['title']); if(isset($r['description'])) $data['post_content']=wp_kses_post($r['description']);
    if(count($data)>1) wp_update_post($data);
    if(isset($r['categories'])) wp_set_object_terms($post->ID, array_map('intval',(array)$r['categories']), 'svntex_category');
    if(isset($r['tags'])) wp_set_object_terms($post->ID, array_map('intval',(array)$r['tags']), 'svntex_tag');
    $map = [ 'unit','tax_class','profit_margin','product_sku','mrp','sale_price','gst_percent','cost_price' ];
    foreach($map as $m){ if(isset($r[$m])) update_post_meta($post->ID,$m, is_numeric($r[$m]) ? (float)$r[$m] : sanitize_text_field($r[$m])); }
    if(isset($r['product_bullets'])) update_post_meta($post->ID,'product_bullets', array_values(array_filter(array_map('sanitize_text_field',(array)$r['product_bullets']))));
    if(isset($r['product_videos'])) update_post_meta($post->ID,'product_videos', array_values(array_filter(array_map('esc_url_raw',(array)$r['product_videos']))));
    return svntex2_api_products_get($r);
}

function svntex2_api_products_delete(WP_REST_Request $r){
    $post = get_post((int)$r['id']); if(!$post || $post->post_type!=='svntex_product') return new WP_Error('not_found','Product not found',['status'=>404]);
    wp_trash_post($post->ID); return new WP_REST_Response(['deleted'=>true]);
}

function svntex2_api_variant_upsert(WP_REST_Request $r){
    $product_id = (int)$r['product_id']; $sku = sanitize_text_field($r['sku']); if(!$product_id||!$sku) return new WP_Error('bad_request','product_id and sku required',['status'=>400]);
    $variant_id = svntex2_variant_upsert($product_id,$sku,[
        'attributes'=> $r['attributes'] ?? null,
        'price'=> isset($r['price']) ? (float)$r['price'] : null,
        'tax_class'=> $r['tax_class'] ?? null,
        'unit'=> $r['unit'] ?? null,
        'active'=> isset($r['active']) ? (int)$r['active'] : 1,
    ]);
    return new WP_REST_Response([ 'variant_id'=>$variant_id, 'total_stock'=> svntex2_inventory_get_total($variant_id) ]);
}

function svntex2_api_inventory_set(WP_REST_Request $r){
    $variant_id = (int)$r['variant_id']; $loc = sanitize_text_field($r['location']); $qty = (int)$r['qty'];
    if(!$variant_id || !$loc) return new WP_Error('bad_request','variant_id and location required',['status'=>400]);
    $id = svntex2_inventory_set($variant_id,$loc,$qty,[
        'min_qty'=> isset($r['min_qty'])?(int)$r['min_qty']:0,
        'max_qty'=> isset($r['max_qty'])?(int)$r['max_qty']:null,
        'reorder_threshold'=> isset($r['reorder_threshold'])?(int)$r['reorder_threshold']:null,
        'backorder_enabled'=> !empty($r['backorder_enabled'])
    ]);
    return new WP_REST_Response([ 'stock_id'=>$id, 'variant_id'=>$variant_id, 'location'=>$loc, 'qty'=>$qty ]);
}

function svntex2_api_variant_inventory(WP_REST_Request $r){
    $variant_id = (int)$r['id']; if(!$variant_id) return new WP_Error('bad_request','variant id required',['status'=>400]);
    return new WP_REST_Response([ 'variant_id'=>$variant_id, 'locations'=> svntex2_inventory_get_rows($variant_id) ]);
}

function svntex2_api_delivery_get(WP_REST_Request $r){
    $scope = sanitize_text_field($r->get_param('scope') ?: 'global');
    $sid = (int)($r->get_param('scope_id') ?: 0);
    $rule = svntex2_delivery_get($scope,$sid);
    return new WP_REST_Response($rule ?: []);
}

function svntex2_api_delivery_upsert(WP_REST_Request $r){
    $scope = sanitize_text_field($r->get_param('scope') ?: 'global');
    $sid = (int)($r->get_param('scope_id') ?: 0);
    $id = svntex2_delivery_upsert($scope,$sid,[
        'mode'=>$r['mode'] ?? 'fixed',
        'amount'=> isset($r['amount']) ? (float)$r['amount'] : 0,
        'free_threshold'=> isset($r['free_threshold']) ? (float)$r['free_threshold'] : null,
        'override_global'=> !empty($r['override_global']),
        'active'=> isset($r['active']) ? (int)$r['active'] : 1,
    ]);
    return new WP_REST_Response([ 'id'=>$id ]);
}

// Formatter
function svntex2_format_product_response(WP_Post $post){
    $meta = [
        'product_id'=> get_post_meta($post->ID,'product_id', true),
        'unit'=> get_post_meta($post->ID,'unit', true),
        'tax_class'=> get_post_meta($post->ID,'tax_class', true),
    // Profit margin: hide from non-managers in public responses
    'profit_margin'=> current_user_can('manage_options') ? (float) get_post_meta($post->ID,'profit_margin', true) : null,
        'sku'=> get_post_meta($post->ID,'product_sku', true),
        'mrp'=> (float) get_post_meta($post->ID,'mrp', true),
        'sale_price'=> (float) get_post_meta($post->ID,'sale_price', true),
        'gst_percent'=> (float) get_post_meta($post->ID,'gst_percent', true),
        // cost_price intentionally omitted from public meta block
    ];
    $bullets = (array) get_post_meta($post->ID,'product_bullets', true); if(!$bullets) $bullets=[];
    $videos = (array) get_post_meta($post->ID,'product_videos', true); if(!$videos) $videos=[];
    $gallery = (array) get_post_meta($post->ID,'product_gallery', true); if(!$gallery) $gallery=[];
    $terms = [
        'categories'=> wp_get_object_terms($post->ID,'svntex_category',[ 'fields'=>'ids' ]),
        'tags'=> wp_get_object_terms($post->ID,'svntex_tag',[ 'fields'=>'ids' ]),
    ];
    global $wpdb; $vt=$wpdb->prefix.'svntex_product_variants'; $var = $wpdb->get_results($wpdb->prepare("SELECT id,sku,attributes,price,tax_class,unit,active FROM $vt WHERE product_id=%d ORDER BY id ASC", $post->ID));
    $variants = array_map(function($r){ return [
        'id'=>(int)$r->id, 'sku'=>$r->sku, 'attributes'=> $r->attributes? json_decode($r->attributes,true):null,
        'price'=> is_null($r->price)? null : (float)$r->price, 'tax_class'=>$r->tax_class, 'unit'=>$r->unit,
        'active'=> (int)$r->active,
    ]; }, $var ?: []);
    // Price range (active variants with price)
    $range = svntex2_get_variant_price_range($post->ID);
    $price_range = null;
    if ($range['min'] !== null) {
        if ($range['min'] === $range['max']) {
            $price_range = [ 'type'=>'single', 'display'=>$range['min'] ];
        } else {
            $price_range = [ 'type'=>'range', 'display_min'=>$range['min'], 'display_max'=>$range['max'] ];
        }
    }
    // Stock status (manual) + quick aggregate (admin only aggregate across variants)
    $stock_status = get_post_meta($post->ID,'stock_status', true) ?: 'in_stock';
    $total_stock = null;
    if(current_user_can('manage_options')){
        global $wpdb; $vt=$wpdb->prefix.'svntex_product_variants'; $stkT=$wpdb->prefix.'svntex_inventory_stocks';
        $total_stock = (int)$wpdb->get_var($wpdb->prepare("SELECT SUM(s.qty) FROM $vt v LEFT JOIN $stkT s ON s.variant_id=v.id WHERE v.product_id=%d", $post->ID));
        if(!$total_stock) $total_stock = 0;
    }
    $base = [
        'id'=>$post->ID,
        'title'=>$post->post_title,
        'description'=> apply_filters('the_content',$post->post_content),
        'meta'=>$meta,
    'bullets'=>$bullets,
    'media'=> [ 'gallery'=>$gallery, 'videos'=>$videos ],
        'terms'=>$terms,
        'variants'=>$variants,
        'price_range'=>$price_range,
        'permalink'=> get_permalink($post),
        'stock_status'=>$stock_status,
        'total_stock'=> $total_stock,
    ];
    // Allow extensions (vendors etc.) to append extra structured data
    $extra = apply_filters('svntex2_product_formatter_extra', [], $post);
    if(is_array($extra) && $extra){ $base = array_merge($base,$extra); }
    return $base;
}

?>
