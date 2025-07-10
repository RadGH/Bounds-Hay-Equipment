<?php

class BHE_Form_Create_Ticket {
	
	public $form_id = 1;
	public $image_field_id = 19;
	
	public function __construct() {
		
		// When a gravity form entry creates a post from the form, attach the image as the featured image to the post
		add_action( 'gform_after_create_post', array( $this, 'gf_attach_image_to_post' ), 10, 3 );
		add_action( 'gform_advancedpostcreation_post_after_creation', array( $this, 'gf_apc_attach_image_to_post' ), 10, 4 );
		
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
	 * When a gravity form entry creates a post from the form, attach the image as the featured image to the post
	 *
	 * @param $post_id
	 * @param $entry
	 * @param $form
	 *
	 * @return void
	 */
	public function gf_attach_image_to_post( $post_id, $entry, $form ) {
		
		// Get the image ID from the entry
		$image_url = rgar( $entry, $this->image_field_id );
		
		// Abort if no image was attached
		if ( ! $image_url ) return;
		
		// Convert to file path
		$image_path = ABSPATH . wp_make_link_relative( $image_url );
		
		if ( ! file_exists($image_path) ) {
			GFFormsModel::add_note( $entry['id'], 0, 'System', 'Image not found at path ' . $image_path );
			return;
		}
		
		// Check if it was already uploaded
		$attachment_id = gform_get_meta( $entry['id'], 'bhe_attachment_id' );
		if ( $attachment_id ) {
			return;
		}
	
		// Upload the image to the media library
		$args = array(
			'guid'           => $image_url,
			'post_mime_type' => 'image/jpeg',
			'post_title'     => sanitize_file_name( basename( $image_path ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		
		$attachment_id = wp_insert_attachment( $args, $image_path, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			GFFormsModel::add_note( $entry['id'], 0, 'System', 'Error uploading image: ' . $attachment_id->get_error_message() );
			return;
		}else{
			GFFormsModel::add_note( $entry['id'], 0, 'System', 'Image #'. $attachment_id .' uploaded, attached to ticket #'. $post_id );
		}
		
		// Add a category to the image
		$term_id = get_field(  'media_category_for_form_uploads', 'bhe_settings' );
		
		if ( $term_id ) {
			wp_set_object_terms( $attachment_id, $term_id, 'media_category' );
		}
		
		// Save to entry
		gform_update_meta( $entry['id'], 'bhe_attachment_id', $attachment_id );
		
		// Set the post thumbnail
		set_post_thumbnail( $post_id, $attachment_id );
		
		// Generate the attachment metadata
		include_once( ABSPATH . 'wp-admin/includes/image.php' );
		
		$attachment_data = wp_generate_attachment_metadata( $attachment_id, $image_path );
		
		if ( ! $attachment_data ) {
			GFFormsModel::add_note( $entry['id'], 0, 'System', 'Error generating attachment metadata' );
		}
		
	}
	
	/**
	 * This hook is for Advanced Post Creation Add-On, which uses slightly different parameters.
	 *
	 * @param $post_id
	 * @param $feed
	 * @param $entry
	 * @param $form
	 *
	 * @return void
	 */
	public function gf_apc_attach_image_to_post( $post_id, $feed, $entry, $form ) {
		$this->gf_attach_image_to_post( $post_id, $entry, $form );
	}
	
}

// Initialize the object
BHE_Form_Create_Ticket::get_instance();
