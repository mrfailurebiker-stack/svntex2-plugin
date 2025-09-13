<?php
if (!defined('ABSPATH')) exit;

function svntex2_register_assets(){
	// Styles
	wp_register_style('svntex2-style', SVNTEX2_PLUGIN_URL . 'assets/css/style.css', [], SVNTEX2_VERSION);
	wp_register_style('svntex2-landing', SVNTEX2_PLUGIN_URL . 'assets/css/landing.css', [], SVNTEX2_VERSION);

	// Scripts
	wp_register_script('svntex2-core', SVNTEX2_PLUGIN_URL . 'assets/js/core.js', ['jquery'], SVNTEX2_VERSION, true);
	wp_register_script('svntex2-dashboard', SVNTEX2_PLUGIN_URL . 'assets/js/dashboard.js', ['jquery'], SVNTEX2_VERSION, true);
	wp_register_script('svntex2-brand-init', SVNTEX2_PLUGIN_URL . 'assets/js/brand-init.js', [], SVNTEX2_VERSION, true);
}
add_action('wp_enqueue_scripts','svntex2_register_assets');
