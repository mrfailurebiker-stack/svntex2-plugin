<?php
/**
 * Plugin Name: SVNTEX-2
 * Description: Core functionality for the SVNTEX theme.
 * Version: 2.0.0
 * Author: Blackbox
 * Text Domain: svntex
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Unify constant names (some files use SVNTEX2_*). Keep both for BC.
define( 'SVNTEX_VERSION', '2.0.0' );
define( 'SVNTEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SVNTEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined('SVNTEX2_VERSION') ) define('SVNTEX2_VERSION', SVNTEX_VERSION);
if ( ! defined('SVNTEX2_PLUGIN_DIR') ) define('SVNTEX2_PLUGIN_DIR', SVNTEX_PLUGIN_DIR);
if ( ! defined('SVNTEX2_PLUGIN_URL') ) define('SVNTEX2_PLUGIN_URL', SVNTEX_PLUGIN_URL);
if ( ! defined('SVNTEX2_PLUGIN_FILE') ) define('SVNTEX2_PLUGIN_FILE', __FILE__);
// Public page slugs used throughout the plugin
if ( ! defined('SVNTEX2_LOGIN_SLUG') ) define('SVNTEX2_LOGIN_SLUG', 'customer-login');
if ( ! defined('SVNTEX2_REGISTER_SLUG') ) define('SVNTEX2_REGISTER_SLUG', 'customer-registration');
if ( ! defined('SVNTEX2_DASHBOARD_SLUG') ) define('SVNTEX2_DASHBOARD_SLUG', 'dashboard');

/**
 * Include necessary files.
 */
function svntex_include_files() {
    // Functions
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/debug.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/helpers.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/enqueue.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/products.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/rest.php';
    // Admin UI (adds SVNTeX 2.0 menu in wp-admin)
    $admin_ui = SVNTEX_PLUGIN_DIR . 'includes/functions/admin.php';
    if ( file_exists($admin_ui) ) require_once $admin_ui;
    $commerce = SVNTEX_PLUGIN_DIR . 'includes/functions/commerce.php';
    if ( file_exists($commerce) ) require_once $commerce;
    $withdrawals = SVNTEX_PLUGIN_DIR . 'includes/functions/withdrawals.php';
    if ( file_exists($withdrawals) ) require_once $withdrawals;
    $vendors = SVNTEX_PLUGIN_DIR . 'includes/functions/vendors.php';
    if ( file_exists($vendors) ) require_once $vendors;
    $referrals = SVNTEX_PLUGIN_DIR . 'includes/functions/referrals.php';
    if ( file_exists($referrals) ) require_once $referrals;
    $kyc = SVNTEX_PLUGIN_DIR . 'includes/functions/kyc.php';
    if ( file_exists($kyc) ) require_once $kyc;
    $cron = SVNTEX_PLUGIN_DIR . 'includes/functions/cron.php';
    if ( file_exists($cron) ) require_once $cron;
    $shortcodes = SVNTEX_PLUGIN_DIR . 'includes/functions/shortcodes.php';
    if ( file_exists($shortcodes) ) require_once $shortcodes;

    // Classes
    $auth = SVNTEX_PLUGIN_DIR . 'includes/classes/auth.php';
    if ( file_exists($auth) ) require_once $auth;
}
add_action( 'plugins_loaded', 'svntex_include_files' );


/**
 * Initialize the plugin.
 */
function svntex_init() {
    // Register a custom logout endpoint handler
    add_action('admin_post_svntex2_logout', function(){
        wp_logout();
        wp_safe_redirect( home_url('/customer-login/') );
        exit;
    });
    add_action('admin_post_nopriv_svntex2_logout', function(){
        wp_safe_redirect( home_url('/customer-login/') );
        exit;
    });
}
add_action( 'init', 'svntex_init' );

/**
 * Redirect users after login to the correct SPA and keep non-admins out of wp-admin.
 */
add_filter('login_redirect', function($redirect_to, $request, $user){
    if ( is_wp_error($user) || ! $user ) return $redirect_to;
    if ( user_can($user, 'manage_options') ) {
        return site_url('/admin-v2/panel.html');
    }
    return site_url('/member-v2/app.html');
}, 10, 3);

