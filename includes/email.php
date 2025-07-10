<?php

class BHE_Email {
	
	public function __construct() {
	
		// When sending an email with wp_mail, override the sender and add a reply-to header
		add_filter( 'wp_mail', array( $this, 'filter_wp_mail' ), 1000, 1 );
		
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
	
	// Hooks
	
	/**
	 * Filter the wp_mail function to set the sender and add a reply-to header
	 *
	 * @param array $args {
	 *     @type string|string[] $to          Array or comma-separated list of email addresses to send message.
	 *     @type string          $subject     Email subject.
	 *     @type string          $message     Message contents.
	 *     @type string|string[] $headers     Additional headers.
	 *     @type string|string[] $attachments Paths to files to attach.
	 * }
	 *
	 * @return array
	 */
	public function filter_wp_mail( $args ) {
		$email_sender = get_option( 'bhe_settings_ticket_notification_email_sender' );
		$email_reply_to = get_option( 'bhe_settings_ticket_notification_email_reply_to' );
		if ( ! $email_sender && ! $email_reply_to ) return $args;
		
		// Ensure headers is an array
		if ( !isset($args['headers']) || ! is_array($args['headers']) ) {
			$args['headers'] = array();
		}
		
		// If headers are already set, remove the From and Reply-To headers
		if ( !empty($args['headers']) ) {
			// Ensure headers is an array
			if ( ! is_array($args['headers']) ) {
				$args['headers'] = explode( "\n", $args['headers'] );
			}
			
			// Remove existing headers if a custom one is present
			foreach( $args['headers'] as $i => $h ) {
				if ( $email_sender && str_contains( strtolower( $h ), 'from:' ) ) unset( $args['headers'][ $i ] );
				if ( $email_reply_to && str_contains( strtolower( $h ), 'reply-to:' ) ) unset( $args['headers'][ $i ] );
			}
		}
		
		// Add the new headers
		if ( $email_sender ) $args['headers'][] = 'From: ' . $email_sender;
		if ( $email_reply_to ) $args['headers'][] = 'Reply-To: ' . $email_reply_to;
		
		return $args;
	}
	
}

// Initialize the object
BHE_Email::get_instance();
