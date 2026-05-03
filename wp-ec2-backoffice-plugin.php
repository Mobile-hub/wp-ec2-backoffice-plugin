<?php
/**
 * Plugin Name: WP EC2 Backoffice Plugin
 * Description: Manage a Windows EC2 instance from the WordPress admin area. Start and stop the instance, manage RDP access rules, and download RDP connection files.
 * Version: 1.0.0
 * Author: WP EC2 Backoffice Plugin Contributors
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-ec2-backoffice-plugin
 * Domain Path: /src/languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_EC2_BACKOFFICE_VERSION', '1.0.0');
define('WP_EC2_BACKOFFICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_EC2_BACKOFFICE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_EC2_BACKOFFICE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WP_EC2_BACKOFFICE_BOOTSTRAP_FILE', __FILE__);
define('WP_EC2_BACKOFFICE_SRC_DIR', WP_EC2_BACKOFFICE_PLUGIN_DIR . 'src/');
define('WP_EC2_BACKOFFICE_SRC_URL', WP_EC2_BACKOFFICE_PLUGIN_URL . 'src/');

require_once WP_EC2_BACKOFFICE_SRC_DIR . 'wp-ec2-backoffice-plugin.php';