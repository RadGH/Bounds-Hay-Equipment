<?php

class BHE_Shortcodes {
	
	public function __construct() {
		
		add_shortcode( 'year', array( $this, 'year_shortcode' ) );
		
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
	 * Year shortcode
	 * @param array $atts
	 * @return string
	 */
	public function year_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'format' => 'Y',
		), $atts, 'year' );
		
		return date( $atts['format'] );
	}
	
	
}

BHE_Shortcodes::get_instance();