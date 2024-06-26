<?php
/**
 * Plugin Name: Multisite & Single Site ACF Importer
 * Plugin URI:  
 * Description: Imports ACF field groups across a multisite network or a single site.
 * Version:     1.0.0
 * Author:      Amal Thomas
 * Author URI:  
 * License:     GPLv2 or later
 * Text Domain: multisite-acf-importer
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'MSAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MSAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Define the main plugin class
class MSAI_ACF_Importer {

	public function __construct() {
		// Load plugin textdomain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Check if multisite or single site
		global $is_multisite;
		$is_multisite = is_multisite();

		// Add network settings menu if multisite
		if ( $is_multisite ) {
			add_action( 'network_admin_menu', array( $this, 'add_network_settings' ) );
		} else { // Add settings page for single site
			add_action( 'admin_menu', array( $this, 'add_single_site_settings' ) );
		}

		// Handle form submission
		add_action( 'network_admin_notices', array( $this, 'import_fields' ) );
		add_action( 'admin_notices', array( $this, 'import_fields' ) );

		// Enqueue scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'multisite-acf-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	public function add_network_settings() {
		add_menu_page(
			__( 'ACF Importer', 'multisite-acf-importer' ),
			__( 'ACF Importer', 'multisite-acf-importer' ),
			'manage_network',
			'msai-importer',
			array( $this, 'network_settings_page' ),
			'dashicons-upload',
			60
		);
	}

	public function add_single_site_settings() {
		add_options_page(
			__( 'ACF Importer', 'multisite-acf-importer' ),
			__( 'ACF Importer', 'multisite-acf-importer' ),
			'manage_options',
			'msai-importer',
			array( $this, 'single_site_settings_page' )
		);
	}

	// Network settings page output
	public function network_settings_page() {
		?>
		<div class="wrap" id="msai-importer">
			<div class="msai-settings-container">
				<h2><span class="dashicons dashicons-upload"></span> ACF Multisite Importer</h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'msai_import_fields', 'msai_nonce' ); ?>

					<div class="msai-form-group">
						<label for="select-all"><?php _e( 'Select Sites', 'multisite-acf-importer' ); ?></label>
						<div class="msai-checkbox-group">
							<?php
							$sites = get_sites( array('deleted' => 0, 'archived' => 0, 'spam' => 0, 'public' => 1));
							foreach ( $sites as $site ) :
								?>
								<label for="site-<?php echo $site->blog_id; ?>">
									<input type="checkbox" id="site-<?php echo $site->blog_id; ?>" name="sites[]" value="<?php echo $site->blog_id; ?>">
									<?php echo $site->blogname; ?>
								</label><br>
								<?php
							endforeach;
							?>
							<label for="select-all">
								<input type="checkbox" id="select-all">
								<?php _e( 'Select All', 'multisite-acf-importer' ); ?>
							</label>
						</div>
					</div>

					<div class="msai-form-group">
						<label for="acf_json_file"><?php _e( 'Upload JSON File', 'multisite-acf-importer' ); ?></label>
						<input type="file" name="acf_json_file" id="acf_json_file" accept=".json">
					</div>

					<p class="submit">
						<input type="submit" name="msai_import_fields" class="button button-primary" value="<?php _e( 'Import JSON', 'multisite-acf-importer' ); ?>">
					</p>

					<div id="msai-errors" class="notice notice-error is-dismissible" style="display: none;">
						<p></p>
					</div>
					<div id="msai-success" class="notice notice-success is-dismissible" style="display: none;">
						<p></p>
					</div>

				</form>
			</div>
		</div>
		<?php
	}

	// Single site settings page output
	public function single_site_settings_page() {
		?>
		<div class="wrap" id="msai-importer">
			<div class="msai-settings-container">
				<h2><span class="dashicons dashicons-upload"></span> ACF Importer</h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'msai_import_fields', 'msai_nonce' ); ?>

					<div class="msai-form-group">
						<label for="acf_json_file"><?php _e( 'Upload JSON File', 'multisite-acf-importer' ); ?></label>
						<input type="file" name="acf_json_file" id="acf_json_file" accept=".json">
					</div>

					<p class="submit">
						<input type="submit" name="msai_import_fields" class="button button-primary" value="<?php _e( 'Import JSON', 'multisite-acf-importer' ); ?>">
					</p>

					<div id="msai-errors" class="notice notice-error is-dismissible" style="display: none;">
						<p></p>
					</div>
					<div id="msai-success" class="notice notice-success is-dismissible" style="display: none;">
						<p></p>
					</div>

				</form>
			</div>
		</div>
		<?php
	}
	public function import_fields() {
		global $is_multisite;

		if ( isset( $_POST['msai_import_fields'] ) && wp_verify_nonce( $_POST['msai_nonce'], 'msai_import_fields' ) ) {
			$sites = isset( $_POST['sites'] ) ? $_POST['sites'] : array();

			// If multisite, get selected sites
			if ( $is_multisite ) {
				if ( empty( $sites ) ) {
					$this->display_error( __( 'Please select at least one site.', 'multisite-acf-importer' ) );
					return;
				}
			} else { // If single site, use current site
				$sites[] = get_current_blog_id();
			}

			$file = $_FILES['acf_json_file'];

			// Validate file upload
			if ( empty( $file['name'] ) || ! in_array( $file['type'], array( 'application/json', 'text/json' ) ) ) {
				$this->display_error( __( 'Please upload a valid JSON file.', 'multisite-acf-importer' ) );
				return;
			}

			// Move the uploaded file to a temporary location (outside the loop)
			$upload_dir = wp_upload_dir();
			$target_path = $upload_dir['path'] . '/' . basename( $file['name'] );
			if (!move_uploaded_file( $file['tmp_name'], $target_path )) {
				$this->display_error( __( 'Error moving uploaded file.', 'multisite-acf-importer' ) );
				return; // Stop processing if the file move fails
			}

			// Process import on selected sites
			$import_success = true; // Flag to track overall import success
			foreach ( $sites as $site_id ) {
				if ( $is_multisite ) {
					switch_to_blog( $site_id ); // Switch to the target site only if multisite
				}

				// Import ACF field groups (using the temporary file)
				$json = file_get_contents( $target_path ); 
				$result = $this->import_acf_fields( $json );

				// Check for errors during import
				if ( $result !== true ) {
					$import_success = false; // Set flag to false if an error occurred
					$this->display_error( __( 'Error importing ACF fields:', 'multisite-acf-importer' ) . ' ' . $result );
				}

				if ( $is_multisite ) {
					restore_current_blog(); // Restore the original site only if multisite
				}
			}

			// Display success or error message after all sites are processed
			if ( $import_success ) {
				$this->display_success( __( 'ACF fields imported successfully.', 'multisite-acf-importer' ) );

				//Deletes the temp file stored in the uploads folder
				if (file_exists($target_path)) {
					unlink($target_path);
				}
			}
		}
	}

	public function import_acf_fields( $json ) {
		// Decode JSON data
		$data = json_decode( $json, true );

		// Validate JSON data
		if ( ! is_array( $data ) || empty( $data ) ) {
			return __( 'Invalid JSON data.', 'multisite-acf-importer' );
		}

		// Loop through field groups and import
		foreach ( $data as $field_group ) {
			// Get existing field group by key
			$existing_field_group = acf_get_field_group( $field_group['key'] );

			// If field group exists, delete it before importing
			if ( $existing_field_group ) {
				acf_delete_field_group( $existing_field_group['ID'] );
			}

			// Import field group (overwrites if it exists)
			$imported_id = acf_import_field_group( $field_group );

			// Check for errors
			if ( is_wp_error( $imported_id ) ) {
				return $imported_id->get_error_message();
			}
		}

		return true;
	}

    // Display error message (updated)
    public function display_error( $message ) {
        ?>
        <div id="msai-errors" class="notice notice-error" style="display: none;">
            <p></p>
        </div>
        <script>
            jQuery('#msai-errors p').html('<?php echo esc_html( $message ); ?>'); 
            jQuery('#msai-errors').addClass('is-dismissible').show(); // Add is-dismissible class
            jQuery('#msai-errors .notice-dismiss').click(function() {
                jQuery(this).parent().hide();
            });
        </script>
        <?php
    }

    // Display success message (updated)
    public function display_success( $message ) {
        ?>
        <div id="msai-success" class="notice notice-success" style="display: none;">
            <p></p>
        </div>
        <script>
            jQuery('#msai-success p').html('<?php echo esc_html( $message ); ?>'); 
            jQuery('#msai-success').addClass('is-dismissible').show(); // Add is-dismissible class
            jQuery('#msai-success .notice-dismiss').click(function() {
                jQuery(this).parent().hide();
            });
        </script>
        <?php
    }

	public function enqueue_scripts() {
		$css_filetime = filemtime( MSAI_PLUGIN_DIR . 'css/msai-style.css' );
		$js_filetime = filemtime( MSAI_PLUGIN_DIR . 'js/msai-scripts.js' );

		wp_enqueue_style( 'msai-styles', MSAI_PLUGIN_URL . 'css/msai-style.css', array(), $css_filetime );
		wp_enqueue_script( 'msai-scripts', MSAI_PLUGIN_URL . 'js/msai-scripts.js', array( 'jquery' ), $js_filetime, true );
	}
}

// Instantiate the plugin class
new MSAI_ACF_Importer();