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
		'supports' => ['title','editor','thumbnail','comments'],
	]);
	// Taxonomy
	register_taxonomy('svntex_category', 'svntex_product', [
		'label' => 'Categories',
		'public' => true,
		'hierarchical' => true,
		'show_in_rest' => true,
		'rewrite' => [ 'slug' => 'product-category' ],
	]);
	// Brand taxonomy
	register_taxonomy('svntex_brand', 'svntex_product', [
		'label' => 'Brands', 'public'=>true, 'hierarchical'=>false, 'show_in_rest'=>true, 'rewrite'=>['slug'=>'brand']
	]);
	// Tags taxonomy
	register_taxonomy('svntex_tag', 'svntex_product', [
		'label' => 'Tags', 'public'=>true, 'hierarchical'=>false, 'show_in_rest'=>true, 'rewrite'=>['slug'=>'product-tag']
	]);
	// Shipping class taxonomy
	register_taxonomy('svntex_shipping_class', 'svntex_product', [
		'label'=>'Shipping Classes','public'=>true,'hierarchical'=>true,'show_in_rest'=>true,'rewrite'=>['slug'=>'shipping-class']
	]);

	// Expose product meta in REST so admin panel can set it
	if ( function_exists('register_post_meta') ) {
		register_post_meta('svntex_product','vendor_id', [
			'type' => 'integer',
			'single' => true,
			'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		// Pricing
		register_post_meta('svntex_product','base_price', [ 'type'=>'number','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','discount_price', [ 'type'=>'number','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
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
		// Stock
		register_post_meta('svntex_product','stock_qty', [ 'type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','stock_status', [ 'type'=>'string','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','low_stock_threshold', [ 'type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		// Media
		register_post_meta('svntex_product','video_media', [
			'type' => 'integer','single' => true,'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		register_post_meta('svntex_product','video_url', [
			'type' => 'string','single' => true,'show_in_rest' => true,
			'auth_callback' => function(){ return current_user_can('edit_posts'); }
		]);
		register_post_meta('svntex_product','gallery', [ 'type'=>'array','single'=>true,'show_in_rest'=>[
			'schema'=>['type'=>'array','items'=>['type'=>'integer']]
		],'auth_callback'=>function(){return current_user_can('edit_posts');}]);
		// Shipping
		register_post_meta('svntex_product','weight', [ 'type'=>'number','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','length', [ 'type'=>'number','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','width', [ 'type'=>'number','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','height', [ 'type'=>'number','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		// SEO & marketing
		register_post_meta('svntex_product','meta_title', [ 'type'=>'string','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','meta_description', [ 'type'=>'string','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','is_featured', [ 'type'=>'boolean','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		// Visibility & approval
		register_post_meta('svntex_product','visibility', [ 'type'=>'string','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','archived', [ 'type'=>'boolean','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','approved', [ 'type'=>'boolean','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		// Attributes & variations (JSON structures)
		register_post_meta('svntex_product','attributes', [ 'type'=>'object','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','variations', [ 'type'=>'array','single'=>true,'show_in_rest'=>['schema'=>['type'=>'array','items'=>['type'=>'object']]],'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		// Analytics placeholders
		register_post_meta('svntex_product','view_count', [ 'type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','sales_count', [ 'type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
		register_post_meta('svntex_product','returns_count', [ 'type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>function(){return current_user_can('edit_posts');} ]);
	}
});

// Intentionally no closing PHP tag.
