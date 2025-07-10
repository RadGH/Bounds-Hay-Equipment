<?php

class BHE_Asset {
	
	/** @type BHE_Instance_Admin_Post_Type_Filters $post_type_filters */
	public $post_type_filters;

	public function __construct() {

		// Register a post type for "Asset"
		add_action( 'init', array( $this, 'register_post_type' ), 6 );
		
		// Add filters to the post list screen for Tickets
		$this->register_post_type_filters();

		// Disable comments for both post types
		add_filter( 'comments_open', array( $this, 'disable_post_comments' ), 10, 2 );

		// Add custom columns
		add_filter( 'manage_asset_posts_columns', array( $this, 'add_custom_columns' ) );

		// Display custom columns
		add_action( 'manage_asset_posts_custom_column', array( $this, 'display_custom_columns' ), 10, 2 );

		// Add a custom post meta box to display the QR Code details
		add_action( 'add_meta_boxes', array( $this, 'add_asset_meta_box' ) );

		// When saving an asset that does not have a QR code, create a new QR code and assign it automatically
		add_action( 'acf/save_post', array( $this, 'on_save_post' ), 30 );

		// Pre-populate the Gravity Forms field "bh_post_id" when visiting an asset page
		add_filter( 'gform_field_value_bh_post_id', array( $this, 'gf_prepopulate_bh_post_id' ) );

		// Password protect the asset template using the global password from the settings
		add_filter( 'get_block_templates', array( $this, 'add_password_to_template' ) );
		add_filter( 'protected_title_format', array( $this, 'dont_show_protected_title' ) );

		// SMS notifications: Fetch the options for the select2 "search" field when requested via ajax
		add_filter( 'acf/fields/select/query/key=field_67c9c9964066d', array( $this, 'fetch_contacts_from_highlevel_api' ), 10, 2 );

		// SMS notifications: Make sure the saved values in the checkbox field are available as field options
		add_filter( 'acf/prepare_field/key=field_67ec3cc67b877', array( $this, 'add_sms_checkbox_values_as_options' ) );

		// SMS notifications: Don't bother saving the select2 "search" field; the checkbox field is the source of truth
		add_filter( 'acf/update_value/key=field_67c9c9964066d', '__return_false' );
		
		// Add custom columns to the Notify Categories taxonomy
		add_filter( 'manage_edit-notify-cat_columns', array( $this, 'add_asset_cat_custom_columns' ) );
		
		// Display custom columns for the Notify Categories taxonomy
		add_filter( 'manage_notify-cat_custom_column', array( $this, 'display_asset_cat_custom_columns' ), 10, 3 );
		
	}

	// Singleton instance
	protected static $instance = null;

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	// Utilities

