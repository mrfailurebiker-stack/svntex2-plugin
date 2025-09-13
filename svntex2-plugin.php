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
// Public page slugs used throughout the plugin
if ( ! defined('SVNTEX2_LOGIN_SLUG') ) define('SVNTEX2_LOGIN_SLUG', 'customer-login');
if ( ! defined('SVNTEX2_REGISTER_SLUG') ) define('SVNTEX2_REGISTER_SLUG', 'customer-registration');
if ( ! defined('SVNTEX2_DASHBOARD_SLUG') ) define('SVNTEX2_DASHBOARD_SLUG', 'dashboard');

/**
 * Include necessary files.
 */
function svntex_include_files() {
    // Functions
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/helpers.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/enqueue.php';
    $vendors = SVNTEX_PLUGIN_DIR . 'includes/functions/vendors.php';
    if ( file_exists($vendors) ) require_once $vendors;
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

