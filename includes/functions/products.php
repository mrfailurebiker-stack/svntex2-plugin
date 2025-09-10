<?php
if (!defined('ABSPATH')) exit;

// 1) CPT + taxonomies
add_action('init', function(){
    register_post_type('svntex_product', [
        'labels' => [
            'name' => 'SVNTeX Products',
            'singular_name' => 'SVNTeX Product',
            'menu_name' => 'SVNTeX Products',
        ],
        'public' => true,
        'has_archive' => true,
        'show_in_rest' => true,
        'rewrite' => [ 'slug' => 'products' ],
        'show_ui' => true,
        'show_in_menu' => false, // Hide from top-level menu
        'menu_icon' => 'dashicons-products',
    'supports' => ['title','editor','thumbnail','excerpt'],
    'capability_type' => 'post',
    'show_in_admin_bar' => true,
    ]);
    register_taxonomy('svntex_category','svntex_product',[ 'label'=>'Product Categories','hierarchical'=>true,'show_ui'=>true,'show_in_rest'=>true ]);
    register_taxonomy('svntex_collection','svntex_product',[ 'label'=>'Collections','hierarchical'=>false,'show_ui'=>true,'show_in_rest'=>true ]);
    register_taxonomy('svntex_tag','svntex_product',[ 'label'=>'Tags','hierarchical'=>false,'show_ui'=>true,'show_in_rest'=>true ]);
});

// Hide default WooCommerce products menu
add_action('admin_menu', function(){
    remove_menu_page('edit.php?post_type=product');
}, 999);

// One-time admin notice to surface where the new menu is
add_action('admin_init', function(){ if(!get_option('svntex2_products_notice_dismissed')) update_option('svntex2_products_notice_dismissed', time()); });
add_action('admin_notices', function(){
    if ( ! current_user_can('manage_options') ) return;
    $ts = (int) get_option('svntex2_products_notice_dismissed', 0);
    // Show only within 2 hours of first load after update
    if ( $ts && ( time() - $ts ) < 7200 ) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>SVNTeX Products</strong> module is active. Find it under <em>SVNTeX Products</em> in the left admin menu. REST API at <code>/wp-json/svntex2/v1</code>.</p></div>';
    }
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
    add_meta_box('svntex_product_media','Media & Presentation','svntex2_mb_product_media','svntex_product','normal','default');
});

function svntex2_mb_product_core($post){
    $pid = get_post_meta($post->ID,'product_id', true);
    if(!$pid){ $pid = svntex2_generate_product_id(); update_post_meta($post->ID,'product_id',$pid); }
    $unit = get_post_meta($post->ID,'unit', true);
    $tax  = get_post_meta($post->ID,'tax_class', true);
    $margin = (float) get_post_meta($post->ID,'profit_margin', true);
    $sku = get_post_meta($post->ID,'product_sku', true);
    $mrp = get_post_meta($post->ID,'mrp', true);
    $sale = get_post_meta($post->ID,'sale_price', true);
    $gst = get_post_meta($post->ID,'gst_percent', true);
    $cost = get_post_meta($post->ID,'cost_price', true);
    $bullets = (array) get_post_meta($post->ID,'product_bullets', true); if(empty($bullets)) $bullets = [];
    $bullets_text = esc_textarea( implode("\n", $bullets) );
    $sku_attr = esc_attr($sku); $gst_attr=esc_attr($gst); $mrp_attr=esc_attr($mrp); $sale_attr=esc_attr($sale); $cost_attr=esc_attr($cost); $margin_attr=esc_attr($margin);
    $stock_status = get_post_meta($post->ID,'stock_status', true) ?: 'in_stock';
    $stock_options = '<option value="in_stock"'.selected($stock_status,'in_stock',false).'>In Stock</option><option value="out_of_stock"'.selected($stock_status,'out_of_stock',false).'>Out of Stock</option>';
    $pid_html = esc_html($pid);
    $unit_options = sprintf('<option value="">—</option><option value="pieces" %s>Pieces</option><option value="kg" %s>KG</option><option value="litres" %s>Litres</option>',
        selected($unit,'pieces',false), selected($unit,'kg',false), selected($unit,'litres',false));
    $tax_options = sprintf('<option value="">—</option><option value="GST" %s>GST</option><option value="VAT" %s>VAT</option>',
        selected($tax,'GST',false), selected($tax,'VAT',false));
    echo <<<HTML
<style>.svntex-admin-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin:12px 0}.svntex-admin-grid label{display:flex;flex-direction:column;font-size:12px;font-weight:600;gap:4px}.svntex-inline-note{font-size:11px;color:#555;margin-top:4px}.svntex-bullets textarea{width:100%;min-height:100px;font-family:monospace}</style>
<p><strong>Product ID:</strong> {$pid_html}</p>
<div class="svntex-admin-grid">
  <label>SKU <input type="text" name="svntex_sku" value="{$sku_attr}" placeholder="Internal SKU" /></label>
  <label>Unit <select name="svntex_unit">{$unit_options}</select></label>
  <label>Tax Class <select name="svntex_tax">{$tax_options}</select></label>
  <label>GST % <input type="number" step="0.01" name="svntex_gst" value="{$gst_attr}" /></label>
  <label>MRP <input type="number" step="0.01" name="svntex_mrp" value="{$mrp_attr}" /></label>
  <label>Discount / Sale Price <input type="number" step="0.01" name="svntex_sale" value="{$sale_attr}" /></label>
  <label>Cost Price (Admin Only) <input type="number" step="0.01" name="svntex_cost" value="{$cost_attr}" /></label>
  <label>Profit Margin % <input type="number" step="0.01" name="svntex_margin" value="{$margin_attr}" class="small-text" /></label>
    <label>Stock Status <select name="svntex_stock_status">{$stock_options}</select></label>
</div>
<div class="svntex-bullets"><label>Bullet Points (one per line)<textarea name="svntex_bullets">{$bullets_text}</textarea></label><p class="svntex-inline-note">These appear as feature bullets (Amazon style). Public.</p></div>
<p class="svntex-inline-note">Leave Profit Margin blank to auto-calc from Sale (or MRP) and Cost.</p>
HTML;
}

