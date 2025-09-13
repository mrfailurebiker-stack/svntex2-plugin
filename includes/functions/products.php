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
		'has_archive' => true,
		'rewrite' => [ 'slug' => 'products' ],
		'menu_icon' => 'dashicons-products',
		'supports' => ['title','editor','excerpt','thumbnail','custom-fields'],
	]);
	// Taxonomy
	register_taxonomy('svntex_category', 'svntex_product', [
		'label' => 'Categories',
		'public' => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'rewrite' => [ 'slug' => 'product-category' ],
	]);

	// Expose vendor_id meta in REST so admin panel can set it
	if ( function_exists('register_post_meta') ) {
		register_post_meta('svntex_product','vendor_id', [
			'type' => 'integer',
			'single' => true,
			'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
	}
});

// Intentionally no closing PHP tag.
