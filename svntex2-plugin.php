<?php
/**
 * Plugin Name: SVNTeX 2.0 Customer System
 * Description: Foundation for SVNTeX 2.0 – registration, wallet ledger, referrals, KYC, withdrawals, PB/RB scaffolding with WooCommerce integration.
 * Version: 0.2.5
 * Author: SVNTeX
 * Text Domain: svntex2
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 *
 * Roadmap / Pending feature logic: see docs/MISSING_FEATURES.md (updated regularly).
 * High-level architecture diagram included in above document for quick onboarding.
 */

// -----------------------------------------------------------------------------
// 0. HARD EXIT IF NOT IN WORDPRESS
// -----------------------------------------------------------------------------
if ( ! defined( 'ABSPATH' ) ) { exit; }

// -----------------------------------------------------------------------------
// 1. CONSTANTS
// -----------------------------------------------------------------------------
define( 'SVNTEX2_VERSION',        '0.2.5' );
define( 'SVNTEX2_PLUGIN_FILE',    __FILE__ );
define( 'SVNTEX2_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'SVNTEX2_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'SVNTEX2_MIN_WC_VERSION', '7.0.0' );
// Default referral commission rate (0 = disabled). Make dynamic via filter 'svntex2_referral_commission_rate'.
if ( ! defined( 'SVNTEX2_REFERRAL_RATE' ) ) {
    define( 'SVNTEX2_REFERRAL_RATE', 0.10 ); // 10% referral commission
}
// Referral Bonus (RB) rate on referee first qualifying action (first order or first wallet top-up)
if ( ! defined( 'SVNTEX2_REFERRAL_BONUS_RATE' ) ) {
    define( 'SVNTEX2_REFERRAL_BONUS_RATE', 0.10 ); // 10% one-time bonus
}
// Financial charge rates
if ( ! defined( 'SVNTEX2_TDS_RATE' ) ) {
    define( 'SVNTEX2_TDS_RATE', 0.02 ); // 2% TDS
}
if ( ! defined( 'SVNTEX2_AMC_RATE' ) ) {
    define( 'SVNTEX2_AMC_RATE', 0.08 ); // 8% maintenance charge
}

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
foreach ( [ 'referrals', 'kyc', 'withdrawals', 'pb', 'cron', 'admin', 'cli' ] as $module ) {
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

    // Admin-entered monthly profit input components (revenue, wallet remaining, cogs, maintenance %)
    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_profit_inputs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        month_year CHAR(7) NOT NULL UNIQUE,
        revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
        remaining_wallet DECIMAL(14,2) NOT NULL DEFAULT 0,
        cogs DECIMAL(14,2) NOT NULL DEFAULT 0,
        maintenance_percent DECIMAL(5,4) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL
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

    // Suspense table for withheld PB payouts (KYC pending / suspended etc.)
    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_pb_suspense (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        month_year CHAR(7) NOT NULL,
        slab_percent DECIMAL(5,2) NULL,
        amount DECIMAL(14,2) NOT NULL,
        reason VARCHAR(40) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'held',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        released_at DATETIME NULL,
        KEY user_month (user_id, month_year),
        KEY status (status)
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
    // Landing / brand extension stylesheet (used for auth + landing + dashboard polish)
    wp_register_style( 'svntex2-landing', SVNTEX2_PLUGIN_URL . 'assets/css/landing.css', [ 'svntex2-style' ], SVNTEX2_VERSION );
}

/**
 * Determine if current request is within any SVNTeX brand UI context.
 * Central place so future pages (withdrawal UI, KYC wizard, etc.) can be added once.
 */
function svntex2_is_brand_ui_context() : bool {
    if ( is_admin() ) return false;
    $auth_page = get_query_var( 'svntex2_page' );
    if ( $auth_page === 'login' || $auth_page === 'register' ) return true;
    if ( is_front_page() || is_home() ) return true; // landing
    // My Account dashboard injection (presence of WooCommerce account page + logged in)
    if ( function_exists( 'is_account_page' ) && is_account_page() && is_user_logged_in() ) return true;
    return false;
}

// Unified enqueue – ensures BOTH primary and landing design layers always present in brand contexts.
add_action( 'wp_enqueue_scripts', function(){
    if ( ! svntex2_is_brand_ui_context() ) return;
    wp_enqueue_style( 'svntex2-style' );
    wp_enqueue_style( 'svntex2-landing' ); // gradient / feature card tokens reused
    // Provide a JS settings object (extensible) once
    wp_register_script( 'svntex2-brand-init', SVNTEX2_PLUGIN_URL . 'assets/js/brand-init.js', [], SVNTEX2_VERSION, true );
    wp_add_inline_script( 'svntex2-brand-init', 'window.SVNTEX2_BRAND = { version: "'.esc_js( SVNTEX2_VERSION ).'" };' );
    wp_enqueue_script( 'svntex2-brand-init' );
}, 20 );

// Add global body class for styling hooks
add_filter( 'body_class', function( $classes ) {
    if ( svntex2_is_brand_ui_context() ) { $classes[] = 'svntex-app-shell'; }
    return $classes;
});

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
    'pb_meta_url' => esc_url_raw( rest_url( 'svntex2/v1/pb/meta' ) ),
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
        // Only redirect logged-in users away from login page
        if ( is_user_logged_in() ) {
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
        wp_enqueue_style( 'svntex2-style' );
        $file = SVNTEX2_PLUGIN_DIR.'views/customer-login.php';
    } elseif ( $page === 'register' ) {
        if ( is_user_logged_in() ) {
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
        wp_enqueue_style( 'svntex2-style' );
        $file = SVNTEX2_PLUGIN_DIR.'views/customer-registration.php';
    } else { return; }
    status_header(200); nocache_headers();
    // Capture template output so we can wrap with full HTML (ensures wp_head/wp_footer fire and styles load)
    ob_start();
    if ( file_exists( $file ) ) { include $file; } else { echo '<p>Auth view missing.</p>'; }
    $content = ob_get_clean();
    ?><!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <?php wp_head(); ?>
    </head>
    <body <?php body_class('svntex-app-shell'); ?>>
        <?php echo $content; ?>
        <?php wp_footer(); ?>
    </body>
    </html><?php
    exit;
}

// Front page landing override (simple) – render modern landing if is home/front
add_action('template_redirect', function(){
    // Force landing page for ALL users on homepage, regardless of theme/static page or login status
    if ( is_front_page() || is_home() || $_SERVER['REQUEST_URI'] === '/' ) {
        $file = SVNTEX2_PLUGIN_DIR.'views/landing.php';
        if ( file_exists( $file ) ) {
            status_header(200); nocache_headers(); include $file; exit; }
    }
});

// -----------------------------------------------------------------------------
// 9c. OVERRIDE WOO MY ACCOUNT PAGE -> DEDICATED SVNTeX DASHBOARD (hide WC UI)
// -----------------------------------------------------------------------------
add_action('template_redirect', function(){
    if ( ! function_exists('is_account_page') ) return;
    if ( ! is_account_page() ) return;
    if ( ! is_user_logged_in() ) return; // let login redirect logic handle guests
        // Prevent redirect loop: if already on My Account page, do not redirect
        if ( untrailingslashit(home_url($_SERVER['REQUEST_URI'])) === untrailingslashit(wc_get_page_permalink('myaccount')) ) return;
   
    // Allow site owners to disable override via filter
    if ( ! apply_filters('svntex2_override_my_account', true) ) return;
        // Mobile redirect: always show dashboard, never WooCommerce My Account
        // But allow logout to work (detect logout URL more robustly)
        if ( wp_is_mobile() ) {
            $request_uri = $_SERVER['REQUEST_URI'] ?? '';
            // Never redirect if logging out or accessing login page (case-insensitive, robust)
            $logout_patterns = ['action=logout','/wp-login.php','/logout','loggedout','customer-login','wp-json','admin-ajax.php'];
            foreach($logout_patterns as $pat) {
                if ( stripos($request_uri, $pat) !== false ) return;
            }
               error_log('SVNTEX2 REDIRECT: Mobile detected, redirecting to My Account: ' . wc_get_page_permalink('myaccount'));
                wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }
    wp_enqueue_style( 'svntex2-style' );
    wp_enqueue_script( 'svntex2-dashboard' );
    wp_localize_script( 'svntex2-dashboard', 'SVNTEX2Dash', [
        'rest_url' => esc_url_raw( rest_url( 'svntex2/v1/wallet/balance' ) ),
        'pb_meta_url' => esc_url_raw( rest_url( 'svntex2/v1/pb/meta' ) ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
    ] );
    status_header(200); nocache_headers();
    ?><!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <?php wp_head(); ?>
        <style>
            /* Ensure any residual WooCommerce containers are hidden if printed by other plugins */
            .woocommerce-MyAccount-navigation, .woocommerce-MyAccount-content, .woocommerce nav.woocommerce-MyAccount-navigation { display:none !important; }
            body.svntex-app-shell { overflow-x:hidden; }
        </style>
    </head>
    <body <?php body_class('svntex-app-shell svntex-myaccount-override'); ?>>
        <main class="svntex-dashboard-override" style="min-height:100vh;display:flex;flex-direction:column;">
            <?php echo do_shortcode('[svntex_dashboard]'); ?>
        </main>
        <?php wp_footer(); ?>
    </body>
    </html><?php
    exit;
});

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
    // Debug: log all POST data and steps
    error_log('SVNTEX2 AJAX LOGIN: POST=' . print_r($_POST, true));
    check_ajax_referer( 'svntex2_login', 'svntex2_login_nonce' );
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rl_key = 'svntex2_login_rl_' . md5( $ip );
    $attempts = (int) get_transient( $rl_key );
    $login_id = sanitize_text_field( $_POST['login_id'] ?? '' );
    $password = $_POST['password'] ?? '';
    if ( ! $login_id || ! $password ) {
        error_log('SVNTEX2 AJAX LOGIN: Missing credentials');
        wp_send_json_error( ['message' => 'Missing credentials'] );
    }

    $user = null;
    if ( is_email( $login_id ) ) {
        $user = get_user_by( 'email', $login_id );
        error_log('SVNTEX2 AJAX LOGIN: Try email, found user=' . print_r($user, true));
    } else {
        $user = get_user_by( 'login', $login_id );
        error_log('SVNTEX2 AJAX LOGIN: Try login, found user=' . print_r($user, true));
        if ( ! $user ) {
            $q = get_users( [ 'meta_key' => 'customer_id', 'meta_value' => $login_id, 'number' => 1, 'fields' => 'all' ] );
            error_log('SVNTEX2 AJAX LOGIN: Try meta customer_id, found=' . print_r($q, true));
            if ( $q ) $user = $q[0];
        }
    }
    if ( ! $user ) {
        error_log('SVNTEX2 AJAX LOGIN: Account not found for login_id=' . $login_id);
        wp_send_json_error( ['message' => 'Account not found'] );
    }
    $check = wp_check_password( $password, $user->user_pass, $user->ID );
    error_log('SVNTEX2 AJAX LOGIN: Password check=' . ($check ? 'PASS' : 'FAIL'));
    if ( ! $check ) {
        $attempts++;
        set_transient( $rl_key, $attempts, 10 * MINUTE_IN_SECONDS );
        error_log('SVNTEX2 AJAX LOGIN: Invalid password, attempts=' . $attempts);
        if ( $attempts > 20 ) {
            error_log('SVNTEX2 AJAX LOGIN: Too many attempts');
            wp_send_json_error( ['message' => 'Too many attempts. Try later.'] );
        }
        wp_send_json_error( ['message' => 'Invalid password'] );
    }
    delete_transient( $rl_key );
    $remember = ! empty( $_POST['remember'] );
    error_log('SVNTEX2 AJAX LOGIN: Setting auth cookie for user_id=' . $user->ID);
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, $remember );
    do_action( 'wp_login', $user->user_login, $user );
    $redirect = wc_get_page_permalink( 'myaccount' );
    error_log('SVNTEX2 AJAX LOGIN: Success, redirect=' . $redirect);
    wp_send_json_success( [ 'redirect' => $redirect ] );
}

