<?php
/**
 * Plugin Name: SVNTeX 2.0 Customer System
 * Description: Foundation for SVNTeX 2.0 – registration, wallet ledger, referrals, KYC, withdrawals, PB/RB scaffolding with WooCommerce integration.
 * Version: 0.2.14
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
define( 'SVNTEX2_VERSION',        '0.2.17' );
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
// Products module (CPT + REST + inventory + delivery rules)
$__pfiles = [ 'products.php', 'rest-products.php' ];
foreach ( $__pfiles as $__pf ) {
    $f = SVNTEX2_PLUGIN_DIR . 'includes/functions/' . $__pf;
    if ( file_exists( $f ) ) require_once $f;
}
// Commerce layer (cart + orders) custom
$commerce = SVNTEX2_PLUGIN_DIR . 'includes/functions/commerce.php';
if ( file_exists( $commerce ) ) require_once $commerce;

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

    // Product/Inventory tables
    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_product_variants (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id BIGINT UNSIGNED NOT NULL,
        sku VARCHAR(80) NOT NULL UNIQUE,
        attributes LONGTEXT NULL,
        price DECIMAL(14,2) NULL,
        tax_class VARCHAR(20) NULL,
        unit VARCHAR(12) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY product_id (product_id), KEY active (active), KEY tax_class (tax_class)
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_inventory_locations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(20) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        address TEXT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_inventory_stocks (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        variant_id BIGINT UNSIGNED NOT NULL,
        location_id BIGINT UNSIGNED NOT NULL,
        qty INT NOT NULL DEFAULT 0,
        min_qty INT NOT NULL DEFAULT 0,
        max_qty INT NULL,
        reorder_threshold INT NULL,
        backorder_enabled TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY var_loc (variant_id, location_id),
        KEY qty (qty), KEY backorder_enabled (backorder_enabled)
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_delivery_rules (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scope ENUM('global','product','variant') NOT NULL,
        scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        mode ENUM('fixed','percent') NOT NULL,
        amount DECIMAL(14,4) NOT NULL DEFAULT 0,
        free_threshold DECIMAL(14,2) NULL,
        override_global TINYINT(1) NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY scope_idx (scope, scope_id), KEY active (active)
    ) $charset";

    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_media_links (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        product_id BIGINT UNSIGNED NOT NULL,
        variant_id BIGINT UNSIGNED NULL,
        attachment_id BIGINT UNSIGNED NULL,
        media_type VARCHAR(20) NOT NULL,
        embed_url VARCHAR(255) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY product_id (product_id), KEY variant_id (variant_id), KEY media_type (media_type)
    ) $charset";

    // Orders + order items (simple custom commerce layer)
    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_orders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        items_total DECIMAL(14,2) NOT NULL DEFAULT 0,
        delivery_total DECIMAL(14,2) NOT NULL DEFAULT 0,
        grand_total DECIMAL(14,2) NOT NULL DEFAULT 0,
        address LONGTEXT NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id), KEY status (status), KEY created_at (created_at)
    ) $charset";
    $sql[] = "CREATE TABLE {$wpdb->prefix}svntex_order_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NOT NULL,
        variant_id BIGINT UNSIGNED NULL,
        qty INT NOT NULL DEFAULT 1,
        price DECIMAL(14,2) NOT NULL DEFAULT 0,
        subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
        meta LONGTEXT NULL,
        KEY order_id (order_id), KEY product_id (product_id), KEY variant_id (variant_id)
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
        // New format: SVNXXXXXX (no hyphen)
        $candidate = 'SVN' . str_pad( (string) wp_rand( 0, 999999 ), 6, '0', STR_PAD_LEFT );
        if ( ! username_exists( $candidate ) ) { return $candidate; }
    }
    return 'SVN' . wp_rand( 100000, 999999 );
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
    // Do not treat the WooCommerce My Account page as a "brand UI" here so themes
    // (for example Astra) can manage navigation and header/footer. Use the
    // shortcode or dedicated integrations instead of overriding theme chrome.
    // if ( function_exists( 'is_account_page' ) && is_account_page() && is_user_logged_in() ) return true;
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
    // Extra compatibility for environments that include index.php in pretty permalinks
    add_rewrite_rule( '^index\.php/'.SVNTEX2_LOGIN_SLUG.'/?$', 'index.php?svntex2_page=login', 'top' );
    add_rewrite_rule( '^index\.php/'.SVNTEX2_REGISTER_SLUG.'/?$', 'index.php?svntex2_page=register', 'top' );
    // Dedicated products page (independent of theme shop / WooCommerce)
    add_rewrite_rule( '^svntex-products/?$', 'index.php?svntex2_page=svntex_products', 'top' );
    add_rewrite_tag( '%svntex2_page%', '([^&]+)' );
}

// Ensure rewrites are flushed automatically after plugin version changes,
// so custom auth slugs start working immediately after deploy.
add_action( 'init', function(){
    $stored = get_option( 'svntex2_version' );
    if ( $stored !== SVNTEX2_VERSION ) {
        flush_rewrite_rules( false );
        update_option( 'svntex2_version', SVNTEX2_VERSION );
    }
}, 99 );

// Template loader for custom pages
add_action( 'template_redirect', 'svntex2_render_auth_pages' );
function svntex2_render_auth_pages(){
    $page = get_query_var('svntex2_page');
    // Fallback: detect by REQUEST_URI when rewrites are not active yet
    if ( ! $page ) {
        $req = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        $login_slug = trim(SVNTEX2_LOGIN_SLUG,'/');
        $register_slug = trim(SVNTEX2_REGISTER_SLUG,'/');
        // Match slug at segment boundaries, with or without leading index.php, case-insensitive
        if ( preg_match('#(^|/)(index\.php/)?'.preg_quote($login_slug,'#').'(/|$)#i', $req) ) {
            $page = 'login';
        } elseif ( preg_match('#(^|/)(index\.php/)?'.preg_quote($register_slug,'#').'(/|$)#i', $req) ) {
            $page = 'register';
        }
        if ( $page ) {
            // Set query var so downstream checks (brand UI context, body_class) behave consistently
            set_query_var('svntex2_page', $page);
            if ( isset( $GLOBALS['wp_query'] ) && method_exists( $GLOBALS['wp_query'], 'set' ) ) {
                $GLOBALS['wp_query']->set('svntex2_page', $page);
            }
        }
    }
    if ( ! $page ) return;
    if ( $page === 'login' ) {
        // Only redirect logged-in users away from login page
        if ( is_user_logged_in() ) {
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
    wp_enqueue_style( 'svntex2-style' );
    wp_enqueue_style( 'svntex2-landing' );
        $file = SVNTEX2_PLUGIN_DIR.'views/customer-login.php';
    } elseif ( $page === 'register' ) {
        if ( is_user_logged_in() ) {
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }
    wp_enqueue_style( 'svntex2-style' );
    wp_enqueue_style( 'svntex2-landing' );
        $file = SVNTEX2_PLUGIN_DIR.'views/customer-registration.php';
    } elseif ( $page === 'svntex_products' ) {
        wp_enqueue_style( 'svntex2-style' );
        wp_enqueue_style( 'svntex2-landing' );
        $custom = SVNTEX2_PLUGIN_DIR.'views/products-archive.php';
        if ( file_exists( $custom ) ) {
            status_header(200); nocache_headers(); include $custom; exit; }
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

// Custom single product template for svntex_product (variant selector, dynamic pricing)
add_action('template_redirect', function(){
    if ( is_singular('svntex_product') ) {
        global $post, $wpdb; if ( ! $post ) return; // safety
        wp_enqueue_style( 'svntex2-style' );
        wp_enqueue_style( 'svntex2-landing' );
        // Gather variants
        $vt = $wpdb->prefix.'svntex_product_variants';
        $variants = $wpdb->get_results( $wpdb->prepare("SELECT id, sku, attributes, price, active FROM $vt WHERE product_id=%d AND active=1 ORDER BY id ASC", $post->ID) );
        $v_export = [];
        $attr_map = [];
        foreach( $variants as $v ) {
            $attrs = $v->attributes ? json_decode( $v->attributes, true ) : [];
            if ( is_array($attrs) ) {
                foreach($attrs as $k=>$val){
                    if (!isset($attr_map[$k])) $attr_map[$k] = [];
                    if ($val !== '' && !in_array($val, $attr_map[$k], true)) $attr_map[$k][] = $val;
                }
            } else { $attrs = []; }
            $v_export[] = [
                'id' => (int)$v->id,
                'sku'=> $v->sku,
                'price' => is_null($v->price)? null : (float)$v->price,
                'attributes' => $attrs,
            ];
        }
        // Compute price range
        $range = [ 'min'=>null,'max'=>null ];
        foreach($v_export as $vx){ if($vx['price'] !== null){ if($range['min']===null||$vx['price']<$range['min']) $range['min']=$vx['price']; if($range['max']===null||$vx['price']>$range['max']) $range['max']=$vx['price']; } }
        // Sort attribute values
        foreach($attr_map as $k=>$vals){ sort($attr_map[$k], SORT_NATURAL|SORT_FLAG_CASE); }
        $single_view = SVNTEX2_PLUGIN_DIR.'views/product-single.php';
        status_header(200); nocache_headers();
        ob_start();
        if ( file_exists( $single_view ) ) { include $single_view; } else {
            echo '<p>Single product view missing.</p>';
        }
        $content = ob_get_clean();
        ?><!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width,initial-scale=1" />
            <title><?php echo esc_html( get_the_title( $post ) ); ?> – <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
            <style>
            .svn-single-shell{max-width:1200px;margin:0 auto;padding:clamp(1.25rem,2.5vw,2.25rem) clamp(1rem,2.5vw,2rem);display:grid;gap:2.2rem;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));}
            .svn-gallery{background:linear-gradient(130deg,rgba(255,255,255,.05),rgba(148,163,184,.08));border:1px solid rgba(255,255,255,.08);border-radius:26px;overflow:hidden;min-height:320px;display:flex;align-items:center;justify-content:center;}
            body:not(.dark) .svn-gallery{background:#fff;border-color:#e2e8f0;}
            .svn-gallery img{width:100%;height:100%;object-fit:cover;display:block;}
            .svn-p-summary h1{margin:0 0 1rem;font-size:clamp(1.5rem,3vw,2.4rem);font-weight:700;letter-spacing:.5px;}
            .svn-price-block{display:flex;flex-direction:column;gap:.35rem;margin:1rem 0 1.2rem;}
            .svn-price-main{font-size:clamp(1.2rem,2vw,1.65rem);font-weight:700;background:linear-gradient(90deg,var(--svn-accent),var(--svn-accent-alt));-webkit-background-clip:text;background-clip:text;color:transparent;}
            .svn-price-range{font-size:.75rem;color:var(--svn-text-dim);}
            .svn-variants{display:flex;flex-direction:column;gap:1rem;margin:1.25rem 0;}
            .svn-variant-group label{font-size:.65rem;font-weight:600;text-transform:uppercase;letter-spacing:.6px;display:block;margin-bottom:.4rem;color:var(--svn-text-dim);}
            .svn-variant-group select{width:100%;padding:.7rem .75rem;border-radius:14px;border:1px solid rgba(255,255,255,.24);background:rgba(15,23,42,.6);color:#fff;font-size:.8rem;}
            body:not(.dark) .svn-variant-group select{background:#fff;color:#0f172a;border-color:#cbd5e1;}
            .svn-add-actions{display:flex;gap:.75rem;align-items:center;margin-top:1.25rem;}
            .svn-add-actions button{flex:1;padding:.9rem 1.15rem;border-radius:18px;border:0;font-weight:600;font-size:.8rem;cursor:pointer;background:linear-gradient(90deg,var(--svn-accent),var(--svn-accent-alt));color:#fff;letter-spacing:.5px;}
            .svn-add-actions button[disabled]{opacity:.55;cursor:not-allowed;}
            .svn-meta-small{font-size:.65rem;color:var(--svn-text-dim);margin-top:.5rem;}
            </style>
        </head>
        <body <?php body_class('svntex-landing svntex-product-single'); ?>>
            <div class="svn-single-shell">
                <div class="svn-gallery">
                    <?php if ( has_post_thumbnail( $post ) ){ echo get_the_post_thumbnail( $post->ID, 'large' ); } else { echo '<div style="padding:2rem;color:var(--svn-text-dim);font-size:.8rem;">No Image</div>'; } ?>
                </div>
                <div class="svn-p-summary">
                    <h1><?php echo esc_html( get_the_title( $post ) ); ?></h1>
                    <div class="svn-price-block">
                        <div class="svn-price-main" id="svn-price-display"></div>
                        <div class="svn-price-range" id="svn-price-range"></div>
                    </div>
                    <div class="svn-variants" id="svn-variant-selectors"></div>
                    <div class="svn-add-actions">
                        <button type="button" id="svn-add-cart" disabled data-loading-text="Adding…">Add to Cart</button>
                    </div>
                    <div class="svn-meta-small">SKU: <span id="svn-sku-display">—</span></div>
                    <article class="svn-desc" style="margin-top:2rem;font-size:.85rem;line-height:1.55;">
                        <?php echo apply_filters('the_content', $post->post_content ); ?>
                    </article>
                </div>
            </div>
            <script>
            window.SVNTEX2_PRODUCT = <?php echo wp_json_encode([
                'id'=>$post->ID,
                'variants'=>$v_export,
                'attributes'=>$attr_map,
                'price_range'=>$range,
            ]); ?>;
            (function(){
                const data = window.SVNTEX2_PRODUCT;
                const selWrap = document.getElementById('svn-variant-selectors');
                const priceEl = document.getElementById('svn-price-display');
                const rangeEl = document.getElementById('svn-price-range');
                const skuEl = document.getElementById('svn-sku-display');
                const btn = document.getElementById('svn-add-cart');
                function fmt(p){ if(p==null) return '—'; return (window.wc && window.wc.price) ? window.wc.price(p): '₹'+p.toFixed(2); }
                function renderRange(){
                    if(data.price_range.min===null){ priceEl.textContent='—'; rangeEl.textContent=''; return; }
                    if(data.price_range.min === data.price_range.max){ priceEl.textContent = fmt(data.price_range.min); rangeEl.textContent=''; }
                    else { priceEl.textContent = fmt(data.price_range.min)+' – '+fmt(data.price_range.max); rangeEl.textContent='Select options to see exact price'; }
                }
                function buildSelectors(){
                    const attrs = data.attributes; const keys = Object.keys(attrs);
                    if(!keys.length){ // single variant maybe
                        if(data.variants.length===1){
                            const v=data.variants[0]; priceEl.textContent = fmt(v.price); skuEl.textContent=v.sku; btn.disabled=false; btn.dataset.variantId=v.id;
                        } else { renderRange(); }
                        return;
                    }
                    keys.forEach(k=>{
                        const box=document.createElement('div'); box.className='svn-variant-group';
                        const label=document.createElement('label'); label.textContent=k;
                        const select=document.createElement('select'); select.name='attr_'+k;
                        select.innerHTML='<option value="">Select '+k+'</option>'+attrs[k].map(v=>'\n<option value="'+v.replace(/"/g,'&quot;')+'">'+v+'</option>').join('');
                        select.addEventListener('change',evaluate);
                        box.appendChild(label); box.appendChild(select); selWrap.appendChild(box);
                    });
                    renderRange();
                }
                function evaluate(){
                    const selects = selWrap.querySelectorAll('select');
                    const chosen={}; let allChosen=true; selects.forEach(s=>{ if(s.value){ chosen[s.previousSibling.textContent]=s.value; } else { allChosen=false; } });
                    if(!Object.keys(chosen).length){ renderRange(); skuEl.textContent='—'; btn.disabled=true; delete btn.dataset.variantId; return; }
                    const matched = data.variants.filter(v=>{
                        for(const k in chosen){ if(!v.attributes || v.attributes[k]!==chosen[k]) return false; }
                        return true;
                    });
                    if(matched.length===1 && (allChosen || Object.keys(matched[0].attributes||{}).length===Object.keys(data.attributes).length)){
                        const v=matched[0]; priceEl.textContent=fmt(v.price); rangeEl.textContent=''; skuEl.textContent=v.sku; btn.disabled=false; btn.dataset.variantId=v.id; return; }
                    // multiple or partial matches
                    if(matched.length){
                        let min=null,max=null; matched.forEach(m=>{ if(m.price!=null){ if(min===null||m.price<min) min=m.price; if(max===null||m.price>max) max=m.price; } });
                        if(min!==null){ priceEl.textContent = (min===max)? fmt(min): (fmt(min)+' – '+fmt(max)); rangeEl.textContent = matched.length+' options'; }
                        else { renderRange(); }
                        skuEl.textContent='—'; btn.disabled=true; delete btn.dataset.variantId; return;
                    }
                    // no match
                    priceEl.textContent='Unavailable'; rangeEl.textContent=''; skuEl.textContent='—'; btn.disabled=true; delete btn.dataset.variantId;
                }
                buildSelectors();
                btn.addEventListener('click', function(){
                    if(btn.disabled || btn.dataset.loading) return;
                    const vid = btn.dataset.variantId; if(!vid){ return; }
                    const orig = btn.textContent; btn.dataset.loading='1'; btn.textContent=btn.getAttribute('data-loading-text')||'Adding…';
                    fetch('<?php echo esc_url_raw( rest_url('svntex2/v1/cart/add') ); ?>', {
                        method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ product_id: data.id, variant_id: vid, qty: 1 })
                    }).then(r=>r.json()).then(j=>{ btn.textContent='Added'; setTimeout(()=>{ btn.textContent=orig; delete btn.dataset.loading; },1200); })
                    .catch(()=>{ btn.textContent='Error'; setTimeout(()=>{ btn.textContent=orig; delete btn.dataset.loading; },1400); });
                });
            })();
            </script>
            <?php wp_footer(); ?>
        </body>
        </html><?php
        exit;
    }
});

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
   
    // Allow site owners or themes to opt-in to the SVNTeX override. By default
    // we defer to the active theme (Astra) so theme-managed navigation and
    // account menus are preserved. Site owners can re-enable our override by
    // returning true from this filter.
    if ( ! apply_filters('svntex2_override_my_account', false) ) return;
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
    // If we get here and the filter returned true, render the dashboard but do
    // not aggressively hide theme header/footer; let theme CSS handle
    // visibility so Astra can style navigation consistently.
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
        // Support both legacy "SVN-XXXXXX" and new "SVNXXXXXX" formats
        $variants = [ $login_id ];
        if ( preg_match('/^SVN-(\d{6})$/i', $login_id, $m) ) {
            $variants[] = 'SVN' . $m[1];
        } elseif ( preg_match('/^SVN(\d{6})$/i', $login_id, $m) ) {
            $variants[] = 'SVN-' . $m[1];
        }

        // Try username (user_login) first for each variant
        foreach ( array_unique($variants) as $try ) {
            $user = get_user_by( 'login', $try );
            error_log('SVNTEX2 AJAX LOGIN: Try login variant=' . $try . ', found user=' . print_r($user, true));
            if ( $user ) break;
        }
        // Then try meta customer_id for each variant
        if ( ! $user ) {
            foreach ( array_unique($variants) as $try ) {
                $q = get_users( [ 'meta_key' => 'customer_id', 'meta_value' => $try, 'number' => 1, 'fields' => 'all' ] );
                error_log('SVNTEX2 AJAX LOGIN: Try meta customer_id variant=' . $try . ', found=' . print_r($q, true));
                if ( $q ) { $user = $q[0]; break; }
            }
        }
        // Finally try mobile meta if input looks like a phone
        if ( ! $user ) {
            $maybe_mobile = preg_replace('/\D/','', $login_id);
            if ( strlen($maybe_mobile) >= 10 ) {
                $q = get_users( [ 'meta_key' => 'mobile', 'meta_value' => $maybe_mobile, 'number' => 1, 'fields' => 'all' ] );
                error_log('SVNTEX2 AJAX LOGIN: Try mobile meta=' . $maybe_mobile . ', found=' . print_r($q, true));
                if ( $q ) { $user = $q[0]; }
            }
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

/**
 * Programmatically create a simple "SVNTeX Account" menu and add account links.
 * This is admin-only and idempotent. Enable by returning true from
 * `add_filter('svntex2_auto_add_menu_items', '__return_true');` in a site
 * mu-plugin or theme functions.php, or call `svntex2_register_astra_account_menu_items()` manually.
 */
