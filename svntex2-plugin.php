<?php
/**
 * Plugin Name: SVNTeX 2.0 Customer System
 * Description: Foundation for SVNTeX 2.0 – registration, wallet ledger, referrals, KYC, withdrawals, PB/RB scaffolding with WooCommerce integration.
 * Version: 0.2.0
 * Author: SVNTeX
 * Text Domain: svntex2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 */

// -----------------------------------------------------------------------------
// 0. HARD EXIT IF NOT IN WORDPRESS
// -----------------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) { exit; }

// -----------------------------------------------------------------------------
// 1. CONSTANTS
// -----------------------------------------------------------------------------
define( 'SVNTEX2_VERSION',        '0.2.2' );
define( 'SVNTEX2_PLUGIN_FILE',    __FILE__ );
define( 'SVNTEX2_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SVNTEX2_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'SVNTEX2_MIN_WC_VERSION', '7.0.0' );

// Pretty slugs for custom auth pages (can be filtered)
define( 'SVNTEX2_LOGIN_SLUG', 'customer-login' );
define( 'SVNTEX2_REGISTER_SLUG', 'customer-register' );

// -----------------------------------------------------------------------------
// 2. AUTOLOADER (Simple – looks for includes/classes/<lower>.php)
// -----------------------------------------------------------------------------
spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'SVNTEX2_' ) === 0 ) {
        $file = SVNTEX2_PLUGIN_DIR . 'includes/classes/' . strtolower( str_replace( 'SVNTEX2_', '', $class ) ) . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
} );

// -----------------------------------------------------------------------------
// 3. INCLUDE FUNCTIONAL MODULES
// -----------------------------------------------------------------------------
require_once SVNTEX2_PLUGIN_DIR . 'includes/functions/helpers.php';        // Wallet + small helpers
require_once SVNTEX2_PLUGIN_DIR . 'includes/functions/shortcodes.php';     // Shortcodes (dashboard + extras)
require_once SVNTEX2_PLUGIN_DIR . 'includes/functions/rest.php';           // REST endpoints
// Newly scaffolded domain modules (placeholders / partial implementations)
foreach ( [ 'referrals', 'kyc', 'withdrawals', 'cron', 'admin', 'cli' ] as $module ) {
    $file = SVNTEX2_PLUGIN_DIR . 'includes/functions/' . $module . '.php';
    if ( file_exists( $file ) ) { require_once $file; }
}

// -----------------------------------------------------------------------------
// 4. ACTIVATION: CREATE TABLES (idempotent via dbDelta)
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, 'svntex2_activate' );
register_deactivation_hook( __FILE__, 'svntex2_deactivate' );

/**
 * Run on activation – creates/updates required custom tables for ledgers, referrals, etc.
 */
function svntex2_activate() {
    global $wpdb; require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    $sql = [];
    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_wallet_transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        type VARCHAR(32) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,
        reference_id VARCHAR(64) NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id), KEY type (type), KEY created_at (created_at)
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_referrals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referrer_id BIGINT UNSIGNED NOT NULL,
        referee_id BIGINT UNSIGNED NOT NULL,
        qualified TINYINT(1) NOT NULL DEFAULT 0,
        first_purchase_amount DECIMAL(12,2) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY pair (referrer_id, referee_id), KEY referrer_id (referrer_id)
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_kyc_submissions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL UNIQUE,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        aadhaar_hash VARCHAR(128) NULL,
        pan_hash VARCHAR(128) NULL,
        bank_name VARCHAR(120) NULL,
        account_last4 VARCHAR(8) NULL,
        ifsc VARCHAR(20) NULL,
        upi_id VARCHAR(80) NULL,
        documents LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        KEY status (status)
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_withdrawals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'requested',
        method VARCHAR(30) NULL,
        destination VARCHAR(150) NULL,
        admin_note TEXT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        KEY user_id (user_id), KEY status (status)
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_profit_distributions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        month_year CHAR(7) NOT NULL,
        company_profit DECIMAL(14,2) NOT NULL,
        eligible_members INT UNSIGNED NOT NULL,
        profit_value DECIMAL(14,4) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY month_year (month_year)
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_pb_payouts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        month_year CHAR(7) NOT NULL,
        slab_percent DECIMAL(5,2) NOT NULL,
        payout_amount DECIMAL(14,2) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_month (user_id, month_year)
    ) $charset";

    foreach ( $sql as $statement ) { dbDelta( $statement ); }
    update_option( 'svntex2_version', SVNTEX2_VERSION );
    flush_rewrite_rules();
}

/** Keep data on deactivate (future: remove crons). */
function svntex2_deactivate() { flush_rewrite_rules(); }

