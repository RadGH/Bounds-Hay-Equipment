<?php

class BHE_Scheduled_Events {
	
	public function __construct() {
		
		// Change the menu position of the Scheduled Events post type
		add_filter( 'register_post_type_args', array( $this, 'register_post_type_args' ), 10, 2 );
		
		// Change the Calendar menu parent slug
		add_filter( 'rs_schedule/calendar_parent_slug', array( $this, 'change_calendar_parent_slug' ) );
		
		// Capture the hook when an event is triggered
		add_action( 'rs_schedule/event', array( $this, 'event_triggered' ), 10, 2 );
		
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
	 * Change the menu position of the Scheduled Events post type
	 *
	 * @param array $args
	 * @param string $post_type
	 * @return array
	 */
	public function register_post_type_args( $args, $post_type ) {
		if ( $post_type === 'schedule' ) {
			$args['menu_position'] = 4;
			$args['show_in_menu'] = 'edit.php?post_type=ticket';
			$args['labels']['all_items'] = 'Scheduled Events';
		}
		return $args;
	}
	
	/**
	 * Change the Calendar menu parent slug
	 *
	 * @param string $slug
	 * @return string
	 */
	public function change_calendar_parent_slug( $slug ) {
		return 'edit.php?post_type=ticket';
	}
	
	/**
	 * Capture the hook when an event is triggered
	 *
	 * @param int $post_id
	 * @param string $today
	 *
	 * @return void
	 */
	public function event_triggered( $post_id, $today ) {
		// Check if we should create a ticket for this event
		$create_ticket = get_field( 'create_ticket', $post_id );
		if ( ! $create_ticket ) return;
		
		// Get the ticket details from the scheduled event
		$d = get_field( 'ticket_details', $post_id );
		
		// Extract ticket details
		$ticket_title = $d['ticket_title'] ?? false;
		$featured_image_id = $d['featured_image_id'] ?? '';
		$asset_id = $d['assigned_asset_id'] ?? '';
		$feedback_form_id = $d['feedback_form_id'] ?? '';
		$type_of_request = $d['type_of_request'] ?? '';
		$severity = $d['severity'] ?? '';
		$odometer = $d['odometer'] ?? '';
		$name = $d['name'] ?? '';
		$email = $d['email'] ?? '';
		$phone_number = $d['phone_number'] ?? '';
		$body = $d['body'] ?? '';
		
		// Validate settings
		if ( ! $ticket_title ) $ticket_title = '[Schedule] ' . get_the_title($post_id);
		
		// Create a new ticket post
		$post_args = array(
			'post_title'   => $ticket_title,
			'post_status'  => 'publish',
			'post_type'    => 'ticket',
			'post_author'  => get_current_user_id(),
		);
		
		$ticket_id = wp_insert_post( $post_args );
		if ( ! $ticket_id || is_wp_error( $ticket_id ) ) {
			if ( function_exists( 'rs_debug_log' ) ) {
				$message = is_wp_error( $ticket_id ) ? $ticket_id->get_error_message() : 'Unknown error creating ticket (wp_insert_post returned false)';
				rs_debug_log( 'error', 'event_post_failed', 'Error creating ticket from scheduled event ID ' . $post_id . ': ' . $message );
			}
			return;
		}
		
		// Add a field to indicate this ticket was created from a scheduled event
		update_post_meta( $ticket_id, '_scheduled_event_id', $post_id );
		
		// Update fields
		if ( $featured_image_id ) set_post_thumbnail( $ticket_id, $featured_image_id );
		update_post_meta( $ticket_id, 'asset', $asset_id );
		
		// Update Fields -- Ticket Settings
		update_post_meta( $ticket_id, 'feedback_form_id', $feedback_form_id );
		update_post_meta( $ticket_id, 'type_of_request', $type_of_request );
		update_post_meta( $ticket_id, 'severity', $severity );
		update_post_meta( $ticket_id, 'odometer', $odometer );
		
		// Update Fields -- Form Data
		update_post_meta( $ticket_id, 'name', $name );
		update_post_meta( $ticket_id, 'email', $email );
		update_post_meta( $ticket_id, 'phone_number', $phone_number );
		update_post_meta( $ticket_id, 'body', $body );
		
		// Update the status after all other fields have been set
		$status = $feedback_form_id ? 'Request Feedback' : 'New';
		update_post_meta( $ticket_id, 'status', $status );
		
		// Trigger an action after the ticket is created
		// This is used to send the feedback form
		/** @see BHE_Ticket_Feedback::create_ticket_feedback_post() */
		do_action( 'bhe_ticket_created', $ticket_id, $post_id );
		
	}
	
	
}

BHE_Scheduled_Events::get_instance();