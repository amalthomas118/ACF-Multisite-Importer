<?php

/*
 Plugin Name: Multisite ACF Importer
 Description: A plugin to import ACF fields across multisite.
 Version: 1.0.0
 Requires at least: 5.0
 Tested up to: 6.2
 Requires PHP: 7.2
 Author: Amal Thomas
 License: GPL-2.0+
 Text Domain: multisite-acf-importer
 Domain Path: /languages
 
------------------------------------------------------------------------
Copyright 2009-2024 Phases, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see http://www.gnu.org/licenses.
*/


// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Check if the class already exists to prevent redeclaration errors
if (!class_exists('Multisite_ACF_Importer')) {
    class Multisite_ACF_Importer
    {
        private static $instance = null;

        // Private constructor to implement singleton pattern
        private function __construct()
        {

            if (!is_multisite()) {
                add_action('admin_notices', [$this, 'multisite_required_notice']);
                add_action('admin_init', [$this, 'deactivate_plugin']);
                return;
            }

            if (!class_exists('ACF')) {
                add_action('admin_notices', [$this, 'acf_plugin_required_notice']);
                add_action('admin_init', [$this, 'deactivate_plugin']);
                return;
            }

            $this->define_constants();
            $this->set_locale();
            $this->load_dependencies();
        }

        // Singleton instance getter
        public static function get_instance()
        {
            if (null == self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        // Define necessary plugin constants
        private function define_constants()
        {
            define('MSAI_PLUGIN_DIR', plugin_dir_path(__FILE__));
            define('MSAI_PLUGIN_URL', plugin_dir_url(__FILE__));

            // Get plugin data to dynamically set the version constant
            $plugin_data = get_plugin_data(__FILE__);
            $plugin_version = $plugin_data['Version'];

            define('MSAI_PLUGIN_VERSION', $plugin_version);
        }

        // Load the plugin's text domain for translation
        private function set_locale()
        {
            load_plugin_textdomain('multisite-acf-importer', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        // Include the required dependency files
        private function load_dependencies()
        {
            require_once MSAI_PLUGIN_DIR . 'core/class-settings.php';
            require_once MSAI_PLUGIN_DIR . 'core/class-importer.php';
        }

        // Admin notice for single site installation
        public function multisite_required_notice()
        {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('The Multisite ACF Importer plugin requires a WordPress Multisite installation.', 'multisite-acf-importer'); ?>
                </p>
            </div>
            <?php
        }

        // Check if the ACF plugin is activated
        public function acf_plugin_required_notice()
        {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('ACF plugin is not activated. The Multisite ACF Importer requires ACF to function correctly.', 'multisite-acf-importer'); ?>
                </p>
            </div>
            <?php
        }

        // Deactivate the plugin if not in multisite or not activated the acf plugin.
        public function deactivate_plugin()
        {
            deactivate_plugins(plugin_basename(__FILE__));
        }
    }

    // Instantiate the plugin class
    Multisite_ACF_Importer::get_instance();
}
?>