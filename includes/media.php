<?php

class BHE_Media {
	
	public function __construct() {
		
		// Register a taxonomy for media attachments called "media_category"
		add_action( 'init', array( $this, 'register_taxonomy' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	// Hooks
	/**
	 * Register the "media_category" taxonomy
	 */
	public function register_taxonomy() {
		
		$labels = array(
			'name' => 'Media Categories',
			'singular_name' => 'Media Category',
			'search_items' => 'Search Media Categories',
			'all_items' => 'All Media Categories',
			'parent_item' => 'Parent Media Category',
			'parent_item_colon' => 'Parent Media Category:',
			'edit_item' => 'Edit Media Category',
			'update_item' => 'Update Media Category',
			'add_new_item' => 'Add New Media Category',
			'new_item_name' => 'New Media Category Name',
			'menu_name' => 'Media Categories',
		);
		
		$args = array(
			'labels' => $labels,
			'hierarchical' => true,
			'show_ui' => true,
			'show_admin_column' => true,
			'query_var' => true,
			'rewrite' => array( 'slug' => 'media_category' ),
		);
		
		register_taxonomy( 'media_category', array( 'attachment' ), $args );
		
	}
	
}

// Initialize the object
BHE_Media::get_instance();
