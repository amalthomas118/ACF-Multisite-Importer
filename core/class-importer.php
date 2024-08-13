<?php

namespace Multisite_ACF_Importer\Core;

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Importer
{
    public function __construct()
    {
        // Hook to handle the form submission for importing ACF fields
        add_action('admin_init', [$this, 'handle_import']);
        // Hook to display admin notices for import results
        add_action('network_admin_notices', [$this, 'display_notices']);
    }

    // Handle the ACF fields import process
    public function handle_import()
    {
        if (isset($_POST['msai_import_fields'])) {

            // Verify that the user is a network administrator
            if (!current_user_can('manage_network_options')) {
                wp_die(esc_html_e('You do not have permission to access this page. Only network administrator can access this.', 'multisite-acf-importer'));
            }

            // Verify nonce for security
            if (!isset($_POST['msai_nonce']) || !wp_verify_nonce($_POST['msai_nonce'], 'msai_import_fields')) {
                wp_die(esc_html_e('Nonce verification failed', 'multisite-acf-importer'));
            }


            $sites = $this->get_sites_to_import();
            $file = $_FILES['acf_json_file'];

            // Validate the uploaded JSON file
            if ($this->validate_file_upload($file)) {
                $upload_dir = wp_upload_dir();
                $target_path = $upload_dir['path'] . '/' . sanitize_file_name(basename($file['name']));
                if (!move_uploaded_file($file['tmp_name'], $target_path)) {
                    $this->add_admin_notice('error', esc_html_e('Error moving uploaded file.', 'multisite-acf-importer'));
                    return;
                }

                // Process the import for the selected sites
                $import_success = $this->process_import($sites, $target_path);

                // Clean up the uploaded file
                if (file_exists($target_path)) {
                    wp_delete_file($target_path);
                }

                // Add success notice if import was successful
                if ($import_success && !empty($sites)) {
                    $this->add_admin_notice('success', esc_html_e('ACF fields imported successfully.', 'multisite-acf-importer'));
                }
            }
        }
    }

    // Get the list of sites selected for import
    private function get_sites_to_import()
    {
        $sites = isset($_POST['sites']) ? array_map('sanitize_text_field', $_POST['sites']) : array();

        // Check if at least one site is selected
        if (empty($sites)) {
            $this->add_admin_notice('error', esc_html_e('Please select at least one site.', 'multisite-acf-importer'));
            return array();
        }

        return $sites;
    }

    // Validate the uploaded JSON file
    private function validate_file_upload($file)
    {
        if (empty($file['name']) || !in_array($file['type'], array('application/json', 'text/json'))) {
            $this->add_admin_notice('error', esc_html_e('Please upload a valid JSON file.', 'multisite-acf-importer'));
            return false;
        }
        return true;
    }

    // Process the ACF fields import for the selected sites
    private function process_import($sites, $target_path)
    {
        $import_success = true;
        foreach ($sites as $site_id) {
            switch_to_blog(intval($site_id));

            $json = wp_remote_get($target_path);
            $result = $this->import_acf_fields($json);

            // Check if there was an error during import
            if ($result !== true) {
                $import_success = false;
                $this->add_admin_notice('error', esc_html_e('Error importing ACF fields:', 'multisite-acf-importer') . ' ' . esc_html($result));
            }

            restore_current_blog();
        }
        return $import_success;
    }

    // Import the ACF fields from the JSON data
    private function import_acf_fields($json)
    {
        $data = json_decode($json, true);

        if (!is_array($data) || empty($data)) {
            return __('Invalid JSON data.', 'multisite-acf-importer');
        }

        foreach ($data as $field_group) {
            if (class_exists('ACF')) {
                // Check if the field group already exists and delete it if it does
                $existing_field_group = acf_get_field_group(sanitize_text_field($field_group['key']));
                if ($existing_field_group) {
                    acf_delete_field_group(intval($existing_field_group['ID']));
                }

                // Import the new field group
                $imported_id = acf_import_field_group($field_group);
                if (is_wp_error($imported_id)) {
                    return $imported_id->get_error_message();
                }
            }
            else
            {
                return false;
            }
        }
        return true;
    }

    // Add an admin notice to display messages to the user
    private function add_admin_notice($type, $message)
    {
        $notices = get_option('msai_admin_notices', array());
        $notices[] = array('type' => $type, 'message' => $message);
        update_option('msai_admin_notices', $notices);
    }

    // Display admin notices for import results
    public function display_notices()
    {
        $notices = get_option('msai_admin_notices', array());
        foreach ($notices as $notice) {
            printf(
                '<div id="msai-errors" class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr($notice['type']),
                esc_html($notice['message'])
            );
        }
        delete_option('msai_admin_notices');
    }
}

// Instantiate the Importer class
new Importer();
