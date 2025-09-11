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

define( 'SVNTEX_VERSION', '2.0.0' );
define( 'SVNTEX_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SVNTEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Include necessary files.
 */
function svntex_include_files() {
    // Functions
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/helpers.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/enqueue.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/products.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/rest-products.php';
    require_once SVNTEX_PLUGIN_DIR . 'includes/functions/vendors.php';

    // Classes
}
add_action( 'init', 'svntex_include_files' );


/**
 * Initialize the plugin.
 */
function svntex_init() {
    // Initialize classes
    SVNTEX_Dashboard::instance();
}
add_action( 'init', 'svntex_init' );


/**
 * Adds a custom GST number field to the WooCommerce product general tab.
 */
function svntex_add_gst_field_to_products() {
    echo '<div class="options_group">';

    woocommerce_wp_text_input(
        array(
            'id'          => '_svntex_gst_rate',
            'label'       => __( 'GST Rate (%)', 'svntex' ),
            'placeholder' => 'e.g., 18',
            'desc_tip'    => 'true',
            'description' => __( 'Enter the GST rate for this product as a percentage.', 'svntex' ),
            'type'        => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0'
            )
        )
    );

    echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'svntex_add_gst_field_to_products' );

/**
 * Saves the custom GST number field value.
 *
 * @param int $product_id The ID of the product being saved.
 */
function svntex_save_gst_field( $product_id ) {
    $gst_rate = isset( $_POST['_svntex_gst_rate'] ) ? sanitize_text_field( $_POST['_svntex_gst_rate'] ) : '';
    update_post_meta( $product_id, '_svntex_gst_rate', $gst_rate );
}
add_action( 'woocommerce_process_product_meta', 'svntex_save_gst_field' );