// -----------------------------------------------------------------------------
// 11.1 SERVER-SIDE LOGOUT ENDPOINT
// Provide a simple, hard server endpoint that performs a canonical logout and
// redirects users to the plugin login page. Using admin-post avoids client-side
// interception and makes testing the logout behaviour easier.
// -----------------------------------------------------------------------------
add_action( 'admin_post_nopriv_svntex2_logout', 'svntex2_handle_logout' );
add_action( 'admin_post_svntex2_logout', 'svntex2_handle_logout' );
function svntex2_handle_logout() {
    // Perform core logout flow
    if ( function_exists( 'wp_logout' ) ) {
        wp_logout();
    }
    // Also attempt to clear PHP session if present (defensive)
    if ( function_exists( 'session_status' ) && session_status() === PHP_SESSION_ACTIVE ) {
        // Clear session array and destroy
        $_SESSION = [];
        @session_destroy();
    }
    // Redirect to plugin login slug
    wp_safe_redirect( home_url( '/'. SVNTEX2_LOGIN_SLUG . '/' ) );
    exit;
}

// -----------------------------------------------------------------------------
// 10. EXTENSION HOOKS (for future add-ons)
// -----------------------------------------------------------------------------
do_action( 'svntex2_initialized' );

// -----------------------------------------------------------------------------
// 10.1 RUNTIME SCHEMA SAFETY (add new columns if missing)
// -----------------------------------------------------------------------------
add_action( 'plugins_loaded', function(){
    global $wpdb; $pref = $wpdb->prefix;
    // Add category column to wallet transactions if missing
    $wallet_table = $pref.'svntex_wallet_transactions';
    $col = $wpdb->get_results( $wpdb->prepare("SHOW COLUMNS FROM $wallet_table LIKE %s", 'category') );
    if ( ! $col ) {
        $wpdb->query( "ALTER TABLE $wallet_table ADD COLUMN category VARCHAR(20) NOT NULL DEFAULT 'general' AFTER type" );
    }
    // Add fee columns to withdrawals if missing
    $wd_table = $pref.'svntex_withdrawals';
    $fee_col = $wpdb->get_results( $wpdb->prepare("SHOW COLUMNS FROM $wd_table LIKE %s", 'tds_amount') );
    if ( ! $fee_col ) {
        $wpdb->query( "ALTER TABLE $wd_table ADD COLUMN tds_amount DECIMAL(12,2) NULL AFTER amount" );
        $wpdb->query( "ALTER TABLE $wd_table ADD COLUMN amc_amount DECIMAL(12,2) NULL AFTER tds_amount" );
        $wpdb->query( "ALTER TABLE $wd_table ADD COLUMN net_amount DECIMAL(12,2) NULL AFTER amc_amount" );
    }
    // Add suspense table if missing (safety for upgrades)
    $suspense_table = $pref.'svntex_pb_suspense';
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $suspense_table) ) !== $suspense_table ) {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE $suspense_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            month_year CHAR(7) NOT NULL,
            slab_percent DECIMAL(5,2) NULL,
            amount DECIMAL(14,2) NOT NULL,
            reason VARCHAR(40) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'held',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            released_at DATETIME NULL,
            KEY user_month (user_id, month_year),
            KEY status (status)
        ) $charset");
    }
    // Profit inputs table safety
    $profit_inputs_table = $pref.'svntex_profit_inputs';
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $profit_inputs_table) ) !== $profit_inputs_table ) {
        $charset = $wpdb->get_charset_collate();
        $wpdb->query("CREATE TABLE $profit_inputs_table (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            month_year CHAR(7) NOT NULL UNIQUE,
            revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
            remaining_wallet DECIMAL(14,2) NOT NULL DEFAULT 0,
            cogs DECIMAL(14,2) NOT NULL DEFAULT 0,
            maintenance_percent DECIMAL(5,4) NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL
        ) $charset");
    }
} );

