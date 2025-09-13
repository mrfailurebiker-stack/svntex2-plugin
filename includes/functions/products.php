<?php
// Custom product management features have been removed (legacy notes kept as comments only).
if (!defined('ABSPATH')) exit;

/** Register Product post type 'svntex_product' and taxonomy 'svntex_category' */
add_action('init', function(){
	// Post type
	register_post_type('svntex_product', [
		'label' => 'Products',
		'labels' => [
			'name' => 'Products',
			'singular_name' => 'Product',
			'add_new_item' => 'Add New Product',
			'edit_item' => 'Edit Product',
		],
		'public' => true,
		'show_in_rest' => true,
		'show_ui' => false, // hide WP admin UI; managed via custom admin
		'has_archive' => true,
		'rewrite' => [ 'slug' => 'products' ],
		'menu_icon' => 'dashicons-products',
		'supports' => ['title','editor','thumbnail'],
	]);
	// Taxonomy
	register_taxonomy('svntex_category', 'svntex_product', [
		'label' => 'Categories',
		'public' => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'rewrite' => [ 'slug' => 'product-category' ],
	]);

	// Expose product meta in REST so admin panel can set it
	if ( function_exists('register_post_meta') ) {
		register_post_meta('svntex_product','vendor_id', [
			'type' => 'integer',
			'single' => true,
			'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		register_post_meta('svntex_product','mrp', [
			'type' => 'number','single' => true,'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		register_post_meta('svntex_product','tax_percent', [
			'type' => 'number','single' => true,'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		register_post_meta('svntex_product','company_profit', [
			'type' => 'number','single' => true,'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		register_post_meta('svntex_product','sku', [
			'type' => 'string','single' => true,'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		register_post_meta('svntex_product','video_media', [
			'type' => 'integer','single' => true,'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		register_post_meta('svntex_product','video_url', [
			'type' => 'string','single' => true,'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
	}
});

// Intentionally no closing PHP tag.
