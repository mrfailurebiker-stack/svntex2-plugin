<?php
if (!defined('ABSPATH')) exit;

// 1) CPT + taxonomies
add_action('init', function(){
    register_post_type('svntex_product', [
        'label' => 'Products',
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'rewrite' => [ 'slug' => 'products' ],
        'show_ui' => true,
        'menu_icon' => 'dashicons-products',
        'supports' => ['title','editor','thumbnail','excerpt'],
    ]);
    register_taxonomy('svntex_category','svntex_product',[ 'label'=>'Product Categories','hierarchical'=>true,'show_ui'=>true ]);
    register_taxonomy('svntex_collection','svntex_product',[ 'label'=>'Collections','hierarchical'=>false,'show_ui'=>true ]);
    register_taxonomy('svntex_tag','svntex_product',[ 'label'=>'Tags','hierarchical'=>false,'show_ui'=>true ]);
});

// 2) Product ID generator SVN0001 etc.
function svntex2_generate_product_id(): string {
    $base = (int) get_option('svntex2_product_seq', 0) + 1;
    update_option('svntex2_product_seq', $base);
    return 'SVN' . str_pad((string)$base, 4, '0', STR_PAD_LEFT);
}

// 3) Admin meta boxes
add_action('add_meta_boxes', function(){
    add_meta_box('svntex_product_core','Product Details','svntex2_mb_product_core','svntex_product','normal','high');
});

function svntex2_mb_product_core($post){
    $pid = get_post_meta($post->ID,'product_id', true);
    if(!$pid){ $pid = svntex2_generate_product_id(); update_post_meta($post->ID,'product_id',$pid); }
    $unit = get_post_meta($post->ID,'unit', true);
    $tax  = get_post_meta($post->ID,'tax_class', true);
    $margin = (float) get_post_meta($post->ID,'profit_margin', true);
    echo '<p><strong>Product ID:</strong> '.esc_html($pid).'</p>';
    echo '<label>Unit <select name="svntex_unit"><option value="pieces" '.selected($unit,'pieces',false).'>Pieces</option><option value="kg" '.selected($unit,'kg',false).'>KG</option><option value="litres" '.selected($unit,'litres',false).'>Litres</option></select></label> ';
    echo '<label style="margin-left:12px">Tax Class <select name="svntex_tax"><option value="GST" '.selected($tax,'GST',false).'>GST</option><option value="VAT" '.selected($tax,'VAT',false).'>VAT</option></select></label> ';
    echo '<label style="margin-left:12px">Profit Margin % <input type="number" step="0.01" name="svntex_margin" value="'.esc_attr($margin).'" class="small-text" /></label>';
}

add_action('save_post_svntex_product', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    // Ensure product_id exists even for REST-created posts
    if (!get_post_meta($post_id,'product_id', true)) {
        update_post_meta($post_id,'product_id', svntex2_generate_product_id());
    }
    if (isset($_POST['svntex_unit'])) update_post_meta($post_id,'unit', sanitize_text_field($_POST['svntex_unit']));
    if (isset($_POST['svntex_tax'])) update_post_meta($post_id,'tax_class', sanitize_text_field($_POST['svntex_tax']));
    if (isset($_POST['svntex_margin'])) update_post_meta($post_id,'profit_margin', (float) $_POST['svntex_margin']);
});

// 4) Variants + inventory helpers (DB layer)
function svntex2_variant_upsert($product_id, $sku, $args=[]){
    global $wpdb; $t=$wpdb->prefix.'svntex_product_variants';
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE sku=%s", $sku));
    $data = [
        'product_id' => (int)$product_id,
        'sku' => $sku,
        'attributes' => isset($args['attributes']) ? wp_json_encode($args['attributes']) : null,
        'price' => isset($args['price']) ? (float)$args['price'] : null,
        'tax_class' => $args['tax_class'] ?? null,
        'unit' => $args['unit'] ?? null,
        'active' => isset($args['active']) ? (int)$args['active'] : 1,
    ];
    if($row){ $wpdb->update($t,$data,['id'=>$row->id]); return (int)$row->id; }
    $wpdb->insert($t,$data); return (int)$wpdb->insert_id;
}

function svntex2_inventory_set($variant_id,$location_code,$qty,$opts=[]){
    global $wpdb; $locT=$wpdb->prefix.'svntex_inventory_locations'; $stkT=$wpdb->prefix.'svntex_inventory_stocks';
    $loc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $locT WHERE code=%s", $location_code));
    if(!$loc){ $wpdb->insert($locT,[ 'code'=>$location_code, 'name'=>$location_code, 'active'=>1 ]); $loc = (object)['id'=>$wpdb->insert_id]; }
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stkT WHERE variant_id=%d AND location_id=%d", $variant_id, $loc->id));
    $data = [ 'variant_id'=>$variant_id,'location_id'=>$loc->id,'qty'=>(int)$qty,
        'min_qty'=>(int)($opts['min_qty'] ?? 0),'max_qty'=>isset($opts['max_qty'])?(int)$opts['max_qty']:null,
        'reorder_threshold'=>isset($opts['reorder_threshold'])?(int)$opts['reorder_threshold']:null,
        'backorder_enabled'=>!empty($opts['backorder_enabled'])?1:0 ];
    if($row){ $wpdb->update($stkT,$data,['id'=>$row->id]); return (int)$row->id; }
    $wpdb->insert($stkT,$data); return (int)$wpdb->insert_id;
}

