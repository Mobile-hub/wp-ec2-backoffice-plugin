<?php

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Require Composer autoloader
if (file_exists(WP_EC2_BACKOFFICE_SRC_DIR . 'vendor/autoload.php')) {
    require_once WP_EC2_BACKOFFICE_SRC_DIR . 'vendor/autoload.php';
}

// Require main plugin class
require_once WP_EC2_BACKOFFICE_SRC_DIR . 'includes/class-plugin.php';

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
            echo esc_html__('WP EC2 Backoffice Plugin: dependencies are not installed. Please run "composer install --working-dir=src" in the plugin directory.', 'wp-ec2-backoffice-plugin'); 
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
            esc_html__('This plugin requires PHP 7.4 or later.', 'wp-ec2-backoffice-plugin'),
            esc_html__('Plugin Activation Error', 'wp-ec2-backoffice-plugin'),
            array('back_link' => true)
        );
    }

    // Check WordPress version
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(WP_EC2_BACKOFFICE_PLUGIN_BASENAME);
        wp_die(
            esc_html__('This plugin requires WordPress 5.0 or later.', 'wp-ec2-backoffice-plugin'),
            esc_html__('Plugin Activation Error', 'wp-ec2-backoffice-plugin'),
            array('back_link' => true)
        );
    }

    // Check if OpenSSL extension is loaded
    if (!extension_loaded('openssl')) {
        deactivate_plugins(WP_EC2_BACKOFFICE_PLUGIN_BASENAME);
        wp_die(
            esc_html__('This plugin requires the OpenSSL PHP extension.', 'wp-ec2-backoffice-plugin'),
            esc_html__('Plugin Activation Error', 'wp-ec2-backoffice-plugin'),
            array('back_link' => true)
        );
    }
}
register_activation_hook(WP_EC2_BACKOFFICE_BOOTSTRAP_FILE, 'wp_ec2_backoffice_activate');

/**
 * Deactivation hook
 */
function wp_ec2_backoffice_deactivate() {
    // Clean up if needed
}
register_deactivation_hook(WP_EC2_BACKOFFICE_BOOTSTRAP_FILE, 'wp_ec2_backoffice_deactivate');
