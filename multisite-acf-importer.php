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
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('MSAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MSAI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Define the main plugin class
class MsaImporter
{

	private $isMultisite;

	public function __construct()
	{
		// Check if multisite or single site
		$this->isMultisite = is_multisite();

		// Initialize settings
		new MsaImporterSettings($this, $this->isMultisite);

		// Register actions
		$this->register_actions();
	}

	private function register_actions()
	{
		add_action('plugins_loaded', array($this, 'load_textdomain'));
		add_action('network_admin_notices', array($this, 'import_fields'));
		add_action('admin_notices', array($this, 'import_fields'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
	}

	public function load_textdomain()
	{
		load_plugin_textdomain('multisite-acf-importer', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	//Get the sites, Checks if it is a multisite or a single site
	private function get_sites_to_import()
	{
		$sites = isset($_POST['sites']) ? $_POST['sites'] : array();

		if ($this->isMultisite) {
			if (empty($sites)) {
				$this->display_error(__('Please select at least one site.', 'multisite-acf-importer'));
				return array();
			}
		} else {
			$sites[] = get_current_blog_id();
		}

		return $sites;
	}

	// Import the fields to a temparory file before the start of the process 
	public function import_fields()
	{
		if (isset($_POST['msai_import_fields']) && wp_verify_nonce($_POST['msai_nonce'], 'msai_import_fields')) {
			$sites = $this->get_sites_to_import();
			$file = $_FILES['acf_json_file'];

			if ($this->validate_file_upload($file)) {
				// Move uploaded file to temporary location
				$upload_dir = wp_upload_dir();
				$target_path = $upload_dir['path'] . '/' . basename($file['name']);
				if (!move_uploaded_file($file['tmp_name'], $target_path)) {
					$this->display_error(__('Error moving uploaded file.', 'multisite-acf-importer'));
					return;
				}

				// Process import on selected sites
				$import_success = $this->process_import($sites, $target_path);

				// Delete temporary file
				if (file_exists($target_path)) {
					unlink($target_path);
				}

				// Display success or error message
				if ($import_success) {
					$this->display_success(__('ACF fields imported successfully.', 'multisite-acf-importer'));
				}
			}
		}
	}

	//Function to validate if its a json file
	private function validate_file_upload($file)
	{
		if (empty($file['name']) || !in_array($file['type'], array('application/json', 'text/json'))) {
			$this->display_error(__('Please upload a valid JSON file.', 'multisite-acf-importer'));
			return false;
		}
		return true;
	}

	//Initialize the import process
	private function process_import($sites, $target_path)
	{
		$import_success = true;
		foreach ($sites as $site_id) {
			if ($this->isMultisite) {
				switch_to_blog($site_id);
			}

			$json = file_get_contents($target_path);
			$result = $this->import_acf_fields($json);

			if ($result !== true) {
				$import_success = false;
				$this->display_error(__('Error importing ACF fields:', 'multisite-acf-importer') . ' ' . $result);
			}

			if ($this->isMultisite) {
				restore_current_blog();
			}
		}
		return $import_success;
	}

	//function of the import process
	public function import_acf_fields($json)
	{
		// Decode JSON data
		$data = json_decode($json, true);

		// Validate JSON data
		if (!is_array($data) || empty($data)) {
			return __('Invalid JSON data.', 'multisite-acf-importer');
		}

		// Loop through field groups and import
		foreach ($data as $field_group) {
			// Get existing field group by key
			$existing_field_group = acf_get_field_group($field_group['key']);

			// If field group exists, delete it before importing
			if ($existing_field_group) {
				acf_delete_field_group($existing_field_group['ID']);
			}

			// Import field group (overwrites if it exists)
			$imported_id = acf_import_field_group($field_group);

			// Check for errors
			if (is_wp_error($imported_id)) {
				return $imported_id->get_error_message();
			}
		}

		return true;
	}

	// Display error message
	public function display_error($message)
	{
		?>
		<div id="msai-errors" class="notice notice-error" style="display: none;">
			<p></p>
		</div>
		<script>
			jQuery('#msai-errors p').html('<?php echo esc_html($message); ?>');
			jQuery('#msai-errors').addClass('is-dismissible').show(); // Add is-dismissible class
			jQuery('#msai-errors .notice-dismiss').click(function () {
				jQuery(this).parent().hide();
			});
		</script>
		<?php
	}

	// Display success message
	public function display_success($message)
	{
		?>
		<div id="msai-success" class="notice notice-success" style="display: none;">
			<p></p>
		</div>
		<script>
			jQuery('#msai-success p').html('<?php echo esc_html($message); ?>');
			jQuery('#msai-success').addClass('is-dismissible').show(); // Add is-dismissible class
			jQuery('#msai-success .notice-dismiss').click(function () {
				jQuery(this).parent().hide();
			});
		</script>
		<?php
	}

	//enqueue the scripts
	public function enqueue_scripts()
	{
		$css_filetime = filemtime(MSAI_PLUGIN_DIR . 'css/msai-style.css');
		$js_filetime = filemtime(MSAI_PLUGIN_DIR . 'js/msai-scripts.js');

		wp_enqueue_style('msai-styles', MSAI_PLUGIN_URL . 'css/msai-style.css', array(), $css_filetime);
		wp_enqueue_script('msai-scripts', MSAI_PLUGIN_URL . 'js/msai-scripts.js', array('jquery'), $js_filetime, true);
	}
}

// A separate class for handling all the functions happening in the front end site (dashboard settings)
class MsaImporterSettings
{
	private $plugin;
	private $isMultisite;

	public function __construct(MsaImporter $plugin, $isMultisite)
	{
		$this->plugin = $plugin;
		$this->isMultisite = $isMultisite;

		// Register actions
		$this->register_actions();
	}

	private function register_actions()
	{
        if ( $this->isMultisite ) { // Use the stored $isMultisite value
            add_action( 'network_admin_menu', array( $this, 'add_network_settings' ) );
        } else {
            add_action( 'admin_menu', array( $this, 'add_single_site_settings' ) );
        }
	}

	public function add_network_settings()
	{
		add_menu_page(
			__('ACF Importer', 'multisite-acf-importer'),
			__('ACF Importer', 'multisite-acf-importer'),
			'manage_network',
			'msai-importer',
			array($this, 'network_settings_page'),
			'dashicons-upload',
			60
		);
	}

	public function add_single_site_settings()
	{
		add_options_page(
			__('ACF Importer', 'multisite-acf-importer'),
			__('ACF Importer', 'multisite-acf-importer'),
			'manage_options',
			'msai-importer',
			array($this, 'single_site_settings_page')
		);
	}

	// Network settings page output
	public function network_settings_page()
	{
		?>
		<div class="wrap" id="msai-importer">
			<div class="msai-settings-container">
				<h2><span class="dashicons dashicons-upload"></span> ACF Multisite Importer</h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field('msai_import_fields', 'msai_nonce'); ?>

					<div class="msai-form-group">
						<label for="select-all"><?php _e('Select Sites', 'multisite-acf-importer'); ?></label>
						<div class="msai-checkbox-group">
							<?php
							$sites = get_sites(array('deleted' => 0, 'archived' => 0, 'spam' => 0, 'public' => 1));
							foreach ($sites as $site):
								?>
								<label for="site-<?php echo $site->blog_id; ?>">
									<input type="checkbox" id="site-<?php echo $site->blog_id; ?>" name="sites[]"
										value="<?php echo $site->blog_id; ?>">
									<?php echo $site->blogname; ?>
								</label><br>
								<?php
							endforeach;
							?>
							<label for="select-all">
								<input type="checkbox" id="select-all">
								<?php _e('Select All', 'multisite-acf-importer'); ?>
							</label>
						</div>
					</div>

					<div class="msai-form-group">
						<label for="acf_json_file"><?php _e('Upload JSON File', 'multisite-acf-importer'); ?></label>
						<input type="file" name="acf_json_file" id="acf_json_file" accept=".json">
					</div>

					<p class="submit">
						<input type="submit" name="msai_import_fields" class="button button-primary"
							value="<?php _e('Import JSON', 'multisite-acf-importer'); ?>">
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
	public function single_site_settings_page()
	{
		?>
		<div class="wrap" id="msai-importer">
			<div class="msai-settings-container">
				<h2><span class="dashicons dashicons-upload"></span> ACF Importer</h2>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field('msai_import_fields', 'msai_nonce'); ?>

					<div class="msai-form-group">
						<label for="acf_json_file"><?php _e('Upload JSON File', 'multisite-acf-importer'); ?></label>
						<input type="file" name="acf_json_file" id="acf_json_file" accept=".json">
					</div>

					<p class="submit">
						<input type="submit" name="msai_import_fields" class="button button-primary"
							value="<?php _e('Import JSON', 'multisite-acf-importer'); ?>">
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
}

// Instantiate the plugin class
new MsaImporter();