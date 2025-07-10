<?php

class BHE_Settings {
	
	public function __construct() {
		
		// Add an ACF settings page
		add_action( 'acf/init', array( $this, 'add_acf_options_page' ) );
		
		// Remove some admin pages
		add_action( 'admin_menu', array( $this, 'remove_admin_pages' ), 100000 );
		
		// Fill the field from Bounds Hay options "feedback_form_ids" with a list of Gravity Form IDs
		add_filter( 'acf/load_field/key=field_681d276a46b4e', array( $this, 'populate_feedback_form_ids' ) ); // feedback_form_ids (settings)
		
		// Fill the Notify Ticket Statuses using the same statuses from the Ticket Status field
		add_filter( 'acf/load_field/key=field_681e5ff32f28e', array( $this, 'populate_ticket_statuses' ) ); // notify_ticket_statuses (settings)
		
		// If you visit the Posts list on the dashboard, redirect to the Tickets screen instead. (We don't use posts)
		add_action( 'current_screen', array( $this, 'maybe_redirect_posts_to_tickets' ) );
		
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
	 * Add an ACF settings page
	 */
	public function add_acf_options_page() {
		if ( function_exists('acf_add_options_page') ) {
			acf_add_options_page( array(
				'page_title' => null,
				'menu_title' => 'Bounds Hay',
				'menu_slug' => 'bhe-settings-parent',
				'capability' => 'manage_options',
				'post_id' => null,
				'redirect' => true,
				'position' => 8,
			) );
			
			acf_add_options_sub_page( array(
				'parent_slug' => 'bhe-settings-parent',
				'page_title' => 'Bounds Hay Equipment — General Settings (bhe_settings)',
				'menu_title' => 'General Settings',
				'menu_slug' => 'bhe-settings-general',
				'capability' => 'manage_options',
				'post_id' => 'bhe_settings', // get_field( 'something', 'bhe_settings' );
			) );
			
			/*
			acf_add_options_sub_page( array(
				'parent_slug' => 'bhe-settings-parent',
				'page_title' => 'Bounds Hay Equipment — Credentials (bhe_credentials)',
				'menu_title' => 'Credentials',
				'menu_slug' => 'bhe-settings-credentials',
				'capability' => 'manage_options',
				'post_id' => 'bhe_credentials', // get_field( 'something', 'bhe_credentials' );
			) );
			*/
			
		}
	}
	
	/**
	 * Remove some admin pages
	 * @return void
	 */
	public function remove_admin_pages() {
		remove_menu_page( 'edit.php' );
		remove_menu_page( 'edit-comments.php' );
	}
	
	/**
	 * Fill the field from Bounds Hay options "feedback_form_ids" with a list of Gravity Form IDs
	 * @param $field
	 * @return mixed
	 */
	public function populate_feedback_form_ids( $field ) {
		if ( acf_is_screen('acf-field-group') ) return $field;
		if ( acf_is_screen('acf_page_acf-tools') ) return $field;
		
		// Get the list of Gravity Forms
		$forms = GFAPI::get_forms();
		
		// Create an array to hold the choices
		$choices = array();
		
		foreach ( $forms as $form ) {
			$choices[ $form['id'] ] = $form['title'] . ' (#' . $form['id'] . ')';
		}
		
		// Set the choices for the field
		$field['choices'] = $choices;
		
		return $field;
	}
	
	/**
	 * Fill the Notify Ticket Statuses using the same statuses from the Ticket Status field
	 * @param $field
	 * @return mixed
	 */
	public function populate_ticket_statuses( $field ) {
		if ( acf_is_screen('acf-field-group') ) return $field;
		if ( acf_is_screen('acf_page_acf-tools') ) return $field;
		
		$status_field_key = 'field_67b24d44f8faa';
		$status_field = acf_get_field( $status_field_key );
		if ( ! $status_field ) return $field;
		
		// Set the choices for the field
		$field['choices'] = $status_field['choices'];
		
		return $field;
	}
	
	/**
	 * If you visit the Posts list on the dashboard, redirect to the Tickets screen instead. (We don't use posts)
	 * @return void
	 */
	public function maybe_redirect_posts_to_tickets() {
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ( ! $screen ) return;
		
		// Check if you are on the post list page, if so, redirect to ticket screen
		if ( $screen->id == 'edit-post' ) {
			wp_redirect( admin_url( 'edit.php?post_type=ticket' ) );
			exit;
		}
	}
	
	
}

BHE_Settings::get_instance();