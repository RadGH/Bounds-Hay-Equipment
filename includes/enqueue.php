<?php

class BHE_Enqueue {
	
	public function __construct() {
		
		// Enqueue front-end assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );
		
		// Enqueue admin assets (backend)
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// Enqueue login page assets
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_assets' ) );
		
		// Enqueue block editor assets
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_block_editor_assets' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		
		return self::$instance;
	}
	
	// Utilities
	/**
	 * Enqueue global assets (Dashboard, front-end, login)
	 * @return void
	 */
	public function enqueue_global_assets() {
		wp_enqueue_style( 'bounds-hay-equipment-global', BHE_URL . '/assets/global.css', array(), BHE_VERSION );
		wp_enqueue_script( 'bounds-hay-equipment-global', BHE_URL . '/assets/global.js', array(), BHE_VERSION );
	}
	
	// Hooks
	/**
	 * Enqueue front-end assets
	 * @return void
	 */
	public function enqueue_public_assets() {
		
		$this->enqueue_global_assets();
		
		wp_enqueue_script( 'bounds-hay-equipment-public', BHE_URL . '/assets/public.js', array(), BHE_VERSION );
		wp_enqueue_style( 'bounds-hay-equipment-public', BHE_URL . '/assets/public.css', array(), BHE_VERSION );
		
	}
	
	/**
	 * Enqueue admin assets (backend)
	 * @return void
	 */
	public function enqueue_admin_assets() {
		
		$this->enqueue_global_assets();
		
		wp_enqueue_style( 'bounds-hay-equipment-admin', BHE_URL . '/assets/admin.css', array(), BHE_VERSION );
		wp_enqueue_script( 'bounds-hay-equipment-admin', BHE_URL . '/assets/admin.js', array( 'acf-input' ), BHE_VERSION );
	
	}
	
	/**
	 * Enqueue login page assets
	 * @return void
	 */
	public function enqueue_login_assets() {
		
		$this->enqueue_global_assets();
		
	}
	
	/**
	 * Enqueue block editor assets
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
	
	}
	
}

// Initialize the object
BHE_Enqueue::get_instance();
