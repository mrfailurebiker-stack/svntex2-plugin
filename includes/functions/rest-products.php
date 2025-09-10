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
    if(isset($r['unit'])) update_post_meta($post_id,'unit', sanitize_text_field($r['unit']));
    if(isset($r['tax_class'])) update_post_meta($post_id,'tax_class', sanitize_text_field($r['tax_class']));
    if(isset($r['profit_margin'])) update_post_meta($post_id,'profit_margin', (float)$r['profit_margin']);
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
    foreach(['unit','tax_class'] as $k){ if(isset($r[$k])) update_post_meta($post->ID,$k, sanitize_text_field($r[$k])); }
    if(isset($r['profit_margin'])) update_post_meta($post->ID,'profit_margin', (float)$r['profit_margin']);
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
        'profit_margin'=> (float) get_post_meta($post->ID,'profit_margin', true),
    ];
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
    return [
        'id'=>$post->ID,
        'title'=>$post->post_title,
        'description'=> apply_filters('the_content',$post->post_content),
        'meta'=>$meta,
        'terms'=>$terms,
        'variants'=>$variants,
        'price_range'=>$price_range,
        'permalink'=> get_permalink($post),
    ];
}

?>
