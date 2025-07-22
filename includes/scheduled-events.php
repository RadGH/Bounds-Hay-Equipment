<?php

class BHE_Scheduled_Events {
	
	public function __construct() {
		
		// Change the menu position of the Scheduled Events post type
		add_filter( 'register_post_type_args', array( $this, 'register_post_type_args' ), 10, 2 );
		
		// Change the Calendar menu parent slug
		add_filter( 'rs_schedule/calendar_parent_slug', array( $this, 'change_calendar_parent_slug' ) );
		
		// Capture the hook when an event is triggered
		add_action( 'rs_schedule/event', array( $this, 'event_triggered' ), 10, 2 );
		
		// Add rewrite rules for the Google Calendar ICS feed
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		
		// Displays the ICS feed page when visiting the ICS feed URL
		add_action( 'template_redirect', array( $this, 'display_ics_feed_page' ) );
		
		// Add a link to the ICS feed on the calendar page
		add_action( 'rs_schedule/calendar_page_after', array( $this, 'add_ics_feed_link' ) );
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	/**
	 * Generates and outputs the ICS feed content
	 *
	 * @return void
	 */
	public function generate_ics_feed() {
		if ( isset($_GET['debug']) ) {
			echo '<pre>';
		}else{
			// Send headers that identify this as an ICS file
			header( 'Content-Type: text/calendar; charset=utf-8' );
			header( 'Content-Disposition: inline; filename="scheduled-events.ics"' );
		}
		
		// require_once ABSPATH . WPINC . '/class-phpmailer.php'; // For timezone-safe formatting
		$events = RS_Schedule_Post_Type::get_schedule_events();
		
		$title = get_option('blogname');
		$hostname = parse_url( home_url(), PHP_URL_HOST );
		
		echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//{$title}//Scheduled Events//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n";
		
		foreach ( $events as $event ) {
			if ( isset( $event['rrule'] ) ) {
				$dtstart = strtotime( $event['rrule']['dtstart'] );
				$until = isset( $event['rrule']['until'] ) ? strtotime( $event['rrule']['until'] . ' 23:59:59' ) : null;
				
				$rrule = [
					'FREQ=' . strtoupper( $event['rrule']['freq'] ),
					'INTERVAL=' . intval( $event['rrule']['interval'] ),
				];
				
				if ( isset( $event['rrule']['byweekday'] ) && is_array( $event['rrule']['byweekday'] ) ) {
					$rrule[] = 'BYDAY=' . strtoupper( implode( ',', $event['rrule']['byweekday'] ) );
				}
				if ( $until ) {
					$rrule[] = 'UNTIL=' . gmdate( 'Ymd\THis\Z', $until );
				}
				
				echo "BEGIN:VEVENT\r\n";
				echo "UID:" . $event['id'] . "@{$hostname}\r\n";
				echo "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
				echo $this->fold_ical_line( "SUMMARY:" . $event['title'] ) . "\r\n";
				echo "DTSTART:" . gmdate( 'Ymd\THis\Z', $dtstart ) . "\r\n";
				echo "RRULE:" . implode( ';', $rrule ) . "\r\n";
				if ( ! empty( $event['url'] ) ) {
					echo "URL:" . esc_url( $event['url'] ) . "\r\n";
				}
				echo "END:VEVENT\r\n";
				
			} elseif ( isset( $event['start'] ) ) {
				
				echo "BEGIN:VEVENT\r\n";
				echo "UID:" . $event['id'] . "@{$hostname}\r\n";
				echo "DTSTAMP:" . gmdate( 'Ymd\THis\Z' ) . "\r\n";
				echo $this->fold_ical_line( "SUMMARY:" . $event['title'] ) . "\r\n";
				echo "DTSTART:" . gmdate( 'Ymd\THis\Z', strtotime( $event['start'] ) ) . "\r\n";
				if ( isset( $event['end'] ) ) {
					echo "DTEND:" . gmdate( 'Ymd\THis\Z', strtotime( $event['end'] ) ) . "\r\n";
				}
				if ( ! empty( $event['url'] ) ) {
					echo "URL:" . esc_url( $event['url'] ) . "\r\n";
				}
				echo "END:VEVENT\r\n";
				
			}
		}
		
		echo "END:VCALENDAR\r\n";
		
		if ( isset($_GET['debug']) ) {
			echo '</pre>';
		}
	}
	
	/**
	 * Automatically wraps long lines in the ICS feed to ensure they conform to the 75-character limit
	 *
	 * @param $line
	 *
	 * @return string
	 */
	public function fold_ical_line( $line ) {
		return wordwrap( $line, 75, "\r\n ", true );
	}
	
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
	
	/**
	 * Add rewrite rules for the Google Calendar ICS feed
	 *
	 * @see https://bounds.zingmap.com/scheduled-events/
	 * @see https://bounds.zingmap.com/scheduled-events/?debug
	 *
	 * @return void
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^scheduled-events$', 'index.php?ics_feed=1', 'top' );
		add_rewrite_tag( '%ics_feed%', '1' );
	}
	
	/**
	 * Displays the ICS feed page when visiting the ICS feed URL
	 *
	 * @see https://bounds.zingmap.com/scheduled-events/
	 * @see https://bounds.zingmap.com/scheduled-events/?debug
	 *
	 * @return void
	 */
	public function display_ics_feed_page() {
		// Only apply to the ICS feed URL: /scheduled-events.ics
		if ( ! get_query_var( 'ics_feed' ) ) return;
		
		$this->generate_ics_feed();
		exit;
	}
	
	/**
	 * Add a link to the ICS feed on the calendar page
	 *
	 * @return void
	 */
	public function add_ics_feed_link() {
		$ics_feed_url = site_url( '/scheduled-events' );
		
		$content = '<a href="' . esc_url( $ics_feed_url ) . '" class="button button-secondary" target="_blank" rel="noopener noreferrer">Download iCal Feed</a>';
		$content .= ' You can also copy this URL to add it to Google Calendar, Outlook, Apple Calendar, and more';
		
		echo '<div class="ics-feed-link">';
		echo wpautop( $content );
		echo '</div>';
	}
	
}

BHE_Scheduled_Events::get_instance();