// -----------------------------------------------------------------------------
// 5. DEPENDENCY CHECKS (WooCommerce)
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', 'svntex2_check_dependencies', 5 );
function svntex2_check_dependencies() {
    if ( defined( 'WC_VERSION') && version_compare( WC_VERSION, SVNTEX2_MIN_WC_VERSION, '<' ) ) {
        add_action( 'admin_notices', function(){ echo '<div class="notice notice-error"><p><strong>SVNTeX 2.0:</strong> WooCommerce version too old. Please upgrade.</p></div>'; } );
    } elseif ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function(){ echo '<div class="notice notice-warning"><p><strong>SVNTeX 2.0:</strong> WooCommerce not active – some features disabled.</p></div>'; } );
    }
}

// -----------------------------------------------------------------------------
// 6. UTILITY HELPERS (FOUNDATION LEVEL)
// -----------------------------------------------------------------------------
/** Generate unique customer id. */
function svntex2_generate_customer_id() : string {
    for ( $i = 0; $i < 5; $i++ ) {
        $candidate = 'SVN-' . str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        if ( ! username_exists( $candidate ) ) { return $candidate; }
    }
    return 'SVN-' . wp_rand( 100000, 999999 );
}

/** Get referral count meta (int). */
function svntex2_get_referral_count( int $user_id ) : int {
    $val = get_user_meta( $user_id, 'referral_count', true );
    return ( $val === '' ) ? 0 : (int) $val;
}

/** Wallet balance wrapper (ledger based). */
function svntex2_get_wallet_balance( int $user_id ) : float { return svntex2_wallet_get_balance( $user_id ); }

/** Recent WooCommerce orders for a user (graceful fallback). */
function svntex2_get_recent_orders( int $user_id, int $limit = 5 ) : array {
    if ( ! function_exists( 'wc_get_orders' ) ) return [];
    return wc_get_orders([
        'customer_id' => $user_id,
        'limit'       => $limit,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);
}

// -----------------------------------------------------------------------------
// 7. ASSET REGISTRATION & CONDITIONAL ENQUEUE
// -----------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'svntex2_register_assets' );
function svntex2_register_assets() {
    wp_register_style( 'svntex2-style', SVNTEX2_PLUGIN_URL . 'assets/css/style.css', [], SVNTEX2_VERSION );
    wp_register_script( 'svntex2-core', SVNTEX2_PLUGIN_URL . 'assets/js/core.js', [ 'jquery' ], SVNTEX2_VERSION, true );
    wp_register_script( 'svntex2-dashboard', SVNTEX2_PLUGIN_URL . 'assets/js/dashboard.js', [ 'jquery' ], SVNTEX2_VERSION, true );
}

// -----------------------------------------------------------------------------
// 8. DASHBOARD SHORTCODE (CONNECTS UI + DATA + WC)
// -----------------------------------------------------------------------------
add_shortcode( 'svntex_dashboard', 'svntex2_dashboard_shortcode' );
function svntex2_dashboard_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'Please log in to view your dashboard.', 'svntex2' ) . '</p>';
    }
    wp_enqueue_style( 'svntex2-style' );
    wp_enqueue_script( 'svntex2-dashboard' );
    wp_localize_script( 'svntex2-dashboard', 'SVNTEX2Dash', [
        'rest_url' => esc_url_raw( rest_url( 'svntex2/v1/wallet/balance' ) ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
    ] );
    $file = SVNTEX2_PLUGIN_DIR . 'views/dashboard.php';
    if ( file_exists( $file ) ) { ob_start(); include $file; return ob_get_clean(); }
    return '<p>' . esc_html__( 'Dashboard view missing.', 'svntex2' ) . '</p>';
}

// -----------------------------------------------------------------------------
// 9. AUTH / REGISTRATION MODULE (ADVANCED) – Loaded via Autoloader
// -----------------------------------------------------------------------------
if ( class_exists( 'SVNTEX2_Auth' ) ) { SVNTEX2_Auth::init(); }

// -----------------------------------------------------------------------------
// 9b. CUSTOM LOGIN / REGISTRATION PAGES & REDIRECTS
// -----------------------------------------------------------------------------
add_action( 'init', 'svntex2_register_auth_rewrites' );
function svntex2_register_auth_rewrites(){
    add_rewrite_rule( '^'.SVNTEX2_LOGIN_SLUG.'/?$', 'index.php?svntex2_page=login', 'top' );
    add_rewrite_rule( '^'.SVNTEX2_REGISTER_SLUG.'/?$', 'index.php?svntex2_page=register', 'top' );
    add_rewrite_tag( '%svntex2_page%', '([^&]+)' );
}

