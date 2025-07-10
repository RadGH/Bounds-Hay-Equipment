<?php

class BHE_Ticket_Feedback {
	
	/** @type BHE_Instance_Admin_Post_Type_Filters $post_type_filters */
	public $post_type_filters;
	
	/** @type int $current_feedback_id */
	public int $current_feedback_id = 0;
	
	/** @type int $current_form_id */
	public int $current_form_id = 0;
	
	public function __construct() {
		
		// Register a post type
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		
		// Add filters to the post list screen for Tickets
		$this->register_post_type_filters();
		
		// Disable comments for both post types
		add_filter( 'comments_open', array( $this, 'disable_post_comments' ), 10, 2 );
		
		// Add custom columns
		add_filter( 'manage_ticket-feedback_posts_columns', array( $this, 'add_custom_columns' ), 99999999999 );
		
		// Display custom columns
		add_action( 'manage_ticket-feedback_posts_custom_column', array( $this, 'display_custom_columns' ), 10, 2 );
		
		// Sort posts on the backend post type screen by date, if no other order is provided
		add_filter( 'pre_get_posts', array( $this, 'sort_posts_by_date' ) );
		
		// Add a custom meta box
		add_action( 'add_meta_boxes', array( $this, 'add_custom_meta_box' ) );
		
		// Prevent users from adding new ticket feedback manually
		add_action( 'current_screen', array( $this, 'prevent_creating_posts' ) );
		
		// When a ticket is saved with the status "Request Feedback", automatically create a new Ticket Feedback item
		add_action( 'acf/save_post', array( $this, 'create_ticket_feedback_post' ), 10 ); // Created by ACF / WordPress
		add_action( 'bhe_ticket_created', array( $this, 'create_ticket_feedback_post' ), 10 ); /** Created by a Scheduled Event: @see BHE_Scheduled_Events::event_triggered() */
		
		// Display a notice that a feedback request was created
		add_action( 'admin_notices', array( $this, 'notice_ticket_feedback_created' ) );
		
		// Fill the field from the Ticket field "feedback_form_id" with a list of Gravity Forms from the Bounds Hay settings -> feedback_form_ids
		/** @see BHE_Tickets::populate_feedback_form_id() */
		add_filter( 'acf/load_field/key=field_681d2640995d7', array( 'BHE_Tickets', 'populate_feedback_form_id' ) ); // feedback_form_id (ticket feedback)
		
		// Fill in the name and a link to the parent ticket of this feedback item
		add_filter( 'acf/load_field/key=field_6827a680c9661', array( $this, 'populate_parent_ticket_message' ) ); // no name (ticket feedback)
		
		// Fill the message of the "Feedback Form" field with the name of the selected form from the select field (with the same name)
		/** @see BHE_Tickets::populate_feedback_form_name() */
		add_filter( 'acf/load_field/key=field_6827ac2580e61', array( 'BHE_Tickets', 'populate_feedback_form_name' ) ); // no name (ticket feedback)
		
		// When ticket feedback is created or updated, check if the status changes and apply some automations
		add_action( 'acf/save_post', array( $this, 'update_ticket_feedback_post' ), 10 );
		add_action( 'bhe_ticket_feedback_created', array( $this, 'update_ticket_feedback_post' ), 10 );
		
		// Replace the page title with a reference to the original ticket title
		add_filter( 'the_title', array( $this, 'replace_feedback_post_title' ), 10, 2 );
		
		// Replace the page content with a brief description of the ticket and the feedback form
		add_filter( 'the_content', array( $this, 'replace_feedback_post_content' ), 10, 1 );
		
		// When creating a GF entry, check if related to a feedback item. If so, process the feedback item with the response
		add_action( 'gform_after_submission', array( $this, 'process_feedback_entry' ), 10, 2 );
		
		// Add no cache and no index headers to the feedback post type
		add_action( 'template_redirect', array( $this, 'add_no_cache_headers' ) );
		
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
	 * Generates a random slug for a ticket
	 *
	 * @param int $ticket_id
	 *
	 * @return string
	 */
	public function generate_feedback_slug( $ticket_id ) {
		// Generate a slug, repeat until it is unique
		do {
			$feedback_slug = 'tf-' . $ticket_id . '-' . wp_generate_password( 8, false );
		} while ( get_page_by_path( $feedback_slug, OBJECT, 'ticket-feedback' ) );
		
		return $feedback_slug;
	}
	
	
	/**
	 * Sends an email notification to the ticket author when feedback is requested
	 * @param $ticket_feedback_id
	 *
	 * @return void
	 */
	public function notify_ticket_feedback_author( $ticket_feedback_id ) {
		if ( get_post_type( $ticket_feedback_id ) !== 'ticket-feedback' ) return;
		
		$subject = get_field( 'ticket_feedback_requested_subject', 'bhe_settings' );
		if ( ! $subject ) {
			$subject = 'Feedback requested for your support ticket: {title}';
		}
		
		$body = get_field( 'ticket_feedback_requested_body', 'bhe_settings' );
		if ( ! $body ) {
			$body = 'Hello {author_name},' . "\n\n";
			$body.= 'We have reviewed your recent ticket titled <strong>{title}</strong>.' . "\n\n";
			$body.= 'We are requesting additional feedback regarding your ticket. Please complete the form "{feedback_form_title}" by using the link below:' . "\n\n";
			$body.= '{feedback_url}' . "\n\n";
			$body.= 'Thank you,' . "\n\n";
			$body.= 'BHE Team';
		}
		
		// Insert merge tags
		$merge_tags = $this->get_merge_tags( $ticket_feedback_id );
		
		$to = '{author_name} <{author_email}>';
		
		$to = str_replace( array_keys( $merge_tags ), array_values( $merge_tags ), $to );
		$subject = str_replace( array_keys( $merge_tags ), array_values( $merge_tags ), $subject );
		$body = str_replace( array_keys( $merge_tags ), array_values( $merge_tags ), $body );
		
		// Allow HTML email content
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		
		$result = wp_mail( $to, $subject, $body, $headers );
		
		if ( $result ) {
			update_post_meta( $ticket_feedback_id, 'notification_sent', 1 );
		}
	}
	
	/**
	 * Get merge tags used for ticket feedback
	 *
	 * @param $ticket_feedback_id
	 *
	 * @return array
	 */
	public function get_merge_tags( $ticket_feedback_id ) {
		
		// Get ticket merge tags
		$ticket_id = get_field( 'ticket_id', $ticket_feedback_id );
		$ticket_tags = BHE_Tickets::get_instance()->get_ticket_merge_tags( $ticket_id );
		
		// Get feedback merge tags
		$feedback_form_id = get_post_meta( $ticket_feedback_id, 'feedback_form_id', true );
		$form = $feedback_form_id ? GFAPI::get_form( $feedback_form_id ) : false;
		$form_title = $form ? $form['title'] : 'Form #'. $feedback_form_id;
		
		$feedback_url = get_permalink( $ticket_feedback_id );
		
		$feedback_tags = array(
			'{feedback_url}' => $feedback_url, // URL to the feedback post
			'{feedback_form_title}' => $form_title, // Title of the feedback form
		);
		
		// Combine all merge tags
		$merge_tags = array_merge( $ticket_tags, $feedback_tags );
		
		return $merge_tags;
	}
	
	/**
	 * Display the Gravity Form associated with the ticket feedback.
	 *
	 * @param int $ticket_feedback_id
	 *
	 * @return void
	 */
	public function display_feedback_form( $ticket_feedback_id ) {
		
		$feedback_form_id = get_post_meta( $ticket_feedback_id, 'feedback_form_id', true );
		$form = $feedback_form_id ? GFAPI::get_form( $feedback_form_id ) : false;
		
		echo '<h2>Feedback Form:</h2>';
		
		if ( $form ) {
			$this->hook_feedback_form( $ticket_feedback_id, $form );
			echo do_shortcode( '[gravityform id="'. $form['id'] .'" title="false" description="false" ajax="false"]' );
			$this->unhook_feedback_form( $ticket_feedback_id, $form );
		}else{
			echo '<p>Error: Invalid form specified. Cannot display feedback form.</p>';
		}
	}
	
	/**
	 * Prepares the feedback gravity form by including necessary hooks and hidden fields used to associate the entry with the ticket feedback after submission.
	 *
	 * @param int $ticket_feedback_id
	 * @param array $form
	 *
	 * @return void
	 */
	public function hook_feedback_form( $ticket_feedback_id, $form ) {
		
		$this->current_feedback_id = $ticket_feedback_id;
		$this->current_form_id = $form['id'];
		
		// Add hook to insert custom fields in the form html
		add_filter( 'gform_get_form_filter_' . $form['id'], array( $this, 'gf_add_feedback_hidden_fields' ), 10, 2 );
		
	}
	
	/**
	 * Unhook the feedback form after being displayed
	 *
	 * @param $ticket_feedback_id
	 * @param $form
	 *
	 * @return void
	 */
	public function unhook_feedback_form( $ticket_feedback_id, $form ) {
		$this->current_feedback_id = 0;
		$this->current_form_id = 0;
		remove_filter( 'gform_get_form_filter_' . $form['id'], array( $this, 'gf_add_feedback_hidden_fields' ), 10 );
	}
	
	// Hooks
	/**
	 * Register the post type
	 */
	public function register_post_type() {
		
		$labels = array(
			'menu_name'                => 'Ticket Feedback',
			'name'                     => 'Ticket Feedback',
			'singular_name'            => 'Ticket Feedback',
			'add_new'                  => 'Add New Ticket Feedback',
			'add_new_item'             => 'Add New Ticket Feedback',
			'edit_item'                => 'Edit Ticket Feedback',
			'new_item'                 => 'New Ticket Feedback',
			'view_item'                => 'View Ticket Feedback',
			'search_items'             => 'Search Ticket Feedback',
			'not_found'                => 'No ticket found',
			'not_found_in_trash'       => 'No ticket found in trash',
			'parent_item_colon'        => 'Parent Ticket Feedback:',
			'all_items'                => 'Ticket Feedback',
			'archives'                 => 'Ticket Feedback Archives',
			'insert_into_item'         => /** @lang text */ 'Insert into ticket',
			'uploaded_to_this_item'    => 'Uploaded to this ticket',
			'featured_image'           => 'Ticket Feedback Image',
			'set_featured_image'       => 'Set ticket image',
			'remove_featured_image'    => 'Remove ticket image',
			'use_featured_image'       => 'Use as ticket image',
			'filter_items_list'        => 'Filter ticket list',
			'items_list_navigation'    => 'Ticket Feedback list navigation',
			'items_list'               => 'Ticket Feedback list',
			'item_published'           => 'Ticket Feedback sent.',
			'item_published_privately' => 'Ticket Feedback sent privately.',
			'item_reverted_to_draft'   => 'Ticket Feedback reverted to draft.',
			'item_scheduled'           => 'Ticket Feedback scheduled.',
			'item_updated'             => 'Ticket Feedback updated.',
		);
		
		$args = array(
			'labels'       => $labels,
			'show_in_rest' => true, // Enables Gutenberg editor
			
			'supports'         => array(
				'title',
				'thumbnail',
				// 'author',
				// 'comments',
				// 'editor',
			),
			'delete_with_user' => false,
			
			'public'              => true,
			'hierarchical'        => true,
			'exclude_from_search' => true,
			'show_in_nav_menus'   => false,
			
			'show_in_menu' => 'edit.php?post_type=ticket',
			// 'menu_icon'     => 'dashicons-tickets-alt',
			// 'menu_position' => 6,
			
			'has_archive' => false,
			'rewrite'     => array(
				'slug'       => 'feedback',
				'with_front' => false,
			),
		);
		
		register_post_type( 'ticket-feedback', $args );
		
	}
	
	/**
	 * Add filters to the post list screen for Tickets, allowing to filter by ticket status, type of request, etc.
	 *
	 * @return void
	 */
	public function register_post_type_filters() {
		$post_type = 'ticket-feedback';
		$taxonomies = array();
		$acf_fields = array(
			// 'field_xxx', // name
		);
		
		$this->post_type_filters = new BHE_Instance_Admin_Post_Type_Filters( $post_type, $taxonomies, $acf_fields );
	}
	
	/**
	 * Disable comments for both post types
	 *
	 * @param bool $open
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function disable_post_comments( $open, $post_id ) {
		if ( get_post_type( $post_id ) === 'ticket-feedback' ) {
			return false;
		}
		
		return $open;
	}
	
	/**
	 * Add custom columns
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function add_custom_columns( $columns ) {
		// Re-order columns so the featured image is after post title
		$columns = array_merge(
			array_slice( $columns, 0, 2, true ),
			array( 'feedback-status' => 'Status' ),
			// array( 'author' => $columns['author'] ),
			array_slice( $columns, 2, null, true )
		);
		
		return $columns;
	}
	
	/**
	 * Display custom columns
	 *
	 * @param string $column
	 * @param int $post_id
	 *
	 * @return void
	 */
	public function display_custom_columns( $column, $post_id ) {
		if ( $column === 'feedback-status' ) {
			$status = get_post_meta( $post_id, 'status', true );
			
			echo $status ?: '&ndash;';
		}
	}
	
	/**
	 * Sort posts on the backend post type screen by date, if no other order is provided
	 *
	 * @param WP_Query $query
	 *
	 * @return void
	 */
	public function sort_posts_by_date( $query ) {
		if ( !is_admin() ) return;
		if ( !$query->is_main_query() ) return;
		
		$default_orderby = 'menu_order title';
		
		if ( $query->get( 'post_type' ) === 'ticket-feedback' && $query->get( 'orderby' ) === $default_orderby ) {
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
		}
	}
	
	/**
	 * Display information from the attached asset, based on the asset type
	 * @return void
	 */
	public function add_custom_meta_box() {
		/*
		add_meta_box(
			'ticket_asset_details',
			'Ticket - Asset Details',
			array( $this, 'display_asset_detail_meta_box' ),
			'ticket-feedback',
			'normal',
			'low'
		);
		*/
	}
	
	/**
	 * Display the asset details meta box content
	 * @return void
	 */
	/*
	public function display_asset_detail_meta_box() {
		$ticket_id = get_the_ID();
	}
	*/
	
	/**
	 * Prevent users from adding new ticket feedback manually
	 * @return void
	 */
	public function prevent_creating_posts() {
		if ( ! BHE_Utility::is_current_screen('ticket-feedback', 'id') ) return;
		if ( ! BHE_Utility::is_current_screen('add', 'action') ) return;
		
		wp_die('You can not add Ticket Feedback manually',
			'Error - Add Ticket Feedback',
			array(
				'response' => 403,
				'back_link' => true,
			)
		);
	}
	
	/**
	 * When a ticket is saved with the status "Request Feedback", automatically create a new Ticket Feedback item
	 *
	 * @param int|string $acf_info
	 *
	 * @return void
	 */
	public function create_ticket_feedback_post( $acf_info ) {
		$info = acf_get_post_id_info( $acf_info );
		if ( $info['type'] != 'post' ) return;
		
		$post_id = $info['id'];
		if ( get_post_type( $post_id ) !== 'ticket' ) return;
		
		// Ignore if the status is not "Request Feedback"
		$status = get_field( 'status', $post_id );
		if ( $status != 'Request Feedback' ) return;
		
		// Get settings
		$feedback_form_id = get_field( 'feedback_form_id', $post_id );
		$author_name = get_field( 'name', $post_id );
		$author_email = get_field( 'email', $post_id );
		$author_phone = get_field( 'phone_number', $post_id );
		
		// Set the link to expire in the next 30 days
		$expiry_date = date( 'Y-m-d H:i:s', strtotime( '+30 days' ) );
		
		// Get a random slug
		$feedback_slug = $this->generate_feedback_slug( $post_id );
		
		// Get the current date
		$date_short = current_time( 'm/d/Y g:i A' );
		
		// Create new Ticket Feedback post
		$args = array(
			'post_title'  => 'Ticket Feedback for Ticket #' . $post_id . ' on ' . $date_short,
			'post_type'   => 'ticket-feedback',
			'post_status' => 'publish',
			'post_name'   => $feedback_slug,
		);
		
		// Insert the post into the database
		$ticket_feedback_id = wp_insert_post( $args );
		
		// Error if it failed
		if ( is_wp_error( $ticket_feedback_id ) ) {
			wp_die('Error creating Ticket Feedback post: ' . $ticket_feedback_id->get_error_message(),
				'Error - Create Ticket Feedback',
				array(
					'response' => 403,
					'back_link' => true,
				)
			);
			exit;
		}
		
		// Feedback: Populate the post meta
		update_post_meta( $ticket_feedback_id, 'ticket_id', $post_id );
		update_post_meta( $ticket_feedback_id, 'feedback_form_id', $feedback_form_id );
		update_post_meta( $ticket_feedback_id, 'name', $author_name );
		update_post_meta( $ticket_feedback_id, 'email', $author_email );
		update_post_meta( $ticket_feedback_id, 'phone_number', $author_phone );
		update_post_meta( $ticket_feedback_id, 'feedback_created_date', current_time( 'Y-m-d H:i:s' ) );
		update_post_meta( $ticket_feedback_id, 'expiration_date', $expiry_date );
		update_post_meta( $ticket_feedback_id, 'status', 'Pending' );
		
		// Ticket: Update the ticket status to Awaiting Feedback and clear the feedback form ID
		update_field( 'status', 'Awaiting Feedback', $post_id );
		update_field( 'feedback_form_id', '', $post_id );
		
		// Ticket: Add a meta key to indicate the feedback was created
		update_post_meta( $post_id, 'feedback_recently_created_date', current_time( 'Y-m-d H:i:s' ) );
		
		// Notify the ticket author about the new feedback request
		$this->notify_ticket_feedback_author( $ticket_feedback_id );
		
		// Add a hook after the ticket feedback has been created
		do_action( 'bhe_ticket_feedback_created', $ticket_feedback_id, $post_id );
		
	}
	
	/**
	 * Display a notice that a feedback request was created
	 * @return void
	 */
	public function notice_ticket_feedback_created() {
		if ( ! BHE_Utility::is_current_screen('ticket', 'id') ) return;
		
		// Check if the feedback was created within the last 60 seconds
		$ticket_id = get_the_ID();
		$feedback_created_date = get_post_meta( $ticket_id, 'feedback_recently_created_date', true );
		$time_now = current_time( 'Y-m-d H:i:s' );
		
		$created_recently = strtotime( $feedback_created_date ) > ( strtotime( $time_now ) - 300 );
		
		if ( $feedback_created_date && $created_recently ) {
			// Display the notice
			echo '<div class="notice notice-success is-dismissible">';
			echo '<p>Feedback has been requested and the status changed to Awaiting Feedback.</p>';
			echo '</div>';
			
			// Delete the meta key
			delete_post_meta( $ticket_id, 'feedback_recently_created_date' );
		}
	}
	
	/**
	 * Fill in the name and a link to the parent ticket of this feedback item
	 *
	 * @param array $field
	 *
	 * @return array
	 */
	public function populate_parent_ticket_message( $field ) {
		if ( acf_is_screen('acf-field-group') ) return $field;
		if ( acf_is_screen('acf_page_acf-tools') ) return $field;
		
		$post_id = get_the_ID();
		
		if ( get_post_type( $post_id ) == 'ticket-feedback' ) {
			$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
			$ticket_id = get_field( 'ticket_id', $post_id );
			$ticket_edit_link = $ticket_id ? get_edit_post_link($ticket_id) : false;
			$ticket_date = get_the_time( $date_format, $ticket_id );
			
			$author_name = get_field( 'name', $post_id );
			$author_email = get_field( 'email', $post_id );
			$author_phone = get_field( 'phone_number', $post_id );
			
			if ( $ticket_id ) {
				$ticket_title = get_the_title( $ticket_id );
				$field['message'] = '<h3 style="margin: 0 0 5px;"><strong>Ticket:</strong> <a href="' . esc_url( $ticket_edit_link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $ticket_title ) . ' (#'. $ticket_id .')</a></h3>';
				
				// Add ticket date
				$field['message'] .= '<p style="margin: 0;">Created on: ' . esc_html( $ticket_date ) . '</p>';
				
				// Add author info
				if ( $author_name ) {
					$field['message'] .= '<p style="margin-top: 15px;">';
					$field['message'] .= 'Author: ' . esc_html( $author_name );
					$field['message'] .= '<br>Author Email: ' . BHE_Utility::get_email_link( $author_email, '(None)' );
					$field['message'] .= '<br>Author Phone: ' . BHE_Utility::get_phone_link( $author_phone, false, '(None)' );
					$field['message'] .= '</p>';
				}else{
					$field['message'] .= 'No author name provided.';
				}
			}else{
				$field['message'] = 'No parent ticket found.';
			}
			
		}
		
		return $field;
	}
	
	/**
	 * When ticket feedback is created or updated, check if the status changes and apply some automations
	 *
	 * @param int $post_id
	 *
	 * @return void
	 */
	public function update_ticket_feedback_post( $post_id ) {
		if ( get_post_type( $post_id ) != 'ticket-feedback' ) return;
		
		// Get the status of the ticket feedback
		$previous_status = get_post_meta( $post_id, 'previous_status', true );
		$status = get_post_meta( $post_id, 'status', true );
		
		// Ignore if the status is not changed
		if ( $previous_status === $status ) return;
		
		switch( $status ) {
			case 'Pending':
				// Do nothing. Waiting on the ticket author to submit the form.
				break;
			
			case 'Closed':
				// Do nothing. The link will no longer be available.
				break;
			
			default:
				// Other statuses need to be added here. If not, display an error.
				wp_die(
					'Uncaught ticket feedback status: ' . $status . ' for post ID: ' . $post_id,
					'Error - Ticket Feedback Status',
					array(
						'response' => 403,
					)
				);
				exit;
				break;
		}
		
		// Remember the previous status for next time
		update_post_meta( $post_id, 'previous_status', $status );
		
	}
	
	/**
	 * Replace the page title with a reference to the original ticket title
	 *
	 * @param string $title
	 * @param int $ticket_feedback_id
	 *
	 * @return string
	 */
	function replace_feedback_post_title( $title, $ticket_feedback_id ) {
		if ( get_post_type( $ticket_feedback_id ) != 'ticket-feedback' ) return $title;
		if ( ! is_singular( 'ticket-feedback' ) ) return $title;
		
		$ticket_id = get_field( 'ticket_id', $ticket_feedback_id );
		if ( $ticket_id ) {
			$ticket_title = get_the_title( $ticket_id );
			$title = 'Feedback: ' . $ticket_title;
		}
		
		return $title;
	}
	
	/**
	 * Replace the page content with a brief description of the ticket and the feedback form
	 *
	 * @param string $title
	 * @param int $ticket_feedback_id
	 *
	 * @return string
	 */
	function replace_feedback_post_content( $content ) {
		if ( ! is_singular( 'ticket-feedback' ) ) return $content;
		
		$ticket_feedback_id = get_the_ID();
		if ( get_post_type( $ticket_feedback_id ) != 'ticket-feedback' ) return $content;
		
		// Must be in "the loop"
		if ( ! in_the_loop() ) return $content;
		
		ob_start();
		
		include BHE_PATH . '/templates/ticket-feedback/content.php';
		
		return ob_get_clean();
	}
	
	/**
	 * Adds hidden fields to the Gravity Form used for ticket feedback, so we can know which ticket feedback this entry belongs to after submission.
	 *
	 * @param string $form_string
	 * @param array $form
	 *
	 * @return string
	 */
	public function gf_add_feedback_hidden_fields( $form_string, $form ) {
		$hidden_fields = '<input type="hidden" name="ticket_feedback_id" value="' . esc_attr( $this->current_feedback_id ) . '" />';
		
		$form_string = str_replace('</form>', "\n" . $hidden_fields . "\n" . '</form>', $form_string);
		
		return $form_string;
	}
	
	/**
	 * When creating a GF entry, check if related to a feedback item. If so, process the feedback item with the response
	 *
	 * @param array $entry
	 * @param array $form
	 *
	 * @return void
	 */
	public function process_feedback_entry( $entry, $form ) {
		$ticket_feedback_id = isset( $_REQUEST['ticket_feedback_id'] ) ? intval( $_REQUEST['ticket_feedback_id'] ) : 0;
		if ( ! $ticket_feedback_id ) return;
		
		GFFormsModel::add_note( $entry['id'], 0, 'System', 'Processing feedback entry for ticket feedback ID: ' . $ticket_feedback_id );
		
		// Check if the feedback item exists
		if ( get_post_type( $ticket_feedback_id ) !== 'ticket-feedback' ) {
			GFFormsModel::add_note( $entry['id'], 0, 'System', 'Error: Ticket feedback ID #'. $ticket_feedback_id .' does not exist.' );
			return;
		}
		
		// Update the feedback status to "Closed"
		update_post_meta( $ticket_feedback_id, 'status', 'Closed' );
		
		// Store the entry ID on the ticket feedback post
		update_post_meta( $ticket_feedback_id, 'entry_id', $entry['id'] );
		update_post_meta( $ticket_feedback_id, 'entry_date_created', current_time('mysql') );
		
		// If the parent ticket status is "Awaiting Feedback", update it to "New Feedback"
		$ticket_id = get_field( 'ticket_id', $ticket_feedback_id );
		
		if ( $ticket_id ) {
			$status = get_field( 'status', $ticket_id );
			
			if ( $status === 'Request Feedback' || $status === 'Awaiting Feedback' ) {
				update_field( 'status', 'New Feedback', $ticket_id );
				
				GFFormsModel::add_note( $entry['id'], 0, 'System', 'Ticket #' . $ticket_id . ' status updated to "New Feedback".' );
			}else{
				GFFormsModel::add_note( $entry['id'], 0, 'System', 'Ticket #' . $ticket_id . ' status is not "Awaiting Feedback". The original ticket status was not updated.' );
			}
		}else{
			GFFormsModel::add_note( $entry['id'], 0, 'System', 'Error: No parent ticket found for ticket feedback ID #'. $ticket_feedback_id .'.' );
		}
	}
	
	/**
	 * Add no cache and no index headers to the feedback post type
	 *
	 * @return void
	 */
	public function add_no_cache_headers() {
		if ( ! is_singular( 'ticket-feedback' ) ) return;
		
		// Disable caching for this page
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		
		// Prevent search engines from indexing this page
		add_filter( 'wp_robots', function( $robots ) {
			$robots['noindex'] = true;
			return $robots;
		} );
	}
	
}

// Initialize the object
BHE_Ticket_Feedback::get_instance();
