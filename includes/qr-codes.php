<?php

class BHE_QR_Codes {
	
	public function __construct() {
		
		// Register a post type for "Equipment"
		add_action( 'init', array( $this, 'register_post_type' ), 7 );
		
		// Disable comments for both post types
		add_filter( 'comments_open', array( $this, 'disable_post_comments' ), 10, 2 );
		
		// Add custom columns
		add_filter( 'manage_qr-code_posts_columns', array( $this, 'add_custom_columns' ) );
		
		// Display custom columns
		add_action( 'manage_qr-code_posts_custom_column', array( $this, 'display_custom_columns' ), 10, 2 );
		
		// Add a custom post meta box to display the QR Code details
		add_action( 'add_meta_boxes', array( $this, 'add_qr_code_meta_box' ) );
		
		// When saving a QR code, generate a unique identifier and generate the QR code image that links to it
		add_action( 'save_post', array( $this, 'on_save_qr_code' ) );
		
		// When visiting the QR code post, perform a redirect
		add_action( 'template_redirect', array( $this, 'on_template_redirect' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	/**
	 * Save an image to the media library
	 *
	 * @param string $image_path
	 * @param string $image_url
	 *
	 * @return int
	 */
	protected function save_image_to_media_library( $image_path, $image_url ) {
		// Check the type of file. We'll use this as the 'post_mime_type'.
		$filetype = wp_check_filetype( basename( $image_path ), null );
		
		// Prepare an array of post data for the attachment.
		$attachment = array(
			'guid'           => $image_url,
			'post_mime_type' => $filetype['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $image_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		
		// Insert the attachment.
		$attach_id = wp_insert_attachment( $attachment, $image_path );
		
		// Generate the metadata for the attachment, and update the database record.
		$attach_data = wp_generate_attachment_metadata( $attach_id, $image_path );
		wp_update_attachment_metadata( $attach_id, $attach_data );
		
		return $attach_id;
	}
	
	/**
	 * Get the QR code URL (QR code permalink)
	 *
	 * @param int $post_id
	 *
	 * @return string|false
	 */
	public function get_qr_url( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) return false;
		if ( $post->post_type != 'qr-code' ) return false;
		
		$slug = $post->post_name;
		
		return site_url('/qr/' . $slug);
	}
	
	/**
	 * Get the redirect URL where the QR code will take you after visiting the QR code post
	 *
	 * @param int $post_id
	 *
	 * @return string|false
	 */
	public function get_redirect_url( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) return false;
		if ( $post->post_type != 'qr-code' ) return false;
		
		$redirect_type = get_field( 'redirect_type', $post_id );
		$redirect_url = false;
		
		if ( $redirect_type == 'post' ) {
			$redirect_post_id = get_field( 'redirect_post_id', $post_id );
			if ( $redirect_post_id ) {
				$redirect_url = get_permalink( $redirect_post_id );
				// Add the post ID to the url
				if ( $redirect_url ) {
					$redirect_url = add_query_arg( 'bh_post_id', $redirect_post_id, $redirect_url );
				}
			}
		}else{
			$redirect_url = get_field( 'redirect_url', $post_id );
		}
		
		// Add a referrer to know this QR code was visited
		if ( $redirect_url ) {
			$redirect_url = add_query_arg( 'bh_referrer_slug', $post->post_name, $redirect_url );
		}
		
		return $redirect_url ?: false;
	}
	
	/**
	 * Generate a QR code image using the QR Server API
	 *
	 * @param int $post_id
	 *
	 * @return int
	 */
	public function generate_qr_code_image( $post_id ) {
		// Get the QR code URL
		$qr_url = $this->get_qr_url( $post_id );
		
		// https://api.qrserver.com/v1/create-qr-code/?size=500x500&data=https://example.org/
		$api_url = 'https://api.qrserver.com/v1/create-qr-code/';
		
		$args = array(
			'size' => '500x500',
			'color' => '000000',
			'bgcolor' => 'ffffff',
			// 'color' => 'ee730a',
			// 'bgcolor' => '000000',
			'margin' => 0,
			'qzone' => 2,
			'format' => 'png',
			'data' => urlencode( $qr_url ),
		);
		
		$api_url = add_query_arg( $args, $api_url );
		
		// Perform wp_remote_get to get the image
		$response = wp_remote_get( $api_url );
		
		// Check for errors
		if ( is_wp_error( $response ) ) return false;
		
		// Check for a valid response
		if ( $response['response']['code'] != 200 ) return false;
		
		// Get the image data
		$image_data = wp_remote_retrieve_body( $response );
		
		// Save the image data to the uploads directory
		$upload_dir = wp_upload_dir();
		$upload_path = $upload_dir['path'];
		$upload_url = $upload_dir['url'];
		
		// Save the image to the uploads directory
		$image_path = $upload_path . '/qr-code-' . $post_id . '.png';
		file_put_contents( $image_path, $image_data );
		
		// Save the image to the media library
		$image_url = $upload_url . '/qr-code-' . $post_id . '.png';
		$image_id = $this->save_image_to_media_library( $image_path, $image_url );
		
		// Assign the media category for the QR Code image
		$term_id = get_field(  'media_category_for_qr_code', 'bhe_settings' );
		
		if ( $term_id ) {
			wp_set_object_terms( $image_id, $term_id, 'media_category' );
		}
		
		// Return the image ID
		return $image_id;
	}
	
	
	public function record_hit( $post_id ) {
		$hits = get_post_meta( $post_id, 'qr_code_hits', true );
		$hits = $hits ? $hits + 1 : 1;
		update_post_meta( $post_id, 'qr_code_hits', $hits );
	}
	
	public function get_hits( $post_id ) {
		$i = (int) get_post_meta( $post_id, 'qr_code_hits', true );
		if ( $i < 1 ) $i = 0;
		return $i;
	}
	
	/**
	 * Create a QR code for a post
	 *
	 * @param int|WP_Post $target_post
	 *
	 * @return int|false
	 */
	public function create_qr_code_for_post( $target_post ) {
		if ( is_numeric($target_post) ) {
			$target_post = get_post( $target_post );
		}
		
		// Create a QR code with redirect_type = post and link to the target post
		$post_id = wp_insert_post( array(
			'post_type' => 'qr-code',
			'post_title' => 'QR Code for ' . $target_post->post_title,
			'post_status' => 'publish',
		) );
		
		// Handle errors
		if ( is_wp_error($post_id) ) return false;
		
		// Set the redirect type to post
		update_field( 'redirect_type', 'post', $post_id );
		
		// Set the redirect post ID
		update_field( 'redirect_post_id', $target_post->ID, $post_id );
		
		// Generate the QR code image
		$this->setup_qr_code( $post_id );
		
		return $post_id;
	}
	
	// Hooks
	/**
	 * Register the "Equipment" post type
	 */
	public function register_post_type() {
		
		$labels = array(
			'menu_name' => 'QR Codes',
			'name' => 'QR Codes',
			'singular_name' => 'QR Code',
			'add_new' => 'Add New QR Code',
			'add_new_item' => 'Add New QR Code',
			'edit_item' => 'Edit QR Code',
			'new_item' => 'New QR Code',
			'view_item' => 'View QR Code',
			'search_items' => 'Search QR Code',
			'not_found' => 'No QR code found',
			'not_found_in_trash' => 'No QR code found in trash',
			'parent_item_colon' => 'Parent QR Code:',
			'all_items' => 'All QR Codes',
			'archives' => 'QR Code Archives',
			'insert_into_item' => /** @lang text */ 'Insert into QR code',
			'uploaded_to_this_item' => 'Uploaded to this QR code',
			'featured_image' => 'QR Code Image',
			'set_featured_image' => 'Set QR code image',
			'remove_featured_image' => 'Remove QR code image',
			'use_featured_image' => 'Use as QR code image',
			'filter_items_list' => 'Filter QR code list',
			'items_list_navigation' => 'QR Code list navigation',
			'items_list' => 'QR Code list',
			'item_published' => 'QR Code sent.',
			'item_published_privately' => 'QR Code sent privately.',
			'item_reverted_to_draft' => 'QR Code reverted to draft.',
			'item_scheduled' => 'QR Code scheduled.',
			'item_updated' => 'QR Code updated.',
		);
		
		$args = array(
			'labels' => $labels,
			'show_in_rest' => false, // Enables Gutenberg editor
			
			'supports' => array(
				'title',
				'author',
				'thumbnail',
				// 'comments',
				// 'editor',
			),
			'delete_with_user' => false,
			
			'public' => true,
			'hierarchical' => true,
			'exclude_from_search' => true,
			'show_in_nav_menus' => false,
			
			'menu_icon' => 'dashicons-tagcloud',
			'menu_position' => 7,
			
			'has_archive' => false,
			'rewrite' => array(
				'slug' => 'qr',
				'with_front' => false,
			),
		);
		
		register_post_type( 'qr-code', $args );
		
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
		if ( get_post_type( $post_id ) === 'qr-code' ) return false;
		
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
			array( 'featured_image' => 'Featured Image' ),
			array( 'qr-hits' => 'Hits' ),
			array( 'asset-redirect' => 'Redirect' ),
			array( 'author' => $columns['author'] ),
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
			echo get_the_post_thumbnail( $post_id, 'thumbnail' );
		}
		
		if ( $column === 'qr-hits' ) {
			echo (int) $this->get_hits( $post_id );
		}
		
		if ( $column === 'asset-redirect' ) {
			$redirect_url = $this->get_redirect_url( $post_id );
			$type = get_field( 'redirect_type', $post_id );
			
			if ( $type == 'post' ) {
				$redirect_post_id = get_field( 'redirect_post_id', $post_id );
				
				$prefix = '';
				$preview_text = ( get_the_title( $redirect_post_id ) ?: '(deleted #'. $redirect_post_id .')' );
				$detail_text = 'Post #' . $redirect_post_id . ' (<a href="'. esc_url( get_edit_post_link( $redirect_post_id ) ) . '" target="_blank">Edit</a>)';
			}else{
				// Remove https:
				$preview_url = str_replace( array('https://', 'http://'), '', $redirect_url );
				$preview_url = remove_query_arg( 'bh_referrer_slug', $preview_url );
				$preview_url = untrailingslashit( $preview_url );
				
				$prefix = '<span class="dashicons dashicons-admin-links"></span> ';
				$preview_text = $preview_url;
				$detail_text = 'Custom URL';
			}
			
			if ( $redirect_url ) {
				echo $prefix;
				echo '<a href="' . esc_url( $redirect_url ) . '" target="_blank">' . esc_html( $preview_text ) . '</a>';
				echo '<div class="row-actions">';
				echo $detail_text;
				echo '</div>';
			} else {
				echo '<span class="dashicons dashicons-no"></span>';
			}
		}
	}
	
	/**
	 * Add a custom post meta box to display the QR Code details
	 */
	public function add_qr_code_meta_box() {
		add_meta_box(
			'qr_code_details',
			'QR Code Details',
			array( $this, 'display_qr_code_meta_box' ),
			'qr-code',
			'normal',
			'high'
		);
	}
	
	/**
	 * Display the QR Code meta box
	 *
	 * @param WP_Post $post
	 */
	public function display_qr_code_meta_box( $post ) {
		$hits = $this->get_hits( $post->ID );
		if ( ! $hits ) $hits = 0;
		
		// Display the QR Code data
		?>
		<p><strong>Hits:</strong> <?php echo intval($hits); ?></p>
		<?php
	}
	
	/**
	 * When saving a QR code, generate a unique identifier and generate the QR code image that links to it
	 *
	 * @param int $post_id
	 */
	public function on_save_qr_code( $post_id ) {
		if ( get_post_type( $post_id ) !== 'qr-code' ) return;
		
		// Ignore certain post statuses
		if ( in_array( get_post_status( $post_id ), array( 'trash', 'auto-draft' ) ) ) return;
		
		// Ignore during autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		
		// If this QR code has not yet been initialized, initialize it
		$initialized_id = get_post_meta( $post_id, 'qr_code_initialized_post_id', true );
		$attachment_id = get_post_thumbnail_id( $post_id );
		
		if ( $post_id != $initialized_id || ! $attachment_id ) {
			$this->setup_qr_code( $post_id );
		}
	}
	
	public function setup_qr_code( $post_id ) {
		// Generate a unique identifier
		$unique_id = get_post_meta( $post_id, 'qr_code_unique_id', true );
		
		if ( ! $unique_id ) {
			$unique_id = uniqid( 'bhe' );
			
			// Save the unique identifier as the post slug
			$post = array(
				'ID'        => $post_id,
				'post_name' => $unique_id,
			);
			
			remove_action( 'save_post', array( $this, 'on_save_qr_code' ) );
			wp_update_post( $post );
			add_action( 'save_post', array( $this, 'on_save_qr_code' ) );
			
			// Save the unique identifier as post meta
			update_post_meta( $post_id, 'qr_code_unique_id', $unique_id );
		}
		
		// Check if a QR code already exists and the attachment is still valid
		$image_id = get_post_meta( $post_id, 'qr_code_image_id', true );
		$attachment_id = get_post_thumbnail_id( $post_id );
		
		if ( ! $image_id || ! $attachment_id ) {
			// Generate the QR code image
			$image_id = $this->generate_qr_code_image( $post_id );
			
			// Save the QR code image
			update_post_meta( $post_id, 'qr_code_image_id', $image_id );
			
			// Save as the featured image
			set_post_thumbnail( $post_id, $image_id );
			
			// Set the parent post of the attachment to the QR code post
			remove_action( 'save_post', array( $this, 'on_save_qr_code' ) );
			wp_update_post( array(
				'ID' => $image_id,
				'post_parent' => $post_id,
			) );
			add_action( 'save_post', array( $this, 'on_save_qr_code' ) );
		}
		
		// Save the post ID as the initialized ID
		update_post_meta( $post_id, 'qr_code_initialized_post_id', $post_id );
		
	}
	
	/**
	 * When visiting the QR code post, perform a redirect
	 */
	public function on_template_redirect() {
		if ( ! is_singular( 'qr-code' ) ) return;
		
		// Record a hit
		$this->record_hit( get_the_ID() );
		
		// Get the QR code URL
		$qr_url = $this->get_redirect_url( get_the_ID() );
		
		// Perform a redirect
		if ( $qr_url ) {
			wp_redirect( $qr_url );
			exit;
		}else{
			wp_die( 'QR code redirect is invalid.', 'QR Code redirect is invalid' );
			exit;
		}
	}
	
}

// Initialize the object
BHE_QR_Codes::get_instance();