// Template loader for custom pages
add_action( 'template_redirect', 'svntex2_render_auth_pages' );
function svntex2_render_auth_pages(){
    $page = get_query_var('svntex2_page');
    if ( ! $page ) return;
    if ( $page === 'login' ) {
        if ( is_user_logged_in() ) { wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) ); exit; }
        wp_enqueue_style( 'svntex2-style' );
        $file = SVNTEX2_PLUGIN_DIR.'views/customer-login.php';
    } elseif ( $page === 'register' ) {
        if ( is_user_logged_in() ) { wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) ); exit; }
        wp_enqueue_style( 'svntex2-style' );
        $file = SVNTEX2_PLUGIN_DIR.'views/customer-registration.php';
    } else { return; }
    status_header(200); nocache_headers();
    if ( file_exists( $file ) ) { include $file; } else { echo '<p>Auth view missing.</p>'; }
    exit;
}

// Redirect wp-login.php to custom page (except special cases)
add_action( 'login_init', function(){
    if ( isset( $_GET['action'] ) && in_array( $_GET['action'], ['lostpassword','rp','resetpass'], true ) ) return; // allow core flows
    wp_safe_redirect( site_url( '/'.SVNTEX2_LOGIN_SLUG.'/' ) );
    exit;
});

// Public registration redirect (if enabled)
add_action( 'register_init', function(){
    if ( ! get_option('users_can_register') ) return; // respect site setting
    wp_safe_redirect( site_url( '/'.SVNTEX2_REGISTER_SLUG.'/' ) );
    exit;
});

// AJAX login handler
add_action( 'wp_ajax_nopriv_svntex2_do_login', 'svntex2_ajax_do_login' );
function svntex2_ajax_do_login(){
    check_ajax_referer( 'svntex2_login', 'svntex2_login_nonce' );
    $login_id = sanitize_text_field( $_POST['login_id'] ?? '' );
    $password = $_POST['password'] ?? '';
    if ( ! $login_id || ! $password ) wp_send_json_error( ['message' => 'Missing credentials'] );

    $user = null;
    if ( is_email( $login_id ) ) {
        $user = get_user_by( 'email', $login_id );
    } else {
        // Try username then meta (customer_id stored as username style)
        $user = get_user_by( 'login', $login_id );
        if ( ! $user ) {
            $q = get_users( [ 'meta_key' => 'customer_id', 'meta_value' => $login_id, 'number' => 1, 'fields' => 'all' ] );
            if ( $q ) $user = $q[0];
        }
    }
    if ( ! $user ) wp_send_json_error( ['message' => 'Account not found'] );
    $check = wp_check_password( $password, $user->user_pass, $user->ID );
    if ( ! $check ) wp_send_json_error( ['message' => 'Invalid password'] );
    $remember = ! empty( $_POST['remember'] );
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, $remember );
    do_action( 'wp_login', $user->user_login, $user );
    $redirect = wc_get_page_permalink( 'myaccount' );
    wp_send_json_success( [ 'redirect' => $redirect ] );
}

// -----------------------------------------------------------------------------
// 10. EXTENSION HOOKS (for future add-ons)
// -----------------------------------------------------------------------------
do_action( 'svntex2_initialized' );

// -----------------------------------------------------------------------------
// 10b. WOO My Account DASHBOARD INJECTION
// -----------------------------------------------------------------------------
/**
 * Automatically inject the SVNTeX dashboard into WooCommerce My Account dashboard
 * so site owners don't need to place the shortcode manually.
 */
add_action( 'init', 'svntex2_hook_wc_account_dashboard' );
function svntex2_hook_wc_account_dashboard() {
    if ( class_exists( 'WooCommerce' ) ) {
        // Priority before most custom additions but after WC base content (default priority 10)
        add_action( 'woocommerce_account_dashboard', 'svntex2_wc_account_inject', 9 );
    }
}

function svntex2_wc_account_inject() {
    if ( ! is_user_logged_in() ) { return; }
    global $svntex2_wc_dash_done; // prevent duplicate rendering if shortcode placed manually as well
    if ( ! empty( $svntex2_wc_dash_done ) ) { return; }
    $svntex2_wc_dash_done = true;
    echo do_shortcode( '[svntex_dashboard]' );
}

// -----------------------------------------------------------------------------
// 11. FALLBACK: CREATE CORE JS IF MISSING (DEV SAFETY)
// -----------------------------------------------------------------------------
if ( ! file_exists( SVNTEX2_PLUGIN_DIR . 'assets/js/core.js' ) ) {
    @wp_mkdir_p( SVNTEX2_PLUGIN_DIR . 'assets/js' );
    @file_put_contents( SVNTEX2_PLUGIN_DIR . 'assets/js/core.js', "console.log('SVNTeX core loaded');" );
}

// End of file.
