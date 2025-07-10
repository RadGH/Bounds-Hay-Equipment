<?php


class BHE_Debug {
	
	public function __construct() {
		
		// Outputs dashboard page globals for page detection.
		add_action( 'in_admin_footer', array( $this, 'display_footer_debug_info' ), 100 );
		
		// Check if current user is radley
		/** @see https://assets.boundshay.com/?amiradley */
		add_action( 'after_setup_theme', array( $this, 'am_i_radley' ) );
		
		// Reset meta box positions (for the current user)
		/** @see https://assets.boundshay.com/?reset_metaboxes */
		if ( isset($_GET['reset_metaboxes']) ) {
			add_action( 'after_setup_theme', function() {
				$user_id = get_current_user_id();
				if ( ! $user_id ) {
					echo 'Must be logged in to reset meta box positions';
					exit;
				}
				
				$meta = get_user_meta( $user_id );
				echo '<pre>';
				foreach( $meta as $k => $v ) {
					if ( str_starts_with( $k, 'meta-box-' ) ) {
						delete_user_meta( $user_id, $k );
						echo 'Deleted: ' . $k . '<br>';
					}
				}
				exit;
			} );
		}
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
	 * Check if the current user is Radley
	 * @return bool
	 */
	public function is_radley() {
		// pretending I am NOT radley
		if ( isset($_GET['radley']) && $_GET['radley'] == '0' ) return false;
		
		$visitor_ip = BHE_Utility::get_visitor_ip();
		
		$radleys_vpn = '192.241.231.69';
		$radleys_ip = '68.113.38.243';
		
		return ($radleys_vpn == $visitor_ip || $radleys_ip == $visitor_ip);
	}
	
	/**
	 * Takes an associative array and displays column headers using keys, and values will be displayed if string or var_dumped if array or object.
	 *
	 * @param array $assoc_array
	 *
	 * @return void
	 */
	public function var_dump_table( $assoc_array ) {
		if ( !empty($assoc_array) && is_array($assoc_array) ) {
			echo '<table><tbody>';
			foreach( $assoc_array as $k => $v ) {
				echo '<tr>';
				echo '<th>', $k, '</th>';
				echo '<td>';
				if ( is_array( $v ) || is_object( $v ) ) {
					echo '<pre>';
					print_r( $v );
					echo '</pre>';
				}else if ( $v === false ) {
					echo '<span>(bool) false</span>';
				}else if ( $v === true ) {
					echo '<span>(bool) true</span>';
				}else if ( $v === null ) {
					echo '<span class="value-empty">NULL</span>';
				}else if ( $v === "" ) {
					echo '<span class="value-empty">""</span>';
				}else if ( is_string($v) ) {
					echo esc_html( '"' . $v . '"' );
				}else{
					var_dump( $v );
				}
				echo '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		}else{
			echo '<pre>';
			var_dump( $assoc_array );
			echo '</pre>';
		}
	}
	
	// Hooks
	
	/**
	 * Outputs dashboard page globals for page detection.
	 * Version 1.1
	 *
	 * Example:
	 * /wp-content/plugins/aa-aspen-assessment/includes/_test.php (line 20)
	 * Array
	 * (
	 * [pagenow] => edit.php
	 * [typenow] => aspen-assessment
	 * [plugin_page] =>
	 * [parent_file] => aspen-dashboard
	 * [submenu_file] => edit.php?post_type=aspen-assessment
	 * )
	 */
	public function display_footer_debug_info() {
		global $pagenow, $typenow, $plugin_page, $parent_file, $submenu_file; // useful for knowing current page details
		global $title, $hook_suffix, $current_screen, $wp_locale, $update_title, $total_update_count; // admin-header.php other globals
		global $wp_query;
		
		if ( function_exists('acf_is_screen') && acf_is_screen('toplevel_page_gf_edit_forms') ) return;
		
		// Only show debug info to Radley
		if ( ! $this->is_radley() ) return;
		
		// same as $current_screen
		// $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		
		?>
		<div id="rfs" class="rad-footer-settings">
			<div class="rfs-item">
				<h3>Globals</h3>
				<div class="rfs-output"><?php
					$vars = array(
						'$pagenow' => $pagenow ?? null,
						'$typenow' => $typenow ?? null,
						'$parent_file' => $parent_file ?? null,
						'$submenu_file' => $submenu_file ?? null,
						'$plugin_page' => $plugin_page ?? null,
						'$title' => $title ?? null,
						'$hook_suffix' => $hook_suffix ?? null,
						// '$update_title' => $update_title,
						// '$total_update_count' => $total_update_count,
					);
					
					$this->var_dump_table( $vars );
					?></div>
			</div>
			<div class="rfs-item">
				<h3>Current Screen</h3>
				<div class="rfs-output"><?php
					$vars = array();
					
					if ( isset($current_screen) && $current_screen instanceof WP_Screen ) {
						$vars = array(
							'$current_screen->action' => $current_screen->action,
							'$current_screen->id' => $current_screen->id,
							'$current_screen->base' => $current_screen->base,
							'$current_screen->post_type' => $current_screen->post_type,
							'$current_screen->taxonomy' => $current_screen->taxonomy,
							'$current_screen->is_block_editor' => $current_screen->is_block_editor,
						);
					}
					
					$this->var_dump_table( $vars );
					?></div>
			</div>
			<div class="rfs-item">
				<h3>Current Query</h3>
				<div class="rfs-output"><?php
					// Create an array of any query vars that are different between a default query and the current $wp_query
					$default_query = new WP_Query(array( 'post_type' => 'post' ));
					$vars = array_diff_assoc( (array) $wp_query->query_vars, (array) $default_query->query_vars );
					
					$ignore_keys = array(
						'posts_per_page',
						'posts_per_archive_page',
						'perm',
						'nopaging',
					);
					
					// Remove all the $ignore_keys from $vars
					foreach( $ignore_keys as $key ) {
						if ( isset($vars[$key]) ) unset($vars[$key]);
					}
					
					$this->var_dump_table( $vars );
					?></div>
			</div>
		</div>
		
		<style>
			#wpfooter {
				/*position: static;*/
				clear: both;
			}
			#rfs {
				max-width: calc( 100% - 220px );
				display: grid;
				grid-template-columns: 1fr 1fr 1fr;
				grid-gap: 30px;
			}
			#rfs pre {
				margin-left: 0 !important;
				overflow: auto;

				font-family: 'Fira Code', 'Courier New', monospace;
				font-size: 12px;
				font-weight: 400;
				letter-spacing: -0.03em;
			}