// Redirect everyone away from wp-admin to SPA unless explicitly allowed
// Define SVNTEX2_ALLOW_WP_ADMIN=true in wp-config.php to bypass this.
add_action('admin_init', function(){
    if ( defined('SVNTEX2_ALLOW_WP_ADMIN') && SVNTEX2_ALLOW_WP_ADMIN ) return;
    if ( defined('DOING_AJAX') && DOING_AJAX ) return;
    if ( current_user_can('manage_options') ) {
        wp_safe_redirect( site_url('/admin-v2/panel.html') );
        exit;
    } else {
        wp_safe_redirect( site_url('/member-v2/app.html') );
        exit;
    }
});

// If a logged-in user hits the login page, send them to their SPA
add_action('template_redirect', function(){
    if ( is_user_logged_in() ) {
        // Redirect from our shortcode login page slug
        if ( is_page( SVNTEX2_LOGIN_SLUG ) ) {
            if ( current_user_can('manage_options') ) wp_safe_redirect( site_url('/admin-v2/panel.html') );
            else wp_safe_redirect( site_url('/member-v2/app.html') );
            exit;
        }
    }
});

/**
 * One-time demo users seeding (requested):
 * - Admin: username nithin / password 1234
 * - Member: username detest / password 1234
 * Creates only once; can be disabled by defining SVNTEX2_DISABLE_DEMO_SEED true in wp-config.php
 */
function svntex2_seed_demo_users(){
    if ( defined('SVNTEX2_DISABLE_DEMO_SEED') && SVNTEX2_DISABLE_DEMO_SEED ) return;
    if ( get_option('svntex2_demo_users_seeded') ) return;
    // Create/ensure admin
    $admin = get_user_by('login','nithin');
    if ( ! $admin ) {
        $aid = wp_insert_user([
            'user_login' => 'nithin',
            'user_pass'  => '1234',
            'user_email' => 'nithin@example.com',
            'display_name' => 'Nithin',
            'role' => 'administrator',
        ]);
        if ( ! is_wp_error($aid) ) { $admin = get_user_by('id', $aid); }
    } else {
        // Ensure role + password
        wp_set_password('1234', $admin->ID);
        $u = new WP_User($admin->ID); $u->set_role('administrator');
    }

    // Create/ensure member
    $member = get_user_by('login','detest');
    if ( ! $member ) {
        $mid = wp_insert_user([
            'user_login' => 'detest',
            'user_pass'  => '1234',
            'user_email' => 'detest@example.com',
            'display_name' => 'Demo Test',
            'role' => 'subscriber',
        ]);
        if ( ! is_wp_error($mid) ) {
            update_user_meta($mid,'customer_id','SVN000001');
        }
    } else {
        wp_set_password('1234', $member->ID);
        $u = new WP_User($member->ID); if ( ! in_array('subscriber', (array)$u->roles, true) ) { $u->set_role('subscriber'); }
        update_user_meta($member->ID,'customer_id', get_user_meta($member->ID,'customer_id', true) ?: 'SVN000001');
    }

    update_option('svntex2_demo_users_seeded', current_time('mysql'));
}
add_action('init','svntex2_seed_demo_users');

