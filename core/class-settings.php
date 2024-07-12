<?php

namespace Multisite_ACF_Importer\Core;

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Settings
{

    public function __construct()
    {
        // Hook to enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        // Hook to add a menu item in the network admin menu
        add_action('network_admin_menu', [$this, 'add_network_admin_menu']);
    }

    // Enqueue the necessary styles and scripts for the admin page    public function enqueue_scripts()
    public function enqueue_scripts()
    {
        wp_enqueue_style('msai-styles', MSAI_PLUGIN_URL . 'css/msai-style.css', array(), MSAI_PLUGIN_VERSION);

        wp_enqueue_script('msai-scripts', MSAI_PLUGIN_URL . 'js/msai-scripts.js', array('jquery'), MSAI_PLUGIN_VERSION, true);
    }

    // Add the network admin menu item for the plugin
    public function add_network_admin_menu()
    {
        add_menu_page(
            __('Multisite ACF Importer', 'multisite-acf-importer'), // Page title
            __('ACF Importer', 'multisite-acf-importer'), // Menu title
            'manage_options', // Capability
            'msai', // Menu slug
            [$this, 'display_network_admin_page'], // Callback function to display the page content
            'dashicons-upload' // Icon URL
        );
    }

    // Display the network admin page content
    public function display_network_admin_page()
    {
?>
        <div class="wrap" id="msai-importer">
            <div class="msai-settings-container">
                <h2><span class="dashicons dashicons-upload"></span> <?php esc_html_e('ACF Multisite Importer', 'multisite-acf-importer'); ?></h2>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('msai_import_fields', 'msai_nonce'); ?>

                    <div class="msai-form-group">
                        <label for="select-all"><?php esc_html_e('Select Sites', 'multisite-acf-importer'); ?></label>
                        <div class="msai-checkbox-group">
                            <?php
                            $sites = get_sites(array('deleted' => 0, 'archived' => 0, 'spam' => 0));
                            foreach ($sites as $site) :
                            ?>
                                <label for="site-<?php echo esc_attr($site->blog_id); ?>">
                                    <input type="checkbox" id="site-<?php echo esc_attr($site->blog_id); ?>" name="sites[]" value="<?php echo esc_attr($site->blog_id); ?>">
                                    <?php echo esc_html($site->blogname); ?>
                                </label><br>
                            <?php
                            endforeach;
                            ?>
                            <label for="select-all">
                                <input type="checkbox" id="select-all">
                                <?php esc_html_e('Select All', 'multisite-acf-importer'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="msai-form-group">
                        <label for="acf_json_file"><?php esc_html_e('Upload JSON File', 'multisite-acf-importer'); ?></label>
                        <input type="file" name="acf_json_file" id="acf_json_file" accept=".json">
                    </div>

                    <p class="submit">
                        <input type="submit" name="msai_import_fields" class="button button-primary" value="<?php esc_html_e('Import JSON', 'multisite-acf-importer'); ?>">
                    </p>

                </form>
            </div>
        </div>
<?php
    }
}


// Instantiate the Settings class
new Settings();