function svntex2_inventory_get_total($variant_id){
    global $wpdb; $stkT=$wpdb->prefix.'svntex_inventory_stocks';
    $sum = (int)$wpdb->get_var($wpdb->prepare("SELECT SUM(qty) FROM $stkT WHERE variant_id=%d", $variant_id));
    return $sum ?: 0;
}

function svntex2_inventory_get_rows($variant_id){
    global $wpdb; $stkT=$wpdb->prefix.'svntex_inventory_stocks'; $locT=$wpdb->prefix.'svntex_inventory_locations';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT s.id,s.qty,s.min_qty,s.max_qty,s.reorder_threshold,s.backorder_enabled,l.code,l.name,l.active FROM $stkT s JOIN $locT l ON l.id=s.location_id WHERE s.variant_id=%d ORDER BY l.code ASC", $variant_id));
    return array_map(function($r){ return [
        'id'=>(int)$r->id,'qty'=>(int)$r->qty,'min_qty'=>(int)$r->min_qty,'max_qty'=> is_null($r->max_qty)?null:(int)$r->max_qty,
        'reorder_threshold'=> is_null($r->reorder_threshold)?null:(int)$r->reorder_threshold,
        'backorder_enabled'=> (int)$r->backorder_enabled,
        'location'=>['code'=>$r->code,'name'=>$r->name,'active'=>(int)$r->active],
    ]; }, $rows ?: []);
}

// 5) Delivery rule resolver
function svntex2_delivery_compute($product_id, $variant_id=null, $subtotal){
    global $wpdb; $t=$wpdb->prefix.'svntex_delivery_rules';
    $get = function($scope,$id){
        global $wpdb; $t=$wpdb->prefix.'svntex_delivery_rules';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE active=1 AND scope=%s AND scope_id=%d ORDER BY id DESC LIMIT 1", $scope,$id));
    };
    $rule = $variant_id ? $get('variant',(int)$variant_id) : null;
    if(!$rule){ $rule = $get('product',(int)$product_id); }
    if(!$rule){ $rule = $get('global',0); if(!$rule) return 0.0; }
    if ($rule->free_threshold !== null && $subtotal >= (float)$rule->free_threshold) return 0.0;
    if ($rule->mode === 'fixed') return (float)$rule->amount;
    return round(((float)$rule->amount/100.0) * (float)$subtotal, 2);
}

function svntex2_delivery_upsert($scope,$scope_id,$data){
    global $wpdb; $t=$wpdb->prefix.'svntex_delivery_rules';
    $scope = in_array($scope,['global','product','variant'],true) ? $scope : 'global';
    $sid = (int)($scope==='global'?0:$scope_id);
    $row = $wpdb->get_row($wpdb->prepare("SELECT id FROM $t WHERE scope=%s AND scope_id=%d", $scope,$sid));
    $payload = [
        'scope'=>$scope,'scope_id'=>$sid,
        'mode'=> ($data['mode'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed',
        'amount'=> isset($data['amount']) ? (float)$data['amount'] : 0,
        'free_threshold'=> isset($data['free_threshold']) ? (float)$data['free_threshold'] : null,
        'override_global'=> !empty($data['override_global']) ? 1 : 0,
        'active'=> isset($data['active']) ? (int)$data['active'] : 1,
    ];
    if($row){ $wpdb->update($t,$payload,['id'=>$row->id]); return (int)$row->id; }
    $wpdb->insert($t,$payload); return (int)$wpdb->insert_id;
}

function svntex2_delivery_get($scope,$scope_id){
    global $wpdb; $t=$wpdb->prefix.'svntex_delivery_rules';
    $scope = in_array($scope,['global','product','variant'],true) ? $scope : 'global';
    $sid = (int)($scope==='global'?0:$scope_id);
    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE scope=%s AND scope_id=%d ORDER BY id DESC LIMIT 1", $scope,$sid));
    if(!$r) return null;
    return [ 'id'=>(int)$r->id, 'scope'=>$r->scope, 'scope_id'=>(int)$r->scope_id, 'mode'=>$r->mode, 'amount'=>(float)$r->amount,
        'free_threshold'=> is_null($r->free_threshold)?null:(float)$r->free_threshold,
        'override_global'=>(int)$r->override_global, 'active'=>(int)$r->active ];
}

// 6) Shortcodes (basic list + single product)
add_shortcode('svntex_products', function($atts){
    $q = new WP_Query([ 'post_type'=>'svntex_product','posts_per_page'=>12 ]);
    ob_start();
    echo '<div class="svntex-grid">';
    while($q->have_posts()){ $q->the_post(); echo '<article class="card"><a href="'.esc_url(get_permalink()).'">'.get_the_title().'</a></article>'; }
    echo '</div>'; wp_reset_postdata();
    return ob_get_clean();
});

// 7) REST scaffolding hooks will be in rest-products.php

?>
