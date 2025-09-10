<?php
if(!defined('ABSPATH')) exit;

// Vendor CRUD helpers
function svntex2_vendor_create($data){
    global $wpdb; $t=$wpdb->prefix.'svntex_vendors';
    $wpdb->insert($t,[
        'name'=>sanitize_text_field($data['name'] ?? ''),
        'email'=>isset($data['email'])?sanitize_email($data['email']):null,
        'phone'=>isset($data['phone'])?sanitize_text_field($data['phone']):null,
        'notes'=>isset($data['notes'])?wp_kses_post($data['notes']):null,
    ]);
    return (int)$wpdb->insert_id;
}
function svntex2_vendor_update($id,$data){
    global $wpdb; $t=$wpdb->prefix.'svntex_vendors';
    $payload=[]; foreach(['name','email','phone','notes'] as $k){ if(isset($data[$k])){ $val=$data[$k]; if($k==='email') $val= sanitize_email($val); else if($k==='notes') $val= wp_kses_post($val); else $val = sanitize_text_field($val); $payload[$k]=$val; }}
    if($payload) $wpdb->update($t,$payload,['id'=>(int)$id]);
    return (int)$id;
}
function svntex2_vendor_get($id){ global $wpdb; $t=$wpdb->prefix.'svntex_vendors'; return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d",(int)$id), ARRAY_A); }
function svntex2_vendor_list($args=[]){ global $wpdb; $t=$wpdb->prefix.'svntex_vendors'; $rows=$wpdb->get_results("SELECT * FROM $t ORDER BY name ASC", ARRAY_A); return $rows ?: []; }
function svntex2_vendor_delete($id){ global $wpdb; $t=$wpdb->prefix.'svntex_vendors'; return (bool)$wpdb->delete($t,['id'=>(int)$id]); }

// REST endpoints for vendors
add_action('rest_api_init', function(){
    register_rest_route('svntex2/v1','/vendors',[
        ['methods'=>'GET','callback'=>function(){ return new WP_REST_Response(['items'=>svntex2_vendor_list()]); },'permission_callback'=>'svntex2_api_can_manage'],
        ['methods'=>'POST','callback'=>function($r){ $id=svntex2_vendor_create($r->get_params()); return new WP_REST_Response(['id'=>$id]); },'permission_callback'=>'svntex2_api_can_manage'],
    ]);
    register_rest_route('svntex2/v1','/vendors/(?P<id>\\d+)',[
        ['methods'=>'GET','callback'=>function($r){ $v=svntex2_vendor_get($r['id']); if(!$v) return new WP_Error('not_found','Vendor not found',['status'=>404]); return new WP_REST_Response($v); },'permission_callback'=>'svntex2_api_can_manage'],
        ['methods'=>'POST','callback'=>function($r){ $id=svntex2_vendor_update($r['id'],$r->get_params()); return new WP_REST_Response(['id'=>$id]); },'permission_callback'=>'svntex2_api_can_manage'],
        ['methods'=>'DELETE','callback'=>function($r){ $ok=svntex2_vendor_delete($r['id']); return new WP_REST_Response(['deleted'=>$ok]); },'permission_callback'=>'svntex2_api_can_manage'],
    ]);
});

// Product <-> Vendor linkage via post meta 'vendor_id'
add_action('add_meta_boxes', function(){
    add_meta_box('svntex_vendor_box','Vendor','svntex2_mb_product_vendor','svntex_product','side','default');
});
function svntex2_mb_product_vendor($post){
    $vid = (int) get_post_meta($post->ID,'vendor_id', true); $vendors = svntex2_vendor_list();
    echo '<select name="svntex_vendor_id" style="width:100%">';
    echo '<option value="">— None —</option>';
    foreach($vendors as $v){ $sel= selected($vid,$v['id'],false); echo '<option value="'.esc_attr($v['id']).'" '.$sel.'>'.esc_html($v['name']).'</option>'; }
    echo '</select>';
    echo '<p style="font-size:11px;color:#555;margin-top:6px;">Manage vendors via REST /vendors endpoint (UI coming later).</p>';
}
add_action('save_post_svntex_product', function($post_id){ if(isset($_POST['svntex_vendor_id'])){ $val=(int)$_POST['svntex_vendor_id']; if($val>0) update_post_meta($post_id,'vendor_id',$val); else delete_post_meta($post_id,'vendor_id'); }});

// Extend product formatter with vendor info (admin only detail currently filtered later if needed)
add_filter('svntex2_product_formatter_extra', function($extra,$post){
    $vid = (int) get_post_meta($post->ID,'vendor_id', true); if($vid){ $extra['vendor_id']=$vid; }
    return $extra; },10,2);

// Intentionally no closing PHP tag
