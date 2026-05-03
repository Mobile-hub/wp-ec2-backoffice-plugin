<?php
/**
 * Plugin Name: WP EC2 Backoffice Plugin
 * Plugin URI: https://github.com/your-username/wp-ec2-backoffice-plugin
 * Description: Gestiona una instancia EC2 de Windows desde el área de administración de WordPress. Permite iniciar/detener la instancia, gestionar reglas de seguridad automáticamente y descargar archivos de conexión RDP.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-ec2-backoffice-plugin
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_EC2_BACKOFFICE_VERSION', '1.0.0');
define('WP_EC2_BACKOFFICE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_EC2_BACKOFFICE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_EC2_BACKOFFICE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Require Composer autoloader
if (file_exists(WP_EC2_BACKOFFICE_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once WP_EC2_BACKOFFICE_PLUGIN_DIR . 'vendor/autoload.php';
}

// Require main plugin class
require_once WP_EC2_BACKOFFICE_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Initialize the plugin
 */
function wp_ec2_backoffice_init() {
    // Check if dependencies are loaded
    if (!class_exists('Aws\Sdk')) {
        add_action('admin_notices', 'wp_ec2_backoffice_missing_dependencies_notice');
        return;
    }

    // Initialize the plugin
    $plugin = new WP_EC2_Backoffice\Plugin();
    $plugin->init();
}
add_action('plugins_loaded', 'wp_ec2_backoffice_init');

/**
 * Display admin notice when dependencies are missing
 */
function wp_ec2_backoffice_missing_dependencies_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php 
            echo esc_html__('WP EC2 Backoffice Plugin: Las dependencias no están instaladas. Por favor ejecuta "composer install" en el directorio del plugin.', 'wp-ec2-backoffice-plugin'); 
            ?>
        </p>
    </div>
    <?php
}

/**
 * Activation hook
 */
function wp_ec2_backoffice_activate() {
    // Check PHP version
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(WP_EC2_BACKOFFICE_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Este plugin requiere PHP 7.4 o superior.', 'wp-ec2-backoffice-plugin'),
            esc_html__('Error de Activación del Plugin', 'wp-ec2-backoffice-plugin'),
            array('back_link' => true)
        );
    }

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(WP_EC2_BACKOFFICE_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Este plugin requiere WordPress 5.0 o superior.', 'wp-ec2-backoffice-plugin'),
            esc_html__('Error de Activación del Plugin', 'wp-ec2-backoffice-plugin'),
            array('back_link' => true)
        );
    }

    // Check if OpenSSL extension is loaded
    if (!extension_loaded('openssl')) {
        deactivate_plugins(WP_EC2_BACKOFFICE_PLUGIN_BASENAME);
        wp_die(
            esc_html__('Este plugin requiere la extensión OpenSSL de PHP.', 'wp-ec2-backoffice-plugin'),
            esc_html__('Error de Activación del Plugin', 'wp-ec2-backoffice-plugin'),
            array('back_link' => true)
        );
    }
}
register_activation_hook(__FILE__, 'wp_ec2_backoffice_activate');

/**
 * Deactivation hook
 */
function wp_ec2_backoffice_deactivate() {
    // Clean up if needed
}
register_deactivation_hook(__FILE__, 'wp_ec2_backoffice_deactivate');
