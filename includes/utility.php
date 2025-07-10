<?php

class BHE_Utility {
	
	public function __construct() {
	
	}
	
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		;
		if ( !isset( self::$instance ) ) {
			self::$instance = new static();
		}
		
		return self::$instance;
	}
	
	// Utilities
	
	/**
	 * Splits a textarea of emails into an array of emails, one per line. Removes empty lines and trims whitespace.
	 *
	 * @param string $emails
	 *
	 * @return string[]
	 */
	public static function split_multiline_emails( $emails ) {
		if ( ! $emails ) return array();
		
		$emails = explode( "\n", $emails );
		$emails = array_map( 'trim', $emails );
		$emails = array_unique( $emails );
		$emails = array_filter( $emails );
		
		// Remove non-email-addresses
		if ( $emails ) foreach( $emails as $i => $e ) {
			if ( !is_email( $e ) ) unset( $emails[ $i ] );
		}
		
		return $emails;
	}
	
	/**
	 * Gets the visitors IP address. Supports Cloudflare proxies and resolves comma separated lists to a single address.
	 *
	 * @return string|false
	 */
	public static function get_visitor_ip() {
		$ip = false;
		if ( !$ip && !empty($_SERVER["HTTP_CF_CONNECTING_IP"]) ) $ip = $_SERVER["HTTP_CF_CONNECTING_IP"];
		if ( !$ip && !empty($_SERVER['HTTP_X_FORWARDED_FOR']) ) $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		if ( !$ip && !empty($_SERVER['REMOTE_ADDR']) ) $ip = $_SERVER['REMOTE_ADDR'];
		
		// If comma separated, use the first IP address
		if ( $ip && strpos($ip, ',') !== false ) {
			$ip = explode(',', $ip);
			$ip = trim($ip[0]);
		}
		
		return $ip;
	}
	
	/**
	 * Gets the current screen, or null if not defined or accessed too early.
	 * @return WP_Screen|null
	 */
	public static function get_current_screen() {
		return function_exists('get_current_screen') ? get_current_screen() : null;
	}
	
	/**
	 * Checks if the current screen matches the given value.
	 * @param string $value    The value to check for
	 * @param string $property The property to check against. Default is 'id'
	 * @return bool
	 */
	public static function is_current_screen( $value = null, $property = 'id' ) {
		$screen = self::get_current_screen();
		return $screen && property_exists($screen, $property) && $screen->{$property} == $value;
	}
	
	/**
	 * Converts a plain email into an HTML mailto: link
	 *
	 * @param string $email
	 * @param null|string $default_if_empty If invalid or empty, display this text instead. If null, the original input will be returned as-is.
	 *
	 * @return string
	 */
	public static function get_email_link( $email, $default_if_empty = null ) {
		$email = trim( $email );
		
		if ( is_email( $email ) ) {
			return '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
		}else{
			if ( $default_if_empty !== null ) {
				return $default_if_empty;
			}else{
				return $email;
			}
		}
	}
	
	/**
	 * Converts a phone number into an HTML tel: link
	 *
	 * @param string $phone
	 * @param bool $hyphenated If true, will be formatted with hyphens instead: 555-555-5555
	 * @param null|string $default_if_empty If invalid or empty, display this text instead. If null, the original input will be returned as-is.
	 *
	 * @return string
	 */
	public static function get_phone_link( $phone, $hyphenated = false, $default_if_empty = null ) {
		$phone = preg_replace( '/[^0-9]/', '', $phone );
		
		// Remove the leading 1 if it's a US number
		if ( strlen( $phone ) === 11 && $phone[0] === '1' ) {
			$phone = substr( $phone, 1 );
		}
		
		// Format a 10-digit phone number
		if ( strlen( $phone ) === 10 ) {
			$pattern = $hyphenated ? '%s-%s-%s' : '(%s) %s-%s';
			return sprintf( $pattern, substr( $phone, 0, 3 ), substr( $phone, 3, 3 ), substr( $phone, 6, 4 ) );
		}
		
		if ( $default_if_empty !== null ) {
			return $default_if_empty;
		}else{
			return $phone;
		}
	}
	
	/**
	 * Returns the Go High Level API key settings from the Bounds Hay settings page.
	 * @return array {
	 *      @type string $private_key
	 *      @type string $location_id
	 * }
	 */
	public static function get_gohighlevel_api_settings() {
		$api_settings = get_field( 'gohighlevel', 'bhe_settings' );
		$private_key = $api_settings['private_key'] ?? '';
		$location_id = $api_settings['location_id'] ?? '';
		
		if ( ! $private_key || ! $location_id ) {
			if ( function_exists( 'aa_debug_log' ) ) {
				aa_debug_log( 'error', 'gohighlevel_api_key_missing', 'The GoHighLevel API key or location ID is missing. Please check the Bounds Hay settings.' );
			}
		}
		
		return array(
			'private_key' => $private_key,
			'location_id' => $location_id,
		);
	}
	
	// Hooks
	
}

// Initialize the object
BHE_Utility::get_instance();