function svntex2_mb_product_media($post){
    $gallery = (array) get_post_meta($post->ID,'product_gallery', true); if(empty($gallery)) $gallery=[];
    $videos = (array) get_post_meta($post->ID,'product_videos', true); if(empty($videos)) $videos=[];
    $gallery_ids = esc_attr( implode(',', $gallery) );
    $videos_text = esc_textarea( implode("\n", $videos) );
    echo <<<HTML
<style>.svntex-media-wrap{display:flex;flex-wrap:wrap;gap:10px;margin:8px 0}.svntex-media-thumb{width:70px;height:70px;position:relative;border:1px solid #ccd0d4;background:#f8f9fa;border-radius:4px;overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:11px;color:#555}.svntex-media-thumb img{width:100%;height:100%;object-fit:cover}.svntex-remove{position:absolute;top:2px;right:2px;background:#d63638;color:#fff;border:none;border-radius:2px;padding:0 4px;font-size:11px;cursor:pointer}</style>
<p style="margin-top:0">Add gallery images & optional product videos (YouTube / MP4 URLs). Featured Image uses the standard WordPress panel.</p>
<div id="svntex-gallery" class="svntex-media-wrap">
HTML;
    if($gallery){
        foreach($gallery as $id){
            $id_int = (int)$id;
            $thumb = wp_get_attachment_image($id_int,'thumbnail',false,[ 'style'=>'display:block;width:100%;height:100%;object-fit:cover;' ]);
            if(!$thumb) $thumb = '<span>No Image</span>';
            echo '<div class="svntex-media-thumb" data-id="'.$id_int.'">'.$thumb.'<button type="button" class="svntex-remove" title="Remove">&times;</button></div>';
        }
    }
    echo "</div>";
    echo '<input type="hidden" name="svntex_gallery_ids" id="svntex_gallery_ids" value="'.$gallery_ids.'" />';
    echo '<p><button type="button" class="button" id="svntex_add_gallery">Add Images</button></p>';
    echo '<p><label>Video URLs (one per line)<br /><textarea name="svntex_videos" style="width:100%;min-height:90px;">'.$videos_text.'</textarea></label></p>';
    echo '<p style="font-size:11px;color:#555;">Video URLs are optional; they can be embedded on the product page in future enhancements.</p>';
    echo <<<JS
<script>jQuery(function($){if(typeof wp==="undefined"||!wp.media)return;let frame;$(document).on('click','#svntex_add_gallery',function(e){e.preventDefault(); if(frame){frame.open();return;} frame=wp.media({title:'Select Images',multiple:true,library:{type:'image'}}); frame.on('select',function(){const sel=frame.state().get('selection'); sel.each(function(att){const id=att.get('id'); if(!id) return; if($('#svntex-gallery .svntex-media-thumb[data-id='+id+']').length) return; const size=att.get('sizes'); const url=size&&size.thumbnail?size.thumbnail.url:att.get('url'); $('#svntex-gallery').append('<div class="svntex-media-thumb" data-id="'+id+'"><img src="'+url+'"/><button type="button" class="svntex-remove" title="Remove">&times;</button></div>'); updateHidden(); });}); frame.open();}); function updateHidden(){const ids=$('#svntex-gallery .svntex-media-thumb').map(function(){return $(this).data('id');}).get(); $('#svntex_gallery_ids').val(ids.join(',')); } $(document).on('click','.svntex-media-thumb .svntex-remove',function(){ $(this).closest('.svntex-media-thumb').remove(); updateHidden(); });});</script>
JS;
}

add_action('save_post_svntex_product', function($post_id){
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    // Ensure product_id exists even for REST-created posts
    if (!get_post_meta($post_id,'product_id', true)) {
        update_post_meta($post_id,'product_id', svntex2_generate_product_id());
    }
    if (isset($_POST['svntex_unit'])) update_post_meta($post_id,'unit', sanitize_text_field($_POST['svntex_unit']));
    if (isset($_POST['svntex_tax'])) update_post_meta($post_id,'tax_class', sanitize_text_field($_POST['svntex_tax']));
    if (isset($_POST['svntex_margin']) && $_POST['svntex_margin'] !== '') update_post_meta($post_id,'profit_margin', (float) $_POST['svntex_margin']);
    if (isset($_POST['svntex_sku'])) update_post_meta($post_id,'product_sku', sanitize_text_field($_POST['svntex_sku']));
    foreach(['mrp'=>'mrp','sale_price'=>'sale','gst_percent'=>'gst','cost_price'=>'cost'] as $meta=>$field){
        $key='svntex_'.$field; if(isset($_POST[$key]) && $_POST[$key] !== '') update_post_meta($post_id,$meta,(float)$_POST[$key]);
    }
    if(isset($_POST['svntex_stock_status'])){
        $ss = $_POST['svntex_stock_status']==='out_of_stock'?'out_of_stock':'in_stock';
        update_post_meta($post_id,'stock_status',$ss);
    }
    if(isset($_POST['svntex_bullets'])){
        $lines = array_filter(array_map('trim', explode("\n", (string)$_POST['svntex_bullets'])));
        update_post_meta($post_id,'product_bullets', $lines);
    }
    if(isset($_POST['svntex_gallery_ids'])){
        $ids = array_filter(array_map('intval', explode(',', sanitize_text_field($_POST['svntex_gallery_ids']))));
        update_post_meta($post_id,'product_gallery', $ids);
    }
    if(isset($_POST['svntex_videos'])){
        $vids = array_filter(array_map('trim', explode("\n", (string)$_POST['svntex_videos'])));
        update_post_meta($post_id,'product_videos', $vids);
    }
    // Auto compute margin if blank and we have cost + (sale or mrp)
    $existing_margin = get_post_meta($post_id,'profit_margin', true);
    if($existing_margin === '' || $existing_margin === null){
        $cost = (float) get_post_meta($post_id,'cost_price', true);
        $sale = (float) get_post_meta($post_id,'sale_price', true);
        $mrp  = (float) get_post_meta($post_id,'mrp', true);
        $basis = $sale ?: $mrp;
        if($basis > 0 && $cost > 0 && $basis > $cost){
            $auto = (($basis - $cost)/$basis)*100.0;
            update_post_meta($post_id,'profit_margin', round($auto,2));
        }
    }
});

// Admin columns for quick Amazon-like overview
add_filter('manage_svntex_product_posts_columns', function($cols){
    $insert = [ 'product_sku'=>'SKU', 'mrp'=>'MRP', 'sale_price'=>'Sale', 'gst_percent'=>'GST %', 'profit_margin'=>'Profit %', 'stock_status'=>'Stock' ];
    // place after title
    $new = []; foreach($cols as $k=>$v){ $new[$k]=$v; if($k==='title'){ foreach($insert as $ik=>$iv){ $new[$ik]=$iv; } } }
    return $new;
});
add_action('manage_svntex_product_posts_custom_column', function($col,$post_id){
    switch($col){
        case 'product_sku': echo esc_html(get_post_meta($post_id,'product_sku', true)); break;
        case 'mrp': $v=get_post_meta($post_id,'mrp', true); if($v!=='') echo esc_html(number_format_i18n((float)$v,2)); break;
        case 'sale_price': $v=get_post_meta($post_id,'sale_price', true); if($v!=='') echo esc_html(number_format_i18n((float)$v,2)); break;
        case 'gst_percent': $v=get_post_meta($post_id,'gst_percent', true); if($v!=='') echo esc_html((float)$v); break;
        case 'profit_margin': $v=get_post_meta($post_id,'profit_margin', true); if($v!=='') echo esc_html((float)$v); break;
    case 'stock_status': $v=get_post_meta($post_id,'stock_status', true) ?: 'in_stock'; echo $v==='in_stock'?'<span style="color:#16a34a;font-weight:600;">In</span>':'<span style="color:#dc2626;font-weight:600;">Out</span>'; break;
    }
},10,2);
add_filter('manage_edit-svntex_product_sortable_columns', function($cols){ $cols['mrp']='mrp'; $cols['sale_price']='sale_price'; $cols['profit_margin']='profit_margin'; return $cols; });

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

/**
 * Atomically decrement inventory for multiple variants.
 * @param array $variant_qty_map [ variant_id => required_qty ]
 * @return true|WP_Error
 */
function svntex2_inventory_batch_decrement(array $variant_qty_map){
    global $wpdb; if(empty($variant_qty_map)) return true;
    $stkT=$wpdb->prefix.'svntex_inventory_stocks'; $varT=$wpdb->prefix.'svntex_product_variants';
    // Validate positive quantities
    foreach($variant_qty_map as $vid=>$q){ if($q<=0){ unset($variant_qty_map[$vid]); } }
    if(!$variant_qty_map) return true;
    $wpdb->query('START TRANSACTION');
    $touched_products = [];
    // Low stock threshold: option configurable + filter override
    $low_threshold = (int) apply_filters('svntex2_low_stock_threshold', get_option('svntex2_low_stock_threshold', 5));
    foreach($variant_qty_map as $vid=>$need){
        $vid=(int)$vid; $need=(int)$need; if($need<=0) continue;
        // Lock rows for this variant
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,qty FROM $stkT WHERE variant_id=%d ORDER BY qty DESC FOR UPDATE", $vid));
        $total=0; foreach($rows as $r){ $total += (int)$r->qty; }
        if($total < $need){ $wpdb->query('ROLLBACK'); return new WP_Error('out_of_stock','Insufficient stock for variant '.$vid,[ 'variant_id'=>$vid,'required'=>$need,'available'=>$total ]); }
        $remain=$need;
        foreach($rows as $r){ if($remain<=0) break; $take = min($remain,(int)$r->qty); if($take>0){ $wpdb->update($stkT,[ 'qty'=>(int)$r->qty - $take ],[ 'id'=>$r->id ]); $remain -= $take; } }
        // After decrement, compute remaining to trigger low stock action
        $after_total = (int)$wpdb->get_var($wpdb->prepare("SELECT SUM(qty) FROM $stkT WHERE variant_id=%d", $vid));
        if($after_total > 0 && $after_total <= $low_threshold){
            do_action('svntex2_low_stock_variant',$vid,$after_total,$low_threshold);
        }
        if($after_total === 0){ do_action('svntex2_out_of_stock_variant',$vid); }
        // Track product for status update
        $pid = (int)$wpdb->get_var($wpdb->prepare("SELECT product_id FROM $varT WHERE id=%d", $vid)); if($pid) $touched_products[$pid]=true;
    }
    // Auto-sync product stock_status (simple rule: if all variant stock == 0 -> out_of_stock; else in_stock)
    foreach(array_keys($touched_products) as $pid){
        $total_variant_stock = (int)$wpdb->get_var($wpdb->prepare("SELECT SUM(s.qty) FROM $varT v LEFT JOIN $stkT s ON s.variant_id=v.id WHERE v.product_id=%d", $pid));
        update_post_meta($pid,'stock_status', $total_variant_stock>0 ? 'in_stock' : 'out_of_stock');
    }
    $wpdb->query('COMMIT');
    return true;
}

// 4b) Variant price range helper (min/max active variant prices)
function svntex2_get_variant_price_range($product_id){
    global $wpdb; $t = $wpdb->prefix.'svntex_product_variants';
    $rows = $wpdb->get_row($wpdb->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price FROM $t WHERE product_id=%d AND active=1 AND price IS NOT NULL", (int)$product_id));
    if(!$rows || $rows->min_price === null){
        return [ 'min'=>null, 'max'=>null ];
    }
    return [ 'min'=>(float)$rows->min_price, 'max'=>(float)$rows->max_price ];
}

// 5) Delivery rule resolver (param order updated: subtotal last optional variant avoided deprecation)
function svntex2_delivery_compute($product_id, $subtotal, $variant_id = null){
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

// (Intentionally no closing PHP tag)

// 8) Inventory alert email notifications (low stock / out of stock)
// These leverage the existing actions fired in svntex2_inventory_batch_decrement.
add_action('svntex2_low_stock_variant', function($variant_id, $remaining, $threshold){
    // Check if notifications disabled
    if(!get_option('svntex2_stock_notify_enabled', 1)) return;
    $variant_id = (int)$variant_id; $remaining = (int)$remaining; $threshold = (int)$threshold;
    // Throttle: one email per variant per 6h while still below/equal threshold
    if( get_transient('svntex2_low_stock_sent_'.$variant_id) ) return;
    global $wpdb; $varT = $wpdb->prefix.'svntex_product_variants';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT v.id,v.sku,v.product_id, p.post_title FROM $varT v LEFT JOIN {$wpdb->posts} p ON p.ID=v.product_id WHERE v.id=%d", $variant_id) );
    if(!$row) return;
    $emails_raw = get_option('svntex2_stock_notify_emails','');
    $emails = array_filter(array_map('sanitize_email', array_map('trim', explode(',', (string)$emails_raw))));
    if(!$emails) { $admin_email = get_option('admin_email'); if($admin_email) $emails = [$admin_email]; }
    if(!$emails) return; // nowhere to send
    $subject = sprintf('Low Stock: %s (Variant #%d)', $row->post_title ?: 'Product', $variant_id);
    $body  = "A product variant has reached low stock.\n\n";
    $body .= "Product: ".$row->post_title."\n";
    $body .= "Variant ID: ".$variant_id."\n";
    $body .= "SKU: ".$row->sku."\n";
    $body .= "Remaining Qty: ".$remaining." (threshold: ".$threshold.")\n";
    $body .= "Time: ".current_time('mysql')."\n";
    if(function_exists('svntex2_inventory_get_rows')){
        $rows = svntex2_inventory_get_rows($variant_id);
        if($rows){
            $body .= "\nLocation Breakdown:\n";
            foreach($rows as $r){ $body .= sprintf(" - %s: %d\n", $r['location']['code'], $r['qty']); }
        }
    }
    $body .= "\nThis alert will repeat after 6h if stock is still low.\n";
    foreach($emails as $em){ wp_mail($em, $subject, $body); }
    set_transient('svntex2_low_stock_sent_'.$variant_id, 1, 6 * HOUR_IN_SECONDS);
}, 10, 3);

add_action('svntex2_out_of_stock_variant', function($variant_id){
    if(!get_option('svntex2_stock_notify_enabled', 1)) return;
    $variant_id = (int)$variant_id;
    // Throttle separate from low stock: one per 3h while zero
    if( get_transient('svntex2_oos_sent_'.$variant_id) ) return;
    global $wpdb; $varT = $wpdb->prefix.'svntex_product_variants';
    $row = $wpdb->get_row( $wpdb->prepare("SELECT v.id,v.sku,v.product_id, p.post_title FROM $varT v LEFT JOIN {$wpdb->posts} p ON p.ID=v.product_id WHERE v.id=%d", $variant_id) );
    if(!$row) return;
    $emails_raw = get_option('svntex2_stock_notify_emails','');
    $emails = array_filter(array_map('sanitize_email', array_map('trim', explode(',', (string)$emails_raw))));
    if(!$emails) { $admin_email = get_option('admin_email'); if($admin_email) $emails = [$admin_email]; }
    if(!$emails) return;
    $subject = sprintf('Out of Stock: %s (Variant #%d)', $row->post_title ?: 'Product', $variant_id);
    $body  = "A product variant has reached zero stock.\n\n";
    $body .= "Product: ".$row->post_title."\nVariant ID: ".$variant_id."\nSKU: ".$row->sku."\nTime: ".current_time('mysql')."\n";
    $body .= "\nRestock to reset alert throttling.\n";
    foreach($emails as $em){ wp_mail($em, $subject, $body); }
    set_transient('svntex2_oos_sent_'.$variant_id, 1, 3 * HOUR_IN_SECONDS);
}, 10, 1);

