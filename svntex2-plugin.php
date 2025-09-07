<?php
/*
Plugin Name: SVNTeX 2.0 Customer System
Description: Core infrastructure for SVNTeX 2.0 (registration, wallet, referrals, KYC, withdrawals, PB/RB scaffolding).
Version: 0.1.0
Author: SVNTeX
Text Domain: svntex2
*/

if (!defined('ABSPATH')) { exit; }

define('SVNTEX2_VERSION', '0.1.0');
define('SVNTEX2_PLUGIN_FILE', __FILE__);
define('SVNTEX2_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SVNTEX2_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoload simple classes (PSR-4 like light)
spl_autoload_register(function($class){
    if (strpos($class, 'SVNTEX2_') === 0) {
        $file = SVNTEX2_PLUGIN_DIR . 'includes/classes/' . strtolower(str_replace('SVNTEX2_', '', $class)) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

// Include functional modules
require_once SVNTEX2_PLUGIN_DIR . 'includes/functions/helpers.php';
require_once SVNTEX2_PLUGIN_DIR . 'includes/functions/shortcodes.php';
require_once SVNTEX2_PLUGIN_DIR . 'includes/functions/rest.php';

// Activation / Deactivation
register_activation_hook(__FILE__, 'svntex2_activate');
register_deactivation_hook(__FILE__, 'svntex2_deactivate');

function svntex2_activate(){
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $tables = [];
    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_wallet_transactions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        type VARCHAR(32) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,
        reference_id VARCHAR(64) NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id),
        KEY type (type),
        KEY created_at (created_at)
    ) $charset";

    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_referrals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        referrer_id BIGINT UNSIGNED NOT NULL,
        referee_id BIGINT UNSIGNED NOT NULL,
        qualified TINYINT(1) NOT NULL DEFAULT 0,
        first_purchase_amount DECIMAL(12,2) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY pair (referrer_id, referee_id),
        KEY referrer_id (referrer_id)
    ) $charset";

    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_kyc_submissions (
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

    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_withdrawals (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'requested',
        method VARCHAR(30) NULL,
        destination VARCHAR(150) NULL,
        admin_note TEXT NULL,
        requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        processed_at DATETIME NULL,
        KEY user_id (user_id),
        KEY status (status)
    ) $charset";

    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_profit_distributions (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        month_year CHAR(7) NOT NULL,
        company_profit DECIMAL(14,2) NOT NULL,
        eligible_members INT UNSIGNED NOT NULL,
        profit_value DECIMAL(14,4) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY month_year (month_year)
    ) $charset";

    $tables[] = "CREATE TABLE {$wpdb->prefix}svntex_pb_payouts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        month_year CHAR(7) NOT NULL,
        slab_percent DECIMAL(5,2) NOT NULL,
        payout_amount DECIMAL(14,2) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_month (user_id, month_year)
    ) $charset";

    foreach($tables as $sql){
        dbDelta($sql);
    }

    // Add option for version tracking
    update_option('svntex2_version', SVNTEX2_VERSION);
}

function svntex2_deactivate(){
    // Intentionally keep data. Could add cron cleanup stop here later.
}

// Enqueue global assets (dash / forms) - kept minimal for now
add_action('wp_enqueue_scripts', function(){
    wp_register_style('svntex2-style', SVNTEX2_PLUGIN_URL . 'assets/css/style.css', [], SVNTEX2_VERSION);
    wp_register_script('svntex2-core', SVNTEX2_PLUGIN_URL . 'assets/js/core.js', ['jquery'], SVNTEX2_VERSION, true);
});

// Dashboard shortcode (placeholder)
add_shortcode('svntex_dashboard', function(){
    if (!is_user_logged_in()) return '<p>Please <a href="'.esc_url(wp_login_url()).'">login</a>.</p>';
    wp_enqueue_style('svntex2-style');
    $file = SVNTEX2_PLUGIN_DIR . 'views/dashboard.php';
    if (file_exists($file)) { ob_start(); include $file; return ob_get_clean(); }
    return '<p>Dashboard view missing.</p>';
});

// Registration shortcode (skeleton)
add_shortcode('svntex_registration', function(){
    wp_enqueue_style('svntex2-style');
    wp_enqueue_script('svntex2-core');
    ob_start();
    ?>
    <form class="svntex2-registration" method="post" novalidate>
        <?php wp_nonce_field('svntex2_register','svntex2_nonce'); ?>
        <div><label>Mobile* <input type="text" name="mobile" required></label></div>
        <div><label>Email* <input type="email" name="email" required></label></div>
        <div><label>Password* <input type="password" name="password" required></label></div>
        <div><label>Referral ID <input type="text" name="referral"></label></div>
        <button type="submit">Register</button>
    </form>
    <?php
    return ob_get_clean();
});

// Handle basic registration POST (Phase 1 minimal) - extension point
add_action('init', function(){
    if (!empty($_POST['svntex2_nonce']) && wp_verify_nonce($_POST['svntex2_nonce'], 'svntex2_register')) {
        $email = sanitize_email($_POST['email'] ?? '');
        $mobile = preg_replace('/\D/','', $_POST['mobile'] ?? '');
        $password = $_POST['password'] ?? '';
        $referral_code = sanitize_text_field($_POST['referral'] ?? '');
        if (!$email || !$mobile || strlen($mobile) < 10 || strlen($password) < 8) {
            $_SESSION['svntex2_reg_error'] = 'Invalid input provided.'; return; }
        if (email_exists($email)) { $_SESSION['svntex2_reg_error'] = 'Email already registered.'; return; }
        // Generate Customer ID
        $customer_id = svntex2_generate_customer_id();
        $user_id = wp_insert_user([
            'user_login' => $customer_id,
            'user_email' => $email,
            'user_pass'  => $password,
            'display_name' => $customer_id
        ]);
        if (is_wp_error($user_id)) { $_SESSION['svntex2_reg_error'] = 'Registration failed.'; return; }
        update_user_meta($user_id, 'mobile', $mobile);
        update_user_meta($user_id, 'customer_id', $customer_id);
        if ($referral_code) update_user_meta($user_id,'referral_source',$referral_code);
        wp_safe_redirect(add_query_arg('registered','1', wp_get_referer() ?: home_url()));
        exit;
    }
});

// Utility generator
function svntex2_generate_customer_id(){
    for($i=0;$i<5;$i++){
        $candidate = 'SVN-' . str_pad(wp_rand(0,999999),6,'0',STR_PAD_LEFT);
        if (!username_exists($candidate)) return $candidate;
    }
    return 'SVN-' . wp_rand(100000,999999);
}

// Minimal core JS inline file creation fallback if missing
if (!file_exists(SVNTEX2_PLUGIN_DIR.'assets/js/core.js')) {
    @wp_mkdir_p(SVNTEX2_PLUGIN_DIR.'assets/js');
    @file_put_contents(SVNTEX2_PLUGIN_DIR.'assets/js/core.js', "console.log('SVNTeX core loaded');");
}

?>
<?php
/*
Plugin Name: SVNTeX 2.0 Customer System
Description: Modular customer registration and login system for SVNTeX, with secure backend, modern UI, and WooCommerce integration.
Version: 2.0
Author: SVNTeX Team
*/

// 1. Activation hook: create tables, flush rewrite rules
register_activation_hook(__FILE__, 'svntex2_activate_plugin');
function svntex2_activate_plugin() {
    // You may want to run the schema SQL here, or use dbDelta for WordPress standards
    // flush_rewrite_rules ensures custom endpoints work
    flush_rewrite_rules();
}

// 2. Deactivation hook: flush rewrite rules
register_deactivation_hook(__FILE__, 'svntex2_deactivate_plugin');
function svntex2_deactivate_plugin() {
    flush_rewrite_rules();
}

// 3. Enqueue JS/CSS for frontend forms
add_action('wp_enqueue_scripts', 'svntex2_enqueue_assets');
function svntex2_enqueue_assets() {
    // Only enqueue on registration/login pages or via shortcode
    wp_enqueue_style('svntex2-ui', plugins_url('customer-ui.css', __FILE__));
    wp_enqueue_script('svntex2-auth', plugins_url('customer-auth.js', __FILE__), array('jquery'), null, true);
}

// 4. Include submodules (registration, login, etc.)
require_once plugin_dir_path(__FILE__) . 'customer-registration.php';
require_once plugin_dir_path(__FILE__) . 'customer-login.php';
// You may want to move backend logic to includes/ for better structure

// 5. Register shortcodes for registration and login forms
add_shortcode('svntex2_registration', 'svntex2_registration_shortcode');
function svntex2_registration_shortcode() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'customer-registration.php';
    return ob_get_clean();
}

add_shortcode('svntex2_login', 'svntex2_login_shortcode');
function svntex2_login_shortcode() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'customer-login.php';
    return ob_get_clean();
}

// 6. Sample usage (add to any page/post):
// [svntex2_registration]
// [svntex2_login]

// 7. Improvements for folder structure:
// - Move backend logic to /includes/ (e.g., includes/registration-backend.php)
// - Keep only entry-point and UI files in root
// - Use /assets/ for JS/CSS
// - Use /templates/ for HTML forms if needed

// 8. Security notes:
// - Always sanitize/validate user input in backend files
// - Use WordPress nonces for AJAX requests if possible
// - Restrict direct access to backend files

// 9. Comments and modularity:
// - Each section is commented for clarity
// - All features are routed through this main file for WordPress compatibility

?>