function svntex2_get_logout_url_for_menu() {
    return esc_url( admin_url( 'admin-post.php?action=svntex2_logout' ) );
}

function svntex2_register_astra_account_menu_items() {
    if ( ! is_admin() ) return; // admin-only operation
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Only run automatically if filter enabled or when called directly
    $auto = apply_filters( 'svntex2_auto_add_menu_items', false );
    $already = get_option( 'svntex2_menu_items_added', 0 );
    if ( ! $auto && ! defined( 'SVNTEX2_FORCE_ADD_MENU' ) ) return;
    if ( $already && ! defined( 'SVNTEX2_FORCE_ADD_MENU' ) ) return;

    $menu_name = apply_filters( 'svntex2_menu_name', 'SVNTeX Account' );
    $menu_exists = wp_get_nav_menu_object( $menu_name );
    if ( ! $menu_exists ) {
        $menu_id = wp_create_nav_menu( $menu_name );
    } else {
        $menu_id = $menu_exists->term_id;
    }

    if ( ! $menu_id ) return;

    // Desired items
    $base = home_url('/dashboard');
    $items = apply_filters( 'svntex2_menu_items', [
        [ 'title' => 'Home', 'url' => $base ],
        [ 'title' => 'Wallet', 'url' => $base . '#wallet' ],
        [ 'title' => 'Top Up', 'url' => $base ],
        [ 'title' => 'Purchases', 'url' => $base . '#purchases' ],
        [ 'title' => 'Referrals', 'url' => $base . '#referrals' ],
    [ 'title' => 'KYC', 'url' => $base . '#kyc' ],
    [ 'title' => 'Products', 'url' => home_url('/svntex-products/') ],
        [ 'title' => 'Logout', 'url' => svntex2_get_logout_url_for_menu() ],
    ] );

    // Fetch existing menu items to avoid duplicates
    $existing = wp_get_nav_menu_items( $menu_id ) ?: [];
    $existing_urls = array_map( function( $it ) { return untrailingslashit( $it->url ); }, $existing );

    foreach ( $items as $it ) {
        $url = untrailingslashit( $it['url'] );
        if ( in_array( $url, $existing_urls, true ) ) continue; // skip duplicates
        wp_update_nav_menu_item( $menu_id, 0, [
            'menu-item-title' => $it['title'],
            'menu-item-url' => $it['url'],
            'menu-item-status' => 'publish',
        ] );
    }

    // Try to assign the menu to a sensible theme location if available
    $locations = get_nav_menu_locations();
    $registered = get_registered_nav_menus();
    // Prefer common Astra primary location keys
    $preferred = [ 'primary', 'header', 'main', key( $registered ) ?: '' ];
    foreach ( $preferred as $loc ) {
        if ( ! $loc ) continue;
        if ( array_key_exists( $loc, $registered ) ) {
            $locations[ $loc ] = $menu_id;
            set_theme_mod( 'nav_menu_locations', $locations );
            break;
        }
    }

    update_option( 'svntex2_menu_items_added', time() );
}