// Create wallet transactions table on activation
function svntex_activate(){
    global $wpdb; $table = $wpdb->prefix.'svntex_wallet_transactions';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        type VARCHAR(50) NOT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'general',
        amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        balance_after DECIMAL(18,2) NOT NULL DEFAULT 0,
        reference_id VARCHAR(191) NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_idx (user_id)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
    // Orders
    $orders = $wpdb->prefix.'svntex_orders';
    $order_items = $wpdb->prefix.'svntex_order_items';
    $sql_orders = "CREATE TABLE IF NOT EXISTS $orders (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        items_total DECIMAL(18,2) NOT NULL DEFAULT 0,
        delivery_total DECIMAL(18,2) NOT NULL DEFAULT 0,
        grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,
        address LONGTEXT NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_idx (user_id)
    ) $charset;";
    dbDelta($sql_orders);
    $sql_items = "CREATE TABLE IF NOT EXISTS $order_items (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        variant_id BIGINT UNSIGNED NULL,
        qty INT NOT NULL DEFAULT 1,
        price DECIMAL(18,2) NOT NULL DEFAULT 0,
        subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
        meta LONGTEXT NULL,
        PRIMARY KEY (id),
        KEY order_idx (order_id)
    ) $charset;";
    dbDelta($sql_items);
    // Withdrawals
    $wd = $wpdb->prefix.'svntex_withdrawals';
    $sql_wd = "CREATE TABLE IF NOT EXISTS $wd (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(18,2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'requested',
        method VARCHAR(50) NULL,
        destination VARCHAR(191) NULL,
        tds_amount DECIMAL(18,2) NULL,
        amc_amount DECIMAL(18,2) NULL,
        net_amount DECIMAL(18,2) NULL,
        admin_note TEXT NULL,
        requested_at DATETIME NULL,
        processed_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY user_idx (user_id)
    ) $charset;";
    dbDelta($sql_wd);
    // Create essential pages if missing
    svntex2_ensure_page( SVNTEX2_LOGIN_SLUG, 'Customer Login', '[svntex_login]' );
    svntex2_ensure_page( SVNTEX2_REGISTER_SLUG, 'Customer Registration', '[svntex_registration]' );
    svntex2_ensure_page( SVNTEX2_DASHBOARD_SLUG, 'Dashboard', '[svntex_dashboard]' );
    // Create landing home page and set as static front page if not set
    $home_id = svntex2_ensure_page( 'home', 'Home', '[svntex_landing]' );
    if ( $home_id ) {
        update_option('show_on_front','page');
        update_option('page_on_front', $home_id);
    }
}
register_activation_hook(__FILE__, 'svntex_activate');

// Ensure front page points to landing once, if still on blog posts
function svntex_maybe_setup_front_page(){
    if ( get_option('svntex2_frontpage_set') ) return; // already applied once
    $show = get_option('show_on_front');
    $front = (int) get_option('page_on_front');
    if ( $show !== 'page' || ! $front ){
        $home_id = svntex2_ensure_page( 'home', 'Home', '[svntex_landing]' );
        if ( $home_id ){
            update_option('show_on_front','page');
            update_option('page_on_front', $home_id);
            update_option('svntex2_frontpage_set', current_time('mysql'));
        }
    }
}
add_action('admin_init','svntex_maybe_setup_front_page');

/** Create or update a page by slug with provided content */
function svntex2_ensure_page( $slug, $title, $content ){
    $page = get_page_by_path( $slug );
    if ( $page ) {
        if ( strpos( (string) $page->post_content, $content ) === false ) {
            wp_update_post([ 'ID'=>$page->ID, 'post_content'=>$content, 'post_status'=>'publish' ]);
        }
        return (int)$page->ID;
    }
    return (int) wp_insert_post([
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => $content,
        'post_type'    => 'page',
        'post_status'  => 'publish'
    ]);
}


/**
 * Adds a custom GST number field to the WooCommerce product general tab.
 */
function svntex_add_gst_field_to_products() {
    echo '<div class="options_group">';
    if ( function_exists('woocommerce_wp_text_input') ) {
        woocommerce_wp_text_input([
            'id'          => '_svntex_gst_rate',
            'label'       => __( 'GST Rate (%)', 'svntex' ),
            'placeholder' => 'e.g., 18',
            'desc_tip'    => 'true',
            'description' => __( 'Enter the GST rate for this product as a percentage.', 'svntex' ),
            'type'        => 'number',
            'custom_attributes' => [ 'step' => 'any', 'min'  => '0' ]
        ]);
    } else {
        echo '<p class="description">' . esc_html__('WooCommerce inactive: GST field unavailable.', 'svntex') . '</p>';
    }
    echo '</div>';
}
if ( class_exists('WooCommerce') ) {
    add_action( 'woocommerce_product_options_general_product_data', 'svntex_add_gst_field_to_products' );
}

/**
 * Saves the custom GST number field value.
 *
 * @param int $product_id The ID of the product being saved.
 */
function svntex_save_gst_field( $product_id ) {
    $gst_rate = isset( $_POST['_svntex_gst_rate'] ) ? sanitize_text_field( $_POST['_svntex_gst_rate'] ) : '';
    update_post_meta( $product_id, '_svntex_gst_rate', $gst_rate );
}
if ( class_exists('WooCommerce') ) {
    add_action( 'woocommerce_process_product_meta', 'svntex_save_gst_field' );
}

