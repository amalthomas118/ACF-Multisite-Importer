<?php
//import the plugin settings class
include MSAI_PLUGIN_DIR .'core/class-settings.php';

// Define the main plugin class
class MsaImporter extends MsaImporterSettings
{
	public function __construct()
	{
		// Register actions
		$this->register_actions();
	}

	private function register_actions()
	{
		add_action('plugins_loaded', array($this, 'load_textdomain'));
		add_action('network_admin_notices', array($this, 'import_fields'));
		add_action('admin_notices', array($this, 'import_fields'));
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
		
		if ( IS_MULTI_SITE ) {
            add_action( 'network_admin_menu', array( $this, 'add_network_settings' ) );
        } else {
            add_action( 'admin_menu', array( $this, 'add_single_site_settings' ) );
        }
	}

	public function load_textdomain()
	{
		load_plugin_textdomain('multisite-acf-importer', false, dirname(plugin_basename(__FILE__)) . '/languages');
	}

	//Get the sites, Checks if it is a multisite or a single site
	private function get_sites_to_import()
	{
		$sites = isset($_POST['sites']) ? $_POST['sites'] : array();

		if (IS_MULTI_SITE) {
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
			if (IS_MULTI_SITE) {
				switch_to_blog($site_id);
			}

			$json = file_get_contents($target_path);
			$result = $this->import_acf_fields($json);

			if ($result !== true) {
				$import_success = false;
				$this->display_error(__('Error importing ACF fields:', 'multisite-acf-importer') . ' ' . $result);
			}

			if (IS_MULTI_SITE) {
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