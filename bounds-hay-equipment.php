<?php
/*
Plugin Name: Bounds Hay Equipment
Description: Provides the Equipment post types and integrations with Gravity Forms and Advanced Custom Fields. QR codes are generated and assigned to each equipment for relaying to contact forms.
Version: 1.1.3
Author: Radley Sustaire, ZingMap LLC
Author URI: https://zingmap.com/
Created on: 5/30/2025
*/

define( 'BHE_PATH', __DIR__ );
define( 'BHE_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'BHE_VERSION', '1.1.3' );

class BHE_Plugin {
	
	/**
	 * Checks that required plugins are loaded before continuing
	 * @return void
	 */
	public static function load_plugin() {
		
		// Check for required plugins
		$missing_plugins = array();
		
		if ( !class_exists( 'ACF' ) ) {
			$missing_plugins[] = 'Advanced Custom Fields Pro';
		}
		
		if ( !class_exists( 'GFAPI' ) ) {
			$missing_plugins[] = 'Gravity Forms';
		}
		
		if ( $missing_plugins ) {
			self::add_admin_notice( '<strong>Bounds Hay Equipment:</strong> The following plugins are required: ' . implode( ', ', $missing_plugins ) . '.', 'error' );
			
			return;
		}
		
		// Load instances
		require_once( BHE_PATH . '/includes/instances/admin-post-type-filters.php' );
		
		// Load plugin files
		require_once( BHE_PATH . '/includes/assets.php' );
		require_once( BHE_PATH . '/includes/debug.php' );
		require_once( BHE_PATH . '/includes/email.php' );
		require_once( BHE_PATH . '/includes/enqueue.php' );
		require_once( BHE_PATH . '/includes/form-create-ticket.php' );
		require_once( BHE_PATH . '/includes/media.php' );
		require_once( BHE_PATH . '/includes/qr-codes.php' );
		require_once( BHE_PATH . '/includes/scheduled-events.php' );
		require_once( BHE_PATH . '/includes/settings.php' );
		require_once( BHE_PATH . '/includes/shortcodes.php' );
		require_once( BHE_PATH . '/includes/tickets.php' );
		require_once( BHE_PATH . '/includes/ticket-feedback.php' );
		require_once( BHE_PATH . '/includes/ticket-feedback-forms.php' );
		require_once( BHE_PATH . '/includes/export-tickets.php' );
		require_once( BHE_PATH . '/includes/utility.php' );
		
		// After the plugin has been activated, flush rewrite rules, upgrade database, etc.
		add_action( 'admin_init', array( __CLASS__, 'after_plugin_activated' ) );
		
	}
	
	/**
	 * When the plugin is activated, set up the post types and refresh permalinks
	 */
	public static function on_plugin_activation() {
		update_option( 'bhe_plugin_activated', 1, true );
		
	}
	
	/**
	 * Flush rewrite rules if the option is set
	 * @return void
	 */
	public static function after_plugin_activated() {
		if ( get_option( 'bhe_plugin_activated' ) ) {
			
			// Flush rewrite rules
			flush_rewrite_rules();
			
			// Install custom roles
			// Bounds_Gay_Equipment_Users::get_instance()->install_roles();
			
			// Upgrade database
			// Bounds_Gay_Equipment_Upgrade::upgrade();
			
			// Clear the option
			update_option( 'bhe_plugin_activated', 0, true );
			
		}
	}
	
	/**
	 * Adds an admin notice to the dashboard's "admin_notices" hook.
	 *
	 * @param string $message The message to display
	 * @param string $type    The type of notice: info, error, warning, or success. Default is "info"
	 * @param bool $format    Whether to format the message with wpautop()
	 *
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'info', $format = true ) {
		add_action( 'admin_notices', function() use ( $message, $type, $format ) {
			?>
			<div class="notice notice-<?php
			echo $type; ?>">
				<?php
				echo $format ? wpautop( $message ) : $message; ?>
			</div>
			<?php
		} );
	}
	
	/**
	 * Add a link to the settings page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public static function add_settings_link( $links ) {
		return array_merge( array(
			'<a href="edit.php?post_type=asset">Assets</a>',
			'<a href="edit.php?post_type=qr-code">QR Codes</a>',
			'<a href="edit.php?post_type=ticket">Tickets</a>',
			'<a href="admin.php?page=bhe-settings-general">Settings</a>',
		), $links);
	}
	
}

// Add a link to the settings page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'BHE_Plugin', 'add_settings_link' ) );

// When the plugin is activated, set up the post types and refresh permalinks
register_activation_hook( __FILE__, array( 'BHE_Plugin', 'on_plugin_activation' ) );

// Initialize the plugin
add_action( 'plugins_loaded', array( 'BHE_Plugin', 'load_plugin' ) );
