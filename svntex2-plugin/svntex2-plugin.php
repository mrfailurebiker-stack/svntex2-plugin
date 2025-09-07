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
    // Include registration module
    require_once plugin_dir_path(__FILE__) . 'includes/customer-registration.php';
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