add_action( 'admin_init', 'svntex2_register_astra_account_menu_items' );

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

    // Product/Inventory tables safety for upgrades
    $charset = $wpdb->get_charset_collate();
    $tbl_variants = $pref.'svntex_product_variants';
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $tbl_variants) ) !== $tbl_variants ) {
        $wpdb->query("CREATE TABLE $tbl_variants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            sku VARCHAR(80) NOT NULL UNIQUE,
            attributes LONGTEXT NULL,
            price DECIMAL(14,2) NULL,
            tax_class VARCHAR(20) NULL,
            unit VARCHAR(12) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY product_id (product_id), KEY active (active), KEY tax_class (tax_class)
        ) $charset");
    }
    $tbl_locations = $pref.'svntex_inventory_locations';
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $tbl_locations) ) !== $tbl_locations ) {
        $wpdb->query("CREATE TABLE $tbl_locations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            address TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $charset");
    }
    $tbl_stocks = $pref.'svntex_inventory_stocks';
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $tbl_stocks) ) !== $tbl_stocks ) {
        $wpdb->query("CREATE TABLE $tbl_stocks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            variant_id BIGINT UNSIGNED NOT NULL,
            location_id BIGINT UNSIGNED NOT NULL,
            qty INT NOT NULL DEFAULT 0,
            min_qty INT NOT NULL DEFAULT 0,
            max_qty INT NULL,
            reorder_threshold INT NULL,
            backorder_enabled TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY var_loc (variant_id, location_id),
            KEY qty (qty), KEY backorder_enabled (backorder_enabled)
        ) $charset");
    }
    $tbl_rules = $pref.'svntex_delivery_rules';
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $tbl_rules) ) !== $tbl_rules ) {
        $wpdb->query("CREATE TABLE $tbl_rules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            scope ENUM('global','product','variant') NOT NULL,
            scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            mode ENUM('fixed','percent') NOT NULL,
            amount DECIMAL(14,4) NOT NULL DEFAULT 0,
            free_threshold DECIMAL(14,2) NULL,
            override_global TINYINT(1) NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY scope_idx (scope, scope_id), KEY active (active)
        ) $charset");
    }
    $tbl_media = $pref.'svntex_media_links';
    if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $tbl_media) ) !== $tbl_media ) {
        $wpdb->query("CREATE TABLE $tbl_media (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NULL,
            attachment_id BIGINT UNSIGNED NULL,
            media_type VARCHAR(20) NOT NULL,
            embed_url VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY product_id (product_id), KEY variant_id (variant_id), KEY media_type (media_type)
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
    // Find referrer by login (customer id) with legacy/new variants, or email fallback
    $referrer_user = null;
    $variants = [ $ref_source ];
    if ( preg_match('/^SVN-(\d{6})$/i', $ref_source, $m) ) {
        $variants[] = 'SVN' . $m[1];
    } elseif ( preg_match('/^SVN(\d{6})$/i', $ref_source, $m) ) {
        $variants[] = 'SVN-' . $m[1];
    }
    foreach ( array_unique($variants) as $try ) {
        $referrer_user = get_user_by( 'login', $try );
        if ( $referrer_user ) break;
    }
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
    $referrer_user = null;
    $variants = [ $ref_source ];
    if ( preg_match('/^SVN-(\d{6})$/i', $ref_source, $m) ) {
        $variants[] = 'SVN' . $m[1];
    } elseif ( preg_match('/^SVN(\d{6})$/i', $ref_source, $m) ) {
        $variants[] = 'SVN-' . $m[1];
    }
    foreach ( array_unique($variants) as $try ) {
        $referrer_user = get_user_by( 'login', $try );
        if ( $referrer_user ) break;
    }
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

// -----------------------------------------------------------------------------
// Ensure My Account page outputs our dashboard markup regardless of theme.
// This is defensive: some themes render headings/content before 'template_redirect'
// replacement. We replace the main query content for the WC My Account page with
// our shortcode so desktop/tablet/mobile always show SVNTeX dashboard.
// -----------------------------------------------------------------------------
add_filter( 'the_content', 'svntex2_replace_myaccount_with_dashboard', 20 );
function svntex2_replace_myaccount_with_dashboard( $content ) {
    if ( is_admin() ) return $content;
    // Only act on main query to avoid affecting nested loops
    if ( ! is_main_query() ) return $content;
    if ( function_exists( 'is_account_page' ) && is_account_page() && is_user_logged_in() ) {
        // Return our dashboard markup (shortcode ensures assets and scripts load)
        return do_shortcode( '[svntex_dashboard]' );
    }
    return $content;
}

function svntex2_wc_account_inject() {
    if ( ! is_user_logged_in() ) { return; }
    global $svntex2_wc_dash_done; // prevent duplicate rendering if shortcode placed manually as well
    if ( ! empty( $svntex2_wc_dash_done ) ) { return; }
    $svntex2_wc_dash_done = true;
    echo do_shortcode( '[svntex_dashboard]' );
}

// -----------------------------------------------------------------------------
// Remove default WooCommerce My Account menu items so our custom dashboard is the
// only visible navigation option. Returning an empty array hides the default
// links regardless of theme/template output (defensive server-side approach).
// -----------------------------------------------------------------------------
add_filter( 'woocommerce_account_menu_items', 'svntex2_override_wc_account_menu_items', 100 );
function svntex2_override_wc_account_menu_items( $items ) {
    // Keep this filter minimal: return empty to prevent theme-rendered menu items.
    return array();
}

// -----------------------------------------------------------------------------
// 11. FALLBACK: CREATE CORE JS IF MISSING (DEV SAFETY)
// -----------------------------------------------------------------------------
if ( ! file_exists( SVNTEX2_PLUGIN_DIR . 'assets/js/core.js' ) ) {
    @wp_mkdir_p( SVNTEX2_PLUGIN_DIR . 'assets/js' );
    @file_put_contents( SVNTEX2_PLUGIN_DIR . 'assets/js/core.js', "console.log('SVNTeX core loaded');" );
}


// -----------------------------------------------------------------------------
