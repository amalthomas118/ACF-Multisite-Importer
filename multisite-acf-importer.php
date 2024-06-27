<?php
/**
 * Plugin Name: Multisite & Single Site ACF Importer
 * Plugin URI:  
 * Description: Imports ACF field groups across a multisite network or a single site.
 * Version:     1.0.2
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
define('IS_MULTI_SITE', is_multisite());

include MSAI_PLUGIN_DIR . 'core/class-importer.php';

// Instantiate the plugin class
new MsaImporter();