// -----------------------------------------------------------------------------
// 10a. REFERRAL QUALIFICATION & COMMISSION ON ORDER COMPLETE
// -----------------------------------------------------------------------------
add_action( 'woocommerce_order_status_completed', function( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) return;
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;
    $user_id = $order->get_user_id();
    if ( ! $user_id ) return;
    $ref_source = get_user_meta( $user_id, 'referral_source', true );
    if ( ! $ref_source ) return;
    // Find referrer by login (customer id) or email fallback
    $referrer_user = get_user_by( 'login', $ref_source );
    if ( ! $referrer_user ) $referrer_user = get_user_by( 'email', $ref_source );
    if ( ! $referrer_user ) return;
    $referrer_id = (int) $referrer_user->ID;
    // Link if fresh
    svntex2_referrals_link( $referrer_id, $user_id );
    // Ensure not processed before
    if ( get_post_meta( $order_id, '_svntex2_referral_processed', true ) ) return;
    $order_total = (float) $order->get_total();
    $shipping_total = (float) $order->get_shipping_total();
    $net_total = $order_total - $shipping_total; // exclude delivery charges
    if ( $net_total <= 0 ) return;
    // Qualification requires net_total >= 2400
    if ( $net_total >= 2400 ) {
        svntex2_referrals_mark_qualified( $referrer_id, $user_id, $net_total );
    }
    // Determine dynamic rate using tiered slabs then allow override via filter
    if ( function_exists('svntex2_referrals_get_commission_rate') ) {
        $base_rate = svntex2_referrals_get_commission_rate( $referrer_id, $order, $user_id );
    } else {
        $base_rate = SVNTEX2_REFERRAL_RATE;
    }
    $rate = (float) apply_filters( 'svntex2_referral_commission_rate', $base_rate, $order, $referrer_id, $user_id );
    if ( $rate > 0 ) {
        // Commission base uses net_total (order total minus shipping) to align with qualification logic
        $commission = round( $net_total * $rate, 2 );
        if ( $commission > 0 ) {
            svntex2_wallet_add_transaction( $referrer_id, 'referral_commission', $commission, 'order:'.$order_id, [ 'referee' => $user_id, 'rate' => $rate, 'base' => $net_total ], 'income' );
        }
    }
    // One-time Referral Bonus (RB) if not yet awarded and first qualifying event is this order
    if ( ! get_user_meta( $user_id, '_svntex2_rb_awarded', true ) ) {
        $rb_rate = (float) apply_filters( 'svntex2_referral_bonus_rate', SVNTEX2_REFERRAL_BONUS_RATE, $user_id, $referrer_id, $net_total, 'order' );
        if ( $rb_rate > 0 ) {
            $rb_bonus = round( $net_total * $rb_rate, 2 );
            if ( $rb_bonus > 0 ) {
                svntex2_wallet_add_transaction( $referrer_id, 'referral_bonus', $rb_bonus, 'rb_order:'.$order_id, [ 'referee' => $user_id, 'base_amount' => $net_total, 'rate' => $rb_rate, 'trigger' => 'order' ], 'income' );
                update_user_meta( $user_id, '_svntex2_rb_awarded', 1 );
            }
        }
    }
    update_post_meta( $order_id, '_svntex2_referral_processed', 1 );
}, 30 );

// -----------------------------------------------------------------------------
// 10a2. REFERRAL BONUS (RB) ON FIRST WALLET TOP-UP (non-order credit)
// -----------------------------------------------------------------------------
/**
 * If a referee (user who was referred) performs their first wallet top-up (any positive credit
 * transaction of a defined type) we give the referrer a one-time RB = 10% of that credited amount.
 * We assume top-ups use transaction type 'wallet_topup' (adjust if different). Filter allows changes.
 */
add_action( 'svntex2_wallet_transaction_created', function( $user_id, $type, $amount, $reference_id, $meta, $balance_after ) {
    if ( $type !== 'wallet_topup' ) return; // Only react to explicit top-up events
    if ( $amount <= 0 ) return;
    // Already processed a referral bonus for this user?
    if ( get_user_meta( $user_id, '_svntex2_rb_awarded', true ) ) return;
    $ref_source = get_user_meta( $user_id, 'referral_source', true );
    if ( ! $ref_source ) return;
    $referrer_user = get_user_by( 'login', $ref_source );
    if ( ! $referrer_user ) $referrer_user = get_user_by( 'email', $ref_source );
    if ( ! $referrer_user ) return;
    $referrer_id = (int) $referrer_user->ID;
    // Link if not yet
    svntex2_referrals_link( $referrer_id, $user_id );
    // If top-up itself meets qualification threshold (>=2400) mark referral qualified
    if ( $amount >= 2400 ) {
        svntex2_referrals_mark_qualified( $referrer_id, $user_id, $amount );
    }
    // Calculate bonus
    $rate = (float) apply_filters( 'svntex2_referral_bonus_rate', SVNTEX2_REFERRAL_BONUS_RATE, $user_id, $referrer_id, $amount, $type );
    if ( $rate <= 0 ) return;
    $bonus = round( $amount * $rate, 2 );
    if ( $bonus <= 0 ) return;
    svntex2_wallet_add_transaction( $referrer_id, 'referral_bonus', $bonus, 'rb:'.$reference_id, [ 'referee' => $user_id, 'base_amount' => $amount, 'rate' => $rate, 'trigger' => 'topup' ], 'income' );
    update_user_meta( $user_id, '_svntex2_rb_awarded', 1 );
}, 20, 6 );

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


// -----------------------------------------------------------------------------
