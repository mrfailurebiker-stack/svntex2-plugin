<?php
// Additional shortcodes placeholder (wallet history etc.)
if (!defined('ABSPATH')) exit;

// Wallet history shortcode (scaffold)
add_shortcode('svntex_wallet_history', function($atts){
    if(!is_user_logged_in()) return '<p>Please log in.</p>';
    global $wpdb; $table = $wpdb->prefix.'svntex_wallet_transactions';
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id=%d ORDER BY id DESC LIMIT 25", get_current_user_id()));
    if(!$rows) return '<p>No transactions yet.</p>';
    $out = '<table class="svntex2-wallet-history"><thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance</th></tr></thead><tbody>';
    foreach($rows as $r){
        $out .= '<tr><td>'.esc_html(mysql2date('Y-m-d H:i', $r->created_at)).'</td><td>'.esc_html($r->type).'</td><td>'.esc_html(number_format($r->amount,2)).'</td><td>'.esc_html(number_format($r->balance_after,2)).'</td></tr>';
    }
    $out .= '</tbody></table>';
    return $out;
});

// Debug summary shortcode
add_shortcode('svntex_debug', function(){
  if(!current_user_can('manage_options')) return '';
  $uid = get_current_user_id();
  $bal = svntex2_wallet_get_balance($uid);
  $qualified = function_exists('svntex2_referrals_get_qualified_count') ? svntex2_referrals_get_qualified_count($uid) : 0;
  return '<pre style="font-size:12px; background:#111; color:#0f0; padding:10px;">USER: '+$uid+'\nBAL:'+number_format($bal,2)+'\nQUALIFIED:'+ $qualified +'</pre>';
});

// Login shortcode (styled)
add_shortcode('svntex_login', function(){
  if ( is_user_logged_in() ) return '<p>You are logged in.</p>';
  wp_enqueue_style('svntex2-style');
  $file = SVNTEX2_PLUGIN_DIR . 'views/customer-login.php';
  if ( file_exists( $file ) ) { ob_start(); include $file; return ob_get_clean(); }
  // Fallback to simple form
  $fallback = SVNTEX2_PLUGIN_DIR . 'views/login.php';
  if ( file_exists( $fallback ) ) { ob_start(); include $fallback; return ob_get_clean(); }
  return '<p>Login view missing.</p>';
});

// Landing page shortcode (custom UI)
add_shortcode('svntex_landing', function(){
  wp_enqueue_style('svntex2-landing');
  wp_enqueue_style('svntex2-style');
  wp_enqueue_script('svntex2-brand-init');
  $file = SVNTEX2_PLUGIN_DIR . 'views/landing.php';
  if ( file_exists( $file ) ) { ob_start(); include $file; return ob_get_clean(); }
  return '<p>Landing view missing.</p>';
});

// Dashboard shortcode
add_shortcode('svntex_dashboard', function(){
  if ( ! is_user_logged_in() ) { wp_safe_redirect( home_url('/'.SVNTEX2_LOGIN_SLUG.'/') ); exit; }
  // enqueue assets
  wp_enqueue_style('svntex2-style');
  wp_enqueue_script('svntex2-dashboard');
  $file = SVNTEX2_PLUGIN_DIR . 'views/dashboard.php';
  if ( file_exists( $file ) ) { ob_start(); include $file; return ob_get_clean(); }
  return '<p>Dashboard view missing.</p>';
});

// AJAX login handler
add_action('wp_ajax_nopriv_svntex2_do_login', 'svntex2_do_login');
// Public endpoint to fetch login nonce for static admin portal
add_action('wp_ajax_nopriv_svntex2_get_login_nonce', function(){
  $nonce = wp_create_nonce('svntex2_login');
  wp_send_json_success([ 'nonce' => $nonce ]);
});
add_action('wp_ajax_svntex2_get_login_nonce', function(){
  $nonce = wp_create_nonce('svntex2_login');
  wp_send_json_success([ 'nonce' => $nonce ]);
});

// Endpoint to get REST nonce so static admin can call WP REST API
add_action('wp_ajax_svntex2_get_rest_nonce', function(){
  if ( ! is_user_logged_in() ) { wp_send_json_error(['message'=>'Not logged in'], 401); }
  $nonce = wp_create_nonce('wp_rest');
  wp_send_json_success([ 'nonce' => $nonce ]);
});
function svntex2_do_login(){
  check_ajax_referer('svntex2_login','svntex2_login_nonce');
  $login = sanitize_text_field($_POST['login_id'] ?? '');
  $pass  = (string)($_POST['password'] ?? '');
  $remember = !empty($_POST['remember']);
  if(!$login || !$pass){ wp_send_json_error(['message'=>'Missing credentials']); }
  // Resolve user by multiple identifiers (email, username, nicename, customer_id, mobile, employee_id)
  $user = null; $login_trim = trim($login);
  if ( is_email($login_trim) ) { $user = get_user_by('email', $login_trim); }
  if ( ! $user ) { $user = get_user_by('login', $login_trim); }
  if ( ! $user ) { $user = get_user_by('slug', sanitize_title($login_trim)); }
  if ( ! $user ) {
    // Try a broader search over login/email/nicename
    $q = new WP_User_Query([
      'search' => $login_trim,
      'search_columns' => ['user_login','user_email','user_nicename'],
      'number' => 1,
      'fields' => 'all',
    ]);
    $results = $q->get_results();
    if ( ! empty($results) ) { $user = $results[0]; }
  }
  if ( ! $user ) {
    // Try by metas
    $meta_keys = ['customer_id','mobile','employee_id'];
    foreach ($meta_keys as $mk) {
      $ids = get_users([ 'meta_key'=>$mk, 'meta_value'=> $login_trim, 'fields'=>'ids', 'number'=>1 ]);
      if($ids){ $user = get_user_by('id', $ids[0]); break; }
    }
  }
  if( ! $user ) { wp_send_json_error(['message'=>'User not found. Try your Username (SVNXXXXXX) or registered email.']); }
  $auth = wp_signon([ 'user_login'=>$user->user_login, 'user_password'=>$pass, 'remember'=>$remember ], is_ssl());
  if ( is_wp_error($auth) ) { wp_send_json_error(['message'=>'Invalid credentials']); }
  wp_set_current_user($auth->ID);
  wp_send_json_success(['redirect'=> home_url('/'.SVNTEX2_DASHBOARD_SLUG.'/') ]);
}