	// Hooks
	/**
	 * Register the "Asset" post type
	 */
	public function register_post_type() {

		$labels = array(
			'menu_name'                => 'Assets',
			'name'                     => 'Assets',
			'singular_name'            => 'Asset',
			'add_new'                  => 'Add New',
			'add_new_item'             => 'Add New Asset',
			'edit_item'                => 'Edit Asset',
			'new_item'                 => 'New Asset',
			'view_item'                => 'View Asset',
			'search_items'             => 'Search Assets',
			'not_found'                => 'No assets found',
			'not_found_in_trash'       => 'No assets found in trash',
			'parent_item_colon'        => 'Parent Asset:',
			'all_items'                => 'All Assets',
			'archives'                 => 'Asset Archives',
			'insert_into_item'         => /** @lang text */
				'Insert into asset',
			'uploaded_to_this_item'    => 'Uploaded to this asset',
			'featured_image'           => 'Asset Image',
			'set_featured_image'       => 'Set asset image',
			'remove_featured_image'    => 'Remove asset image',
			'use_featured_image'       => 'Use as asset image',
			'filter_items_list'        => 'Filter asset list',
			'items_list_navigation'    => 'Asset list navigation',
			'items_list'               => 'Asset list',
			'item_published'           => 'Asset published',
			'item_published_privately' => 'Asset privately published',
			'item_reverted_to_draft'   => 'Asset reverted to draft',
			'item_scheduled'           => 'Asset scheduled',
			'item_updated'             => 'Asset updated',
		);

		$args = array(
			'labels'       => $labels,
			'show_in_rest' => true, // Enables Gutenberg editor

			'supports'         => array(
				'title',
				'author',
				'thumbnail',
				// 'comments',
				// 'editor',
			),
			'delete_with_user' => false,

			'public'              => true,
			'hierarchical'        => true,
			'exclude_from_search' => true,
			'show_in_nav_menus'   => false,

			'menu_icon'     => 'dashicons-portfolio',
			'menu_position' => 5,

			'has_archive' => false,
			'rewrite'     => true,
			'taxonomies'  => array( 'notify-cat', 'asset-type' ),
		);

		register_post_type( 'asset', $args );

		// Register a taxonomy called "notify-cat"
		register_taxonomy( 'notify-cat', 'asset', array(
			'label'             => 'Notify Categories',
			'labels'            => array(
				'search_items' => 'Search Notify Categories',
				'all_items'    => 'All Notify Categories',
				'edit_item'    => 'Edit Notify Category',
				'update_item'  => 'Update Notify Category',
				'add_new_item' => 'Add New Notify Category',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
		) );

		// Register a taxonomy called "asset-type"
		register_taxonomy( 'asset-type', 'asset', array(
			'label'             => 'Asset Type',
			'labels'            => array(
				'search_items' => 'Search Asset Types',
				'all_items'    => 'All Asset Types',
				'edit_item'    => 'Edit Asset Type',
				'update_item'  => 'Update Asset Type',
				'add_new_item' => 'Add New Asset Type',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
		) );

	}
	
	/**
	 * Add filters to the post list screen for Tickets, allowing to filter by ticket status, type of request, etc.
	 *
	 * @return void
	 */
	public function register_post_type_filters() {
		$post_type = 'asset';
		$taxonomies = array(
			'notify-cat',
			'asset-type',
		);
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
		if ( get_post_type( $post_id ) === 'asset' ) {
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
			array( 'featured_image' => 'Featured Image' ),
			array( 'asset-qr-code' => 'QR Code' ),
			array( 'taxonomy-notify-cat' => $columns['taxonomy-notify-cat'] ),
			array( 'taxonomy-asset-type' => $columns['taxonomy-asset-type'] ),
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
			echo get_the_post_thumbnail( $post_id, 'medium', array( 'style' => 'max-width: 100%; height: auto;' ) );
		}

		if ( $column === 'asset-qr-code' ) {
			$qr_code_post_id = get_field( 'qr_code_post_id', $post_id );
			if ( $qr_code_post_id ) {
				$image_url = wp_get_attachment_image_url( get_post_thumbnail_id( $qr_code_post_id ), 'full' );

				echo '<a href="' . $image_url . '" target="_blank">';
				echo get_the_post_thumbnail( $qr_code_post_id, 'thumbnail' );
				echo '</a>';

				$edit_link = get_edit_post_link( $qr_code_post_id );
				if ( $edit_link ) {
					echo '<div class="row-actions">';
					echo '<a href="' . $edit_link . '">Edit QR Code</a>';
					echo ' | ';
					echo 'Hits: ' . BHE_QR_Codes::get_instance()->get_hits( $qr_code_post_id );
					echo '</div>';
				}
			}
		}
	}

	/**
	 * Add a custom post meta box to display the QR Code details
	 */
	public function add_asset_meta_box() {
		add_meta_box(
			'asset_qr_code',
			'Asset QR Code',
			array( $this, 'display_asset_meta_box' ),
			'asset',
			'side',
			'low'
		);
	}

	/**
	 * Display the QR Code meta box
	 *
	 * @param WP_Post $post
	 */
	public function display_asset_meta_box( $post ) {
		$qr_code_post_id = get_field( 'qr_code_post_id', $post->ID );

		if ( $qr_code_post_id ) {
			// Display the QR Code image
			$image_url = wp_get_attachment_image_url( get_post_thumbnail_id( $qr_code_post_id ), 'full' );

			echo '<p>';
			echo '<strong>QR Code:</strong> <br>';

			echo '<a href="' . $image_url . '" target="_blank">';
			echo get_the_post_thumbnail( $qr_code_post_id, 'full', array( 'style' => 'max-width: 200px; height: auto;' ) );
			echo '</a>';
			echo '</p>';

			echo '<p>';
			echo '<a href="' . $image_url . '" target="_blank" class="button button-secondary">View / Print</a> ';
			echo '<a href="' . get_edit_post_link( $qr_code_post_id ) . '" target="_blank" class="button button-secondary">Edit</a> ';
			echo '</p>';
		}
	}

	/**
	 * When saving an asset that does not have a QR code, create a new QR code and assign it automatically
	 *
	 * @param int $post_id
	 */
	public function on_save_post( $post_id ) {
		if ( get_post_type( $post_id ) !== 'asset' ) {
			return;
		}

		// Ignore certain post statuses
		if ( in_array( get_post_status( $post_id ), array( 'trash', 'auto-draft' ) ) ) {
			return;
		}

		// Ignore during autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check if the post has a QR code
		$qr_code_post_id = get_field( 'qr_code_post_id', $post_id );
		if ( $qr_code_post_id ) {
			return;
		}

		// Create a new QR code
		$qr_code_post_id = BHE_QR_Codes::get_instance()->create_qr_code_for_post( $post_id );

		// Assign the QR code to the asset
		if ( $qr_code_post_id ) {
			update_field( 'qr_code_post_id', $qr_code_post_id, $post_id );
		}
	}

	/**
	 * Pre-populate the Gravity Forms field "bh_post_id" when visiting an asset page
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function gf_prepopulate_bh_post_id( $value ) {
		// Ignore if already provided by the URL
		if ( isset( $_GET['bh_post_id'] ) ) {
			return $value;
		}

		// Apply only to the asset post type
		if ( is_singular( 'asset' ) ) {
			global $post;

			return $post->ID;
		}

		return $value;
	}

	/**
	 * Replaces the content of the single asset template with a password form if the global password is set and required
	 *
	 * @param $query_result
	 *
	 * @return mixed
	 */
	public function add_password_to_template( $query_result ) {
		if ( is_admin() ) {
			return $query_result;
		}

		foreach ( $query_result as &$result ) {
			if ( $result->slug !== 'single-asset' ) {
				continue;
			}

			$global_pw = get_field( 'global_asset_pw', 'bhe_settings' );

			if ( ! $global_pw ) {
				continue;
			}

			$original_pw                    = $GLOBALS['post']->post_password;
			$GLOBALS['post']->post_password = $global_pw;

			if ( post_password_required() ) {
				$result->content = '<!-- wp:template-part {"slug":"header","theme":"twentytwentyfive"} /-->
<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40)">
' . get_the_password_form() . '
</div>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer","theme":"twentytwentyfive"} /-->';
			}

			$GLOBALS['post']->post_password = $original_pw;
		}

		return $query_result;
	}

	/**
	 * Remove the "Protected:" prefix from the title of password-protected posts
	 *
	 * @return string
	 */
	public function dont_show_protected_title() {
		return '%s';
	}

	public function get_curl_options( $perPage, $page, $search = '' ) {
		$filters = array(
			array(
				'field'    => 'phone',
				'operator' => 'exists'
			)
		);

		if ( $search ) {
			$filters[] = array(
				'group'   => 'OR',
				'filters' => array(
					array(
						'field'    => 'firstNameLowerCase',
						'operator' => 'contains',
						'value'    => $search
					),
					array(
						'field'    => 'lastNameLowerCase',
						'operator' => 'contains',
						'value'    => $search
					)
				)
			);
		}
		
		// Get API settings
		$api_settings = BHE_Utility::get_gohighlevel_api_settings();
		
		return array(
			CURLOPT_URL            => 'https://services.leadconnectorhq.com/contacts/search',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_HTTPHEADER     => array(
				'Accept: application/json',
				'Authorization: Bearer ' . $api_settings['private_key'],
				'Content-Type: application/json',
				'Version: 2021-07-28'
			),
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => json_encode( array(
				'locationId' => $api_settings['location_id'],
				'page'       => $page,
				'pageLimit'  => $perPage,
				'filters'    => $filters,
				'sort'       => array(
					array(
						'field'     => 'lastNameLowerCase',
						'direction' => 'asc'
					)
				)
			) ),
		);
	}

	public function fetch_contacts_from_highlevel_api( $shortcut, $options ) {
		$perPage = 20;
		$page    = $options['paged'] ? intval( $options['paged'] ) : 1;
		$search  = $options['s'];

		$curl = curl_init();
		curl_setopt_array( $curl, $this->get_curl_options( $perPage, $page, $search ) );
		$response = curl_exec( $curl );
		$err      = curl_error( $curl );
		curl_close( $curl );

		if ( $err ) {
			error_log( 'cURL Error #:' . $err );

			return $shortcut;
		}

		$results  = array();
		$response = json_decode( $response );
		foreach ( $response->contacts as $contact ) {
			if ( $contact->phone ) {
				$name      = preg_replace( '/[^\x20-\x7E]/', '', $contact->firstNameLowerCase . ' ' . $contact->lastNameLowerCase );
				$results[] = array(
					'id'   => $contact->id,
					'text' => ucwords( $name ) . ' (' . $contact->phone . ')',
				);
			}
		}

		return array(
			'results' => $results,
			'more'    => $response->total > $perPage * $page
		);
	}

	public function add_sms_checkbox_values_as_options( $field ) {
		if ( is_array( $field['value'] ) ) {
			foreach ( $field['value'] as $value ) {
				// extract the contact name to use as the display value, but keep the json encoded id/name pair as the key
				$contact                    = json_decode( $value, true );
				$field['choices'][ $value ] = $contact[1];
			}
		}

		return $field;
	}
	
	/**
	 * Gets the asset types for the given asset ID.
	 *
	 * @param $asset_id
	 *
	 * @return WP_Term[]
	 */
	public static function get_asset_types( $asset_id ) {
		$asset_types = get_the_terms( $asset_id, 'asset-type' );
		
		if ( ! $asset_types || is_wp_error( $asset_types ) ) {
			return array();
		}else{
			return $asset_types;
		}
	}
	
	/**
	 * Returns an HTML summary of the asset type. Vehicles use a vehicle information form, for example.
	 *
	 * @param $asset_id
	 *
	 * @return string|false
	 */
	public static function get_asset_type_summary( $asset_id ) {
		$asset_types = self::get_asset_types( $asset_id );
		
		$html_parts = array();
		
		foreach( $asset_types as $asset_type ) {
			// Include the template for the asset type summary based on the asset type's slug
			// * /templates/asset-type-summary/vehicle.php
			$file = BHE_PATH . '/templates/asset-type-summary/' . $asset_type->slug . '.php';
			if ( ! file_exists( $file ) ) continue;
			
			ob_start();
			include( $file );
			$html = ob_get_clean();
			
			if ( $html ) $html_parts[] = $html;
		}
		
		if ( $html_parts ) {
			return implode( "\n\n", $html_parts );
		}else{
			return false;
		}
	}
	
	/**
	 * Add custom columns to the Notify Categories taxonomy
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function add_asset_cat_custom_columns( $columns ) {
		if ( isset($columns['description']) ) unset( $columns['description'] );
		
		$new_columns = array(
			'asset_cat_notify' => 'Notifications',
		);
		
		// Add new columns after the 2nd index
		$columns = array_slice( $columns, 0, 2, true )
			+ $new_columns
			+ array_slice( $columns, 2, null, true );

		return $columns;
	}
	
	/**
	 * Display custom columns for the Notify Categories taxonomy
	 *
	 * @param string $content
	 * @param string $column_name
	 * @param int $term_id
	 *
	 * @return string
	 */
	public function display_asset_cat_custom_columns( $content, $column_name, $term_id ) {
		if ( $column_name === 'asset_cat_notify' ) {
			$notify_emails = get_field( 'notifications_email', 'notify-cat_' . $term_id ); // textarea
			$notify_sms = (array) get_field( 'notifications_phone', 'notify-cat_' . $term_id ); // repeater
			
			$none_html = '<span style="opacity: 0.5;">None</span>';
			
			if ( empty($notify_emails) && empty($notify_sms) ) {
				return $none_html;
			}else{
				$email_count = count( BHE_Utility::split_multiline_emails( $notify_emails ) );
				$sms_count = count( $notify_sms );
				
				if ( $email_count === 0 ) $email_count = $none_html;
				if ( $sms_count === 0 ) $sms_count = $none_html;
				
				$content = '<strong>Emails:</strong> ' . $email_count . '<br>';
				$content .= '<strong>SMS:</strong> ' . $sms_count;
			}
		}
		
		return $content;
	}
	
}

// Initialize the object
BHE_Asset::get_instance();
