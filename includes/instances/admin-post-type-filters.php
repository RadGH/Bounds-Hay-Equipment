<?php

class BHE_Instance_Admin_Post_Type_Filters {
	
	/**
	 * The post type to filter
	 * @type string $post_type
	 */
	public string $post_type;
	
	/**
	 * Taxonomy dropdowns to display
	 * @type string[] $taxonomies Values are the taxonomy names
	 */
	public array $taxonomies;
	
	/**
	 * ACF field keys to display
	 * @type string[] $acf_field_keys Values are the field keys
	 */
	public array $acf_field_keys;
	
	/**
	 * ACF filters to display
	 * @type array[] $acf_fields Keys are the field key, values are the field array
	 */
	public $acf_fields = null;
	
	/**
	 * Constructor
	 *
	 * @param $post_type
	 * @param $taxonomies
	 * @param $acf_field_keys
	 */
	public function __construct( $post_type, $taxonomies, $acf_field_keys ) {
		$this->post_type = $post_type;
		$this->taxonomies = $taxonomies;
		$this->acf_field_keys = $acf_field_keys;
		
		// Add filters to the post list screen
		add_action( 'restrict_manage_posts', array( $this, 'display_filters' ) );
		
		// Apply filters to the query on the post list screen
		add_filter( 'parse_query', array( $this, 'filter_posts' ) );
		
	}
	
	// Utilities
	
	/**
	 * Gets the ACF Field objects from the ACF Field Keys. Cached for reuse.
	 * @return array
	 */
	public function get_acf_fields() {
		if ( $this->acf_fields === null ) {
			$this->acf_fields = array();
			
			foreach( $this->acf_field_keys as $field_key ) {
				$field = acf_get_field( $field_key );
				if ( !$field ) continue;
				
				$this->acf_fields[ $field_key ] = $field;
			}
		}
		
		return $this->acf_fields;
	}
	
	/**
	 * Get the parameter name for a filter based on the field name
	 *
	 * @param $field_name
	 *
	 * @return string
	 */
	public function get_filter_name( $field_name ) {
		return 'bhe_' . $field_name;
	}
	
	// Hooks
	/**
	 * Add filters to the post list screen
	 * @return void
	 */
	public function display_filters() {
		if ( ! BHE_Utility::is_current_screen( 'edit-' . $this->post_type ) ) return;
		
		foreach ( $this->taxonomies as $taxonomy ) {
			$this->display_taxonomy_filter( $taxonomy );
		}
		
		foreach ( $this->get_acf_fields() as $field ) {
			$this->display_acf_filter( $field );
		}
	}
	
	/**
	 * Displays a filter for a taxonomy
	 *
	 * @param string $taxonomy
	 *
	 * @return void
	 */
	public function display_taxonomy_filter( $taxonomy ) {
		$selected = isset( $_GET[ $taxonomy ] ) ? $_GET[ $taxonomy ] : '';
		$info     = get_taxonomy( $taxonomy );
		
		// $label = $info->labels->all_items ?: 'All ' . $info->labels->name;
		$label = '&ndash;';
		$html_id = $taxonomy . '-filter';
		
		$args = array(
			'show_option_all' => $label,
			'taxonomy'        => $taxonomy,
			'name'            => $taxonomy,
			'selected'        => $selected,
			'id'              => $html_id,
			'show_count'      => true,
			'hide_empty'      => true,
			'orderby'         => 'name',
			'hierarchical'    => true,
			'hide_if_empty'   => true,
			'value_field'     => 'slug',
		);
		
		echo '<div class="bhe-post-type-filter filter-taxonomy taxonomy-' . $taxonomy . '">';
		echo '<label for="' . esc_attr( $taxonomy ) . '">' . esc_html( $info->labels->name ) . '</label>';
		wp_dropdown_categories( $args );
		echo '</div>';
	}
	
	/**
	 * Displays an ACF Field as a filter
	 *
	 * @param array $field
	 *
	 * @return void
	 */
	public function display_acf_filter( $field ) {
		$name = $field['name'];                           // status
		$label = $field['label'];                         // Status
		$choices = $field['choices'] ?? array();          // string["all"] => "All Posts"
		// $field_key = $field['key'];                    // field_67b24d44f8faa
		// $type = $field['type'];                        // select
		// $allow_multiple = $field['multiple'] ?? false; // bool
		
		$field_name = $this->get_filter_name( $name );
		$html_id = $field_name . '-filter';
		$selected_value = isset( $_GET[$field_name] ) ? $_GET[$field_name] : '';
		
		// Display as a select if the field has choices
		if ( !empty( $choices ) ) {
			$all_label = '&ndash;';
			$all_value = '';
			
			// Add the "All" option
			$choices = array_merge( array( $all_value => $all_label ), $choices );
			
			echo '<div class="bhe-post-type-filter filter-field field-' . esc_attr( $name ) . '">';
			echo '<label for="' . esc_attr( $html_id ) . '">' . esc_html( $label ) . '</label>';
			
			echo '<select name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $name ) . '">';
			foreach( $choices as $value => $text ) {
				echo '<option value="' . esc_attr( $value ) . '" ' . selected( $selected_value, $value, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select>';
			
			echo '</div>';
		}
	}
	
	/**
	 * Apply filters to the query on the post list screen
	 *
	 * @param WP_Query $query
	 *
	 * @return WP_Query
	 */
	public function filter_posts( $query ) {
		if ( ! is_admin() ) return $query;
		if ( ! $query->is_main_query() ) return $query;
		if ( ! BHE_Utility::is_current_screen( 'edit-' . $this->post_type ) ) return $query;
		
		$meta_query = array();
		
		foreach ( $this->get_acf_fields() as $field ) {
			$name = $field['name']; // status
			$field_name = $this->get_filter_name( $name );
			$value = $_GET[$field_name] ?? false;
			
			if ( $value ) {
				$meta_query[] = array(
					'key' => $name,
					'value' => $value,
					'compare' => 'LIKE',
				);
			}
		}
		
		if ( $meta_query ) {
			$query->set( 'meta_query', array_merge( $query->get( 'meta_query', array() ), $meta_query ) );
		}
		
		return $query;
	}
	
	
	
}