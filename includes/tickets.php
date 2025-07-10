<?php

class BHE_Tickets {
	
	/** @type BHE_Instance_Admin_Post_Type_Filters $post_type_filters */
	public $post_type_filters;
	
	public function __construct() {
		
		// Register a post type
		add_action( 'init', array( $this, 'register_post_type' ), 5 );
		
		// Add filters to the post list screen for Tickets
		$this->register_post_type_filters();
		
		// Disable comments for both post types
		add_filter( 'comments_open', array( $this, 'disable_post_comments' ), 10, 2 );
		
		// Add custom columns
		add_filter( 'manage_ticket_posts_columns', array( $this, 'add_custom_columns' ), 99999999999 );
		
		// Display custom columns
		add_action( 'manage_ticket_posts_custom_column', array( $this, 'display_custom_columns' ), 10, 2 );
		
		// Maybe send email/sms notifications when adding a ticket and when updating its status
		add_action( 'gform_advancedpostcreation_post_after_creation', array( $this, 'maybe_notify_on_insert' ) );
		add_filter( 'acf/update_value/name=status', array( $this, 'maybe_notify_on_update' ), 20, 2 );
		
		// Sort posts on the backend post type screen by date, if no other order is provided
		add_filter( 'pre_get_posts', array( $this, 'sort_posts_by_date' ) );
		
		// When visiting the URL to a ticket, redirect to edit the ticket instead (admins). For other users, redirect to the home page.
		add_action( 'template_redirect', array( $this, 'prevent_direct_access' ) );
		
		// Display information from the attached asset, based on the asset type
		add_action( 'add_meta_boxes', array( $this, 'add_asset_detail_meta_box' ) );
		
		// Fill the field from the Ticket field "feedback_form_id" with a list of Gravity Forms from the Bounds Hay settings -> feedback_form_ids
		add_filter( 'acf/load_field/key=field_681d2640995d7', array( $this, 'populate_feedback_form_id' ) ); // feedback_form_id (tickets)
		add_filter( 'acf/load_field/key=field_685f14b761406', array( $this, 'populate_feedback_form_id' ) ); // feedback_form_id (schedules)
		
		// Fill the message of the "Feedback Form" field with the name of the selected form from the select field (with the same name)
		add_filter( 'acf/load_field/key=field_6827a420b8f72', array( $this, 'populate_feedback_form_name' ) ); // no name (tickets)
		
		// Fill the "Conditional Current Status (Hidden)" field on a ticket
		add_filter( 'acf/load_value/key=field_6827a138317db', array( $this, 'populate_conditional_current_status' ), 30, 3 ); // conditional_current_status (tickets)
		
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
	 * Register the post type
	 */
	public function register_post_type() {
		
		$labels = array(
			'menu_name'                => 'Tickets',
			'name'                     => 'Tickets',
			'singular_name'            => 'Ticket',
			'add_new'                  => 'Add New Ticket',
			'add_new_item'             => 'Add New Ticket',
			'edit_item'                => 'Edit Ticket',
			'new_item'                 => 'New Ticket',
			'view_item'                => 'View Ticket',
			'search_items'             => 'Search Ticket',
			'not_found'                => 'No ticket found',
			'not_found_in_trash'       => 'No ticket found in trash',
			'parent_item_colon'        => 'Parent Ticket:',
			'all_items'                => 'All Tickets',
			'archives'                 => 'Ticket Archives',
			'insert_into_item'         => /** @lang text */
				'Insert into ticket',
			'uploaded_to_this_item'    => 'Uploaded to this ticket',
			'featured_image'           => 'Ticket Image',
			'set_featured_image'       => 'Set ticket image',
			'remove_featured_image'    => 'Remove ticket image',
			'use_featured_image'       => 'Use as ticket image',
			'filter_items_list'        => 'Filter ticket list',
			'items_list_navigation'    => 'Ticket list navigation',
			'items_list'               => 'Ticket list',
			'item_published'           => 'Ticket sent.',
			'item_published_privately' => 'Ticket sent privately.',
			'item_reverted_to_draft'   => 'Ticket reverted to draft.',
			'item_scheduled'           => 'Ticket scheduled.',
			'item_updated'             => 'Ticket updated.',
		);
		
		$args = array(
			'labels'       => $labels,
			'show_in_rest' => false, // Enables Gutenberg editor
			
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
			
			'menu_icon'     => 'dashicons-tickets-alt',
			'menu_position' => 6,
			
			'has_archive' => false,
			'rewrite'     => false,
		);
		
		register_post_type( 'ticket', $args );
		
	}
	
	/**
	 * Add filters to the post list screen for Tickets, allowing to filter by ticket status, type of request, etc.
	 *
	 * @return void
	 */
	public function register_post_type_filters() {
		$post_type = 'ticket';
		$taxonomies = array();
		$acf_fields = array(
			'field_67b24d44f8faa', // status
			'field_6819388656ab9', // type_of_request
			'field_6819389a56aba', // severity
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
		if ( get_post_type( $post_id ) === 'ticket' ) {
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
			array( 'status--qef-type-select--' => $columns['status--qef-type-select--'] ),
			array( 'type_of_request--qef-type-radio--' => $columns['type_of_request--qef-type-radio--'] ),
			array( 'name--qef-type-text--' => $columns['name--qef-type-text--'] ),
			array( 'asset--qef-type-post_object--' => $columns['asset--qef-type-post_object--'] ),
			array( 'featured_image' => 'Image' ),
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
		 if ( $column === 'featured_image' ) {
			$full_image_url = get_the_post_thumbnail_url( $post_id, 'full' );
			
			echo '<a href="' . esc_attr( $full_image_url ) . '" target="_blank" rel="noopener noreferrer">';
			echo get_the_post_thumbnail( $post_id, 'thumbnail', array( 'style' => 'width: 50px; height: auto;' ) );
			echo '</a>';
		 }
	}
	
	/**
	 * Send notifications when adding a new ticket via GF Advanced Post Creation
	 *
	 * @param int $ticket_id
	 *
	 * @return void
	 */
	public function maybe_notify_on_insert( $ticket_id ) {
		if ( get_post_type( $ticket_id ) !== 'ticket' ) {
			return;
		}
		$new_status = get_post_meta( $ticket_id, 'status', true ); // "New"
		$this->send_status_notifications( $ticket_id, null, $new_status );
	}
	
	/**
	 * Send notifications when changing the "status" field of a ticket
	 *
	 * @param string $new_status
	 * @param int $ticket_id
	 *
	 * @return string
	 */
	public function maybe_notify_on_update( $new_status, $ticket_id ) {
		$prev_status = get_post_meta( $ticket_id, 'status', true );
		if ( $new_status !== $prev_status ) {
			$this->send_status_notifications( $ticket_id, $prev_status, $new_status );
		}
		
		return $new_status;
	}
	
	/**
	 * Notify the emails/phones associated with the ticket's asset's categories
	 *
	 * @param int $ticket_id
	 * @param string|null $prev_status
	 * @param string $new_status
	 *
	 * @return void
	 */
	public function send_status_notifications( $ticket_id, $prev_status, $new_status ) {
		// Ignore if the status is not changed
		if ( $prev_status === $new_status ) return;
		
		// Check if the new status is allowed to receive notifications from the settings (Bounds Hay -> Notify Ticket Statuses)
		$allowed_notify_statuses = get_field( 'notify_ticket_statuses', 'bhe_settings' );
		if ( !$allowed_notify_statuses || !in_array( $new_status, $allowed_notify_statuses ) ) {
			return;
		}
		
		// get the asset categories
		$asset_id = get_post_meta( $ticket_id, 'asset', true );
		$terms = get_the_terms( $asset_id, 'notify-cat' );
		if ( !$terms ) {
			return;
		}
		
		$emails = array();
		$sms_contacts = array();
		
		// if this term has notifications, add them to the list
		foreach( $terms as $term ) {
			if ( $term_emails = get_term_meta( $term->term_id, 'notifications_email', true ) ) {
				$emails = array_merge( $emails, BHE_Utility::split_multiline_emails( $term_emails ) );
			}
			
			if ( $term_sms_contacts = get_term_meta( $term->term_id, 'notifications_phone', true ) ) {
				$term_sms_contacts = array_map( function( $phone ) {
					$decoded_phone = json_decode( $phone, true );
					
					return $decoded_phone[0];
				}, $term_sms_contacts );
				$sms_contacts = array_merge( $sms_contacts, $term_sms_contacts );
			}
		}
		
		// trim whitespace
		$emails = array_map( 'trim', $emails );
		$sms_contacts = array_map( 'trim', $sms_contacts );
		
		// remove duplicates
		$emails = array_unique( $emails );
		$sms_contacts = array_unique( $sms_contacts );
		
		if ( count( $emails ) ) {
			$this->send_notification_emails( $emails, $ticket_id, $prev_status, $new_status );
		}
		
		if ( count( $sms_contacts ) ) {
			$this->send_notification_texts( $sms_contacts, $ticket_id, $prev_status, $new_status );
		}
	}
	
	/**
	 * Send notification emails, assuming the email settings are configured
	 *
	 * @param array $emails
	 * @param int $ticket_id
	 * @param string|null $prev_status
	 * @param string $new_status
	 *
	 * @return void
	 */
	public function send_notification_emails( $emails, $ticket_id, $prev_status, $new_status ) {
		$subject_template = $prev_status
			? get_option( 'bhe_settings_ticket_notification_email_subject_updated' )
			: get_option( 'bhe_settings_ticket_notification_email_subject_new' );
		$body_template = $prev_status
			? get_option( 'bhe_settings_ticket_notification_email_body_updated' )
			: get_option( 'bhe_settings_ticket_notification_email_body_new' );
		
		if ( !$subject_template || !$body_template ) {
			return;
		}
		
		$subject = $this->replace_tags_in_notification_templates( $subject_template, $ticket_id, $prev_status, $new_status );
		$body = $this->replace_tags_in_notification_templates( $body_template, $ticket_id, $prev_status, $new_status );
		
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		
		add_filter( 'wp_mail_from_name', '__return_empty_string' );
		
		foreach( $emails as $email ) {
			// $headers[] = 'Cc: ' . $email;
			wp_mail( $email, $subject, $body, $headers );
		}
		
		remove_filter( 'wp_mail_from_name', '__return_empty_string' );
	}
	
	/**
	 * Send notification texts, assuming the SMS settings are configured
	 *
	 * @param array $sms_contacts
	 * @param int $ticket_id
	 * @param string|null $prev_status
	 * @param string $new_status
	 *
	 * @return void
	 */
	public function send_notification_texts( $sms_contacts, $ticket_id, $prev_status, $new_status ) {
		$is_new = ! $prev_status;
		
		$template = $is_new
			? get_option( 'bhe_settings_ticket_notification_phone_new' )
			: get_option( 'bhe_settings_ticket_notification_phone_updated' );
		
		if ( !$template ) {
			return;
		}
		
		$message = $this->replace_tags_in_notification_templates( $template, $ticket_id, $prev_status, $new_status );
		
		$attachment_id = $is_new ? get_post_thumbnail_id( $ticket_id ) : false;
		
		foreach( $sms_contacts as $contact ) {
			$curl = curl_init();
			curl_setopt_array( $curl, $this->get_curl_options( $contact, $message, $attachment_id ) );
			$response = curl_exec( $curl );
			$err = curl_error( $curl );
			curl_close( $curl );
			
			if ( $err ) {
				error_log( 'cURL Error #:' . $err );
			}
		}
	}
	
	/**
	 * Get the cURL options for sending an SMS via the HighLevel API
	 *
	 * @param string $contact
	 * @param string $message
	 * @param int|false $attachment_id  Image to attach to the message, if provided
	 * @param boolean $debug
	 *
	 * @return array
	 */
	public function get_curl_options( $contact, $message, $attachment_id = false, $debug = false ) {
		$args = array(
			'type'      => 'SMS',
			'contactId' => $contact,
			'message'   => $message
		);
		
		if ( $attachment_id ) {
			$image_url = wp_get_attachment_image_src( $attachment_id, 'large' );
			if ( $image_url ) {
				$args['attachments'] = array( $image_url[0] );
			}
		}
		
		// Get API settings
		$api_settings = BHE_Utility::get_gohighlevel_api_settings();
		
		return array(
			CURLOPT_URL            => $debug
				? 'https://stoplight.io/mocks/highlevel/integrations/39582856/conversations/messages'
				: 'https://services.leadconnectorhq.com/conversations/messages',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER     => array(
				'Accept: application/json',
				'Authorization: Bearer ' . $api_settings['private_key'],
				'Content-Type: application/json',
				'Version: 2021-04-15'
			),
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode( $args ),
		);
	}
	
	/**
	 * Get the merge tags for a ticket, to be used in notifications
	 *
	 * @param int $ticket_id
	 * @param string|null $previous_status
	 * @param string|null $new_status
	 *
	 * @return array
	 */
	public function get_ticket_merge_tags( $ticket_id, $previous_status = null, $new_status = null ) {
		$asset_id = get_field( 'asset', $ticket_id );
		
		if ( $new_status === null ) {
			$new_status = get_field( 'status', $ticket_id );
		}
		
		$merge_tags = array(
			'{id}'              => $ticket_id,
			'{title}'           => get_the_title( $ticket_id ),
			'{edit_url}'        => get_edit_post_link( $ticket_id ),
			'{post_date}'       => get_the_date( 'F j, Y', $ticket_id ),
			'{today_date}'      => current_time( 'F j, Y' ),
			'{previous_status}' => $previous_status,
			'{new_status}'      => $new_status,
			
			// Asset
			'{asset_id}'        => $asset_id,
			'{asset_title}'     => get_the_title( $asset_id ),
			'{type_of_request}' => get_field( 'type_of_request', $ticket_id ), // Service or Repair
			'{severity}' => get_field( 'severity', $ticket_id ), // Blank, Out of Service, Needs Attention ...
			
			// Author
			'{author_name}'     => get_field( 'name', $ticket_id ),
			'{author_email}'    => get_field( 'email', $ticket_id ),
			'{author_phone}'    => get_field( 'phone_number', $ticket_id ),
			'{body}'            => get_field( 'body', $ticket_id ),
		);
		
		return $merge_tags;
	}
	
	/**
	 * Replace tags in notification templates
	 *
	 * @param string $template
	 * @param int $ticket_id
	 * @param string|null $prev_status
	 * @param string $new_status
	 *
	 * @return string
	 */
	public function replace_tags_in_notification_templates( $template, $ticket_id, $prev_status, $new_status ) {
		$merge_tags = $this->get_ticket_merge_tags( $ticket_id, $prev_status, $new_status );
		
		return str_replace( array_keys( $merge_tags ), array_values( $merge_tags ), $template );
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
		
		if ( $query->get( 'post_type' ) === 'ticket' && $query->get( 'orderby' ) === $default_orderby ) {
			$query->set( 'orderby', 'date' );
			$query->set( 'order', 'DESC' );
		}
	}
	
	/**
	 * When visiting the URL to a ticket, redirect to edit the ticket instead (admins). For other users, redirect to the home page.
	 * @return void
	 */
	public function prevent_direct_access() {
		if ( is_singular( 'ticket' ) ) {
			if ( current_user_can( 'edit_post', get_the_ID() ) ) {
				$edit_url = admin_url('post.php?post='. get_the_ID() .'&action=edit');
				wp_redirect( $edit_url );
			}else{
				wp_redirect( home_url() );
			}
			exit;
		}
	}
	
	/**
	 * Display information from the attached asset, based on the asset type
	 * @return void
	 */
	public function add_asset_detail_meta_box() {
		add_meta_box(
			'ticket_asset_details',
			'Ticket - Asset Details',
			array( $this, 'display_asset_detail_meta_box' ),
			'ticket',
			'normal',
			'low'
		);
		
		add_meta_box(
			'ticket_asset_feedback',
			'Ticket - Feedback',
			array( $this, 'display_feedback_requested_meta_box' ),
			'ticket',
			'normal',
			'low'
		);
	}
	
	/**
	 * Display the asset details meta box content
	 * @return void
	 */
	public function display_asset_detail_meta_box() {
		$ticket_id = get_the_ID();
		$asset_id = get_field( 'asset', $ticket_id );
		
		if ( !$asset_id ) {
			echo '<p>No asset attached to this ticket.</p>';
			return;
		}
		
		$notify_categories = get_the_terms( $asset_id, 'notify-cat' );
		$asset_type_terms = get_the_terms( $asset_id, 'asset-type' );
		
		$edit_url = get_edit_post_link( $asset_id );
		$html = BHE_Asset::get_asset_type_summary( $asset_id );
		
		?>
		<div class="asset-type-summary">
			<h3><?php echo get_the_title( $asset_id ); ?> (<a href="<?php echo esc_url($edit_url); ?>">Edit</a>)</h3>
			
			<?php
			// Display the asset's post thumbnail
			if ( has_post_thumbnail($asset_id) ) {
				echo '<div class="asset-thumbnail">';
				echo get_the_post_thumbnail( $asset_id, 'medium' );
				echo '</div>';
			}
			?>
			
			<?php
			// Display a list of the asset's notify categories
			?>
			<div class="asset-taxonomy notify-cat">
				<span class="label"><?php echo _n('Notify Category:', 'Notify Categories:', count($notify_categories ?: array())); ?></span>
				<?php
				if ( $notify_categories && !is_wp_error( $notify_categories ) ) {
					foreach( $notify_categories as $i => $term ) {
						$term_url = get_edit_term_link( $term );
						echo '<span class="term"><a href="'. esc_url($term_url) .'">' . esc_html( $term->name ) . '</a></span>';
						if ( $i < count( $notify_categories ) - 1 ) {
							echo ', ';
						}
					}
				}else{
					echo '<span class="term">None</span>';
				}
				?>
			</div>
			
			<?php
			// Display a list of the asset's asset types
			?>
			<div class="asset-taxonomy asset-type">
				<span class="label"><?php echo _n('Asset Type:', 'Asset Types:', count($asset_type_terms ?: array())); ?></span>
				<?php
				if ( $asset_type_terms && !is_wp_error( $asset_type_terms ) ) {
					foreach( $asset_type_terms as $term ) {
						$term_url = get_edit_term_link( $term );
						echo '<span class="term"><a href="'. esc_url($term_url) .'">' . esc_html( $term->name ) . '</a></span>';
						if ( $i < count( $notify_categories ) - 1 ) {
							echo ', ';
						}
					}
				}else{
					echo '<span class="term">None</span>';
				}
				?>
			</div>
			
			<?php
			if ( $html ) {
				echo '<div class="summary-content">';
				echo $html;
				echo '</div>';
			}
			?>
		</div>
		<?php
	}
	
	
	/**
	 * Display the feedback requested meta box content
	 * @return void
	 */
	public function display_feedback_requested_meta_box() {
		$ticket_id = get_the_ID();
		
		$args = array(
			'post_type'      => 'ticket-feedback',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => 'ticket_id',
					'value'   => $ticket_id,
					'compare' => '='
				)
			)
		);
		
		$query = new WP_Query( $args );
		
		$entry_id = get_post_meta( $ticket_id, 'entry_id', true );
		
		?>
		<div class="ticket-feedback-group">
		
			<?php
			if ( $entry_id ) {
				$entry = GFAPI::get_entry( $entry_id );
				if ( $entry && $entry['form_id'] ) {
					echo '<p><span class="dashicons dashicons-edit-page"></span> This ticket was created by entry #'. $entry_id .'. <a href="'. admin_url('admin.php?page=gf_entries&view=entry&id=' . $entry['form_id'] . '&lid=' . $entry['id']) .'" target="_blank" rel="noopener noreferrer">View Entry</a></p>';
				} else {
					echo '<p><span class="dashicons dashicons-warning"></span> This ticket was created by entry #'. $entry_id .'. However, that entry could not be found.</p>';
				}
			} else {
				echo '<p><span class="dashicons dashicons-warning"></span> This ticket was not created by a form entry.</p>';
			}
			?>
		
			<h3>Feedback History:</h3>
			
			<?php
			if ( $query->have_posts() ) {
				echo '<div class="ticket-feedback-list">';
				while( $query->have_posts() ): $query->the_post();
					$ticket_feedback_id = get_the_ID();
					$feedback_status = get_field( 'status', $ticket_feedback_id );
					$feedback_form_id = get_field( 'feedback_form_id', $ticket_feedback_id );
					$feedback_date = get_the_date( 'F j, Y g:i:s a', $ticket_feedback_id );
					$date_relative = human_time_diff( get_the_time( 'U', $ticket_feedback_id ), current_time( 'timestamp' ) );
					$view_link = get_edit_post_link( $ticket_feedback_id );
					
					echo '<div class="ticket-feedback-item">';
					echo '<h4>Feedback requested <abbr title="'. esc_attr($feedback_date) .'">'. $date_relative .' ago</abbr> (<a href="'. esc_url( $view_link ) .'" target="_blank" rel="noopener noreferrer" class="">Edit</a>)</h4>';
					
					if ( $feedback_status != 'Closed' ) {
						echo '<p class="feedback-status">Status: <strong>' . esc_html( $feedback_status ) . '</strong></p>';
						
						if ( $feedback_form_id ) {
							$form = GFAPI::get_form( $feedback_form_id );
							if ( !is_wp_error( $form ) ) {
								echo '<p class="feedback-form">Form: ' . esc_html( $form['title'] ) . ' (#' . esc_html( $form['id'] ) . ', <a href="' . admin_url( 'admin.php?page=gf_entries&id=' . $form['id'] ) . '" target="_blank" rel="noopener noreferrer">Edit</a>)</p>';
							}
						}else{
							echo '<p class="feedback-form"><em>No feedback form was selected.</em></p>';
						}
					}else{
						$ts = strtotime( get_post_meta( $ticket_feedback_id, 'entry_date_created', true ) );
						$feedback_date = date( 'F j, Y g:i:s a', $ts );
						echo '<p>Feedback received <abbr title="'. esc_attr($feedback_date) .'">'. $date_relative .' ago</abbr></p>';
						
						// View entry link
						$entry_id = get_post_meta( $ticket_feedback_id, 'entry_id', true );
						$entry = $entry_id ? GFAPI::get_entry( $entry_id ) : false;
						if ( $entry ) {
							$edit_entry_link = admin_url( 'admin.php?page=gf_entries&view=entry&id=' . $entry['form_id'] . '&lid=' . $entry['id'] );
							echo '<p><a href="' . esc_url( $edit_entry_link ) . '" target="_blank" rel="noopener noreferrer" class="button button-secondary">View Entry</a></p>';
						}else{
							echo '<p><em>No entry was created for this feedback.</em></p>';
						}
					}
					
					echo '</div>';
				endwhile;
				wp_reset_postdata();
				echo '</div>';
				
			}else{
				echo '<p>No feedback has been requested for this ticket.</p>';
			}
			?>
			
			<p class="description">To request new feedback from the ticket author, change the ticket status to "Request Feedback".</p>
		</div>
		<?php
	}
	
	/**
	 * Fill the field from the Ticket field "feedback_form_id" with a list of Gravity Forms from the Bounds Hay settings -> feedback_form_ids
	 *
	 * @param array $field
	 *
	 * @return array
	 */
	public static function populate_feedback_form_id( $field ) {
		if ( acf_is_screen('acf-field-group') ) return $field;
		if ( acf_is_screen('acf_page_acf-tools') ) return $field;
		
		$feedback_form_ids = get_option( 'bhe_settings_feedback_form_ids' );
		
		if ( !$feedback_form_ids ) {
			return $field;
		}
		
		$forms = GFAPI::get_forms();
		$choices = array();
		
		foreach( $forms as $form ) {
			if ( in_array( $form['id'], $feedback_form_ids ) ) {
				$choices[ $form['id'] ] = $form['title'] . ' (#' . $form['id'] . ')';
			}
		}
		
		$field['choices'] = $choices;
		
		return $field;
	}
	
	/**
	 * Fill the message of the "Feedback Form" field with the name of the selected form from the select field (with the same name)
	 *
	 * @param array $field
	 *
	 * @return array
	 */
	public static function populate_feedback_form_name( $field ) {
		if ( acf_is_screen('acf-field-group') ) return $field;
		if ( acf_is_screen('acf_page_acf-tools') ) return $field;
		
		$post_id = get_the_ID();
		
		if ( get_post_type( $post_id ) == 'ticket' || get_post_type( $post_id ) == 'ticket-feedback' ) {
			
			$form_id = get_post_meta( $post_id, 'feedback_form_id', true );
			
			if ( $form_id ) {
				$form = GFAPI::get_form( $form_id );
				if ( !is_wp_error( $form ) ) {
					$form_link = admin_url('admin.php?page=gf_entries&id=' . $form_id);
					$field['message'] = 'Feedback Form: <a href="' . esc_url($form_link) .'" target="_blank" rel="noopener noreferrer">' . $form['title'] . ' (#' . $form['id'] . ')</a>';
				}
			}
			
			if ( empty($field['message']) ) {
				if ( get_post_type( $post_id ) == 'ticket' ) {
					$field['message'] = 'Change the status to Request Feedback if you need additional information from the user.';
				}else{
					$field['message'] = 'No feedback form selected.';
				}
			}
			
		}
		
		return $field;
	}
	
	/**
	 * Fill the "Conditional Current Status (Hidden)" field on a ticket
	 *
	 * @param string $value
	 * @param int $post_id
	 * @param array $field
	 *
	 * @return string
	 */
	public function populate_conditional_current_status( $value, $post_id, $field ) {
		if ( acf_is_screen('acf-field-group') ) return $value;
		if ( acf_is_screen('acf_page_acf-tools') ) return $value;
		
		// Always fill the current status. (Ignore the existing value of this field)
		$value = $post_id ? get_field( 'status', $post_id ) : '';
		
		return $value;
	}
	
}

// Initialize the object
BHE_Tickets::get_instance();