			#rfs table {
				width: 100%;
			}

			#rfs table th,
			#rfs table td {
				padding: 2px 5px;
			}

			#rfs table th {
				text-align: left;
			}

			#rfs table td {
			}

			#rfs table tr:nth-child(even) {
				background: #f9f9f9;
			}

			#rfs .rfs-output {
				background: #fff;
				padding: 5px;
			}
			
			#rfs .value-empty {
				opacity: 0.5;
			}
		</style>
		
		<script>
			// Move #rfs to the end of #wpbody-content if it exists
			document.addEventListener('DOMContentLoaded', function() {
				var rfs = document.getElementById('rfs');
				var wpbody_content = document.getElementById('wpbody-content');
				if ( rfs && wpbody_content ) {
					wpbody_content.appendChild(rfs);
				}
			});
		</script>
		<?php
	}
	
	/**
	 * Check if current user is radley
	 * @see https://assets.boundshay.com/?amiradley
	 *
	 * @return void
	 */
	public function am_i_radley() {
		if ( ! isset($_GET['amiradley']) ) return;
		
		$html = '';
		
		if ( $this->is_radley() ) {
			$html .= '<strong>Success &ndash; You are Radley</strong>';
		}else{
			$html .= 'You are NOT radley';
		}
		
		$html .= '<br><br>';
		$html .= 'Your IP: ' . BHE_Utility::get_visitor_ip();
		
		wp_die( $html, 'Checking if you are Radley', array( 'response' => 200 ));
		exit;
	}
	
}

// Initialize the object
BHE_Debug::get_instance();