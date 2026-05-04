<?php
/**
 * Main Plugin Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class that handles initialization and WordPress integration
 */
class Plugin {
    /**
     * UI Controller instance
     *
     * @var UI_Controller
     */
    private $ui_controller;

    /**
     * Configuration Store instance
     *
     * @var Configuration_Store
     */
    private $config_store;

    /**
     * EC2 Manager instance
     *
     * @var EC2_Manager
     */
    private $ec2_manager;

    /**
     * Security Group Manager instance
     *
     * @var Security_Group_Manager
     */
    private $sg_manager;

    /**
     * RDP Generator instance
     *
     * @var RDP_Generator
     */
    private $rdp_generator;

    /**
     * IP Detector instance
     *
     * @var IP_Detector
     */
    private $ip_detector;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize components
        $this->config_store = new Configuration_Store();
        $this->ip_detector = new IP_Detector();
        $this->ec2_manager = new EC2_Manager($this->config_store);
        $this->sg_manager = new Security_Group_Manager($this->config_store, $this->ip_detector);
        $this->rdp_generator = new RDP_Generator();
        $this->ui_controller = new UI_Controller(
            $this->config_store,
            $this->ec2_manager,
            $this->sg_manager,
            $this->rdp_generator
        );
    }

    /**
     * Initialize the plugin
     *
     * Registers WordPress hooks and actions
     */
    public function init() {
        // Check dependencies
        if (!$this->check_dependencies()) {
            return;
        }

        // Register admin menu
        add_action('admin_menu', array($this, 'register_admin_menu'));

        // Register admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Register AJAX handlers
        $this->register_ajax_handlers();

        // Load text domain for translations
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Check if all required dependencies are available
     *
     * @return bool True if all dependencies are available
     */
    private function check_dependencies() {
        // Check if AWS SDK is loaded
        if (!class_exists('Aws\Sdk')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice Plugin: AWS SDK not found. Run composer install.');
            }
            return false;
        }

        // Check if OpenSSL extension is loaded
        if (!extension_loaded('openssl')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice Plugin: OpenSSL extension not loaded.');
            }
            return false;
        }

        return true;
    }

    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        add_menu_page(
            __('EC2 Backoffice', 'wp-ec2-backoffice-plugin'),
            __('EC2 Backoffice', 'wp-ec2-backoffice-plugin'),
            'manage_options',
            'wp-ec2-backoffice',
            array($this->ui_controller, 'render_admin_page'),
            'dashicons-cloud',
            30
        );
    }

    /**
     * Enqueue admin assets (CSS and JavaScript)
     *
     * @param string $hook The current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin page
        if ($hook !== 'toplevel_page_wp-ec2-backoffice') {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'wp-ec2-backoffice-admin',
            WP_EC2_BACKOFFICE_SRC_URL . 'admin/css/admin-styles.css',
            array(),
            WP_EC2_BACKOFFICE_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'wp-ec2-backoffice-admin',
            WP_EC2_BACKOFFICE_SRC_URL . 'admin/js/admin-scripts.js',
            array('jquery'),
            WP_EC2_BACKOFFICE_VERSION,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script(
            'wp-ec2-backoffice-admin',
            'wpEc2Backoffice',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_ec2_backoffice_nonce'),
                'strings' => array(
                    'starting' => __('Starting instance...', 'wp-ec2-backoffice-plugin'),
                    'stopping' => __('Stopping instance...', 'wp-ec2-backoffice-plugin'),
                    'testing' => __('Testing connection...', 'wp-ec2-backoffice-plugin'),
                    'loading' => __('Loading...', 'wp-ec2-backoffice-plugin'),
                    'error' => __('Error', 'wp-ec2-backoffice-plugin'),
                    'success' => __('Success', 'wp-ec2-backoffice-plugin'),
                    'copied' => __('Copied to clipboard', 'wp-ec2-backoffice-plugin'),
                )
            )
        );
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        add_action('wp_ajax_ec2_start_instance', array($this->ui_controller, 'handle_ajax_start_instance'));
        add_action('wp_ajax_ec2_stop_instance', array($this->ui_controller, 'handle_ajax_stop_instance'));
        add_action('wp_ajax_ec2_get_status', array($this->ui_controller, 'handle_ajax_get_status'));
        add_action('wp_ajax_ec2_test_connection', array($this->ui_controller, 'handle_ajax_test_connection'));
        add_action('wp_ajax_ec2_get_windows_password', array($this->ui_controller, 'handle_ajax_get_windows_password'));
        add_action('wp_ajax_ec2_download_rdp', array($this->ui_controller, 'handle_ajax_download_rdp'));
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-ec2-backoffice-plugin',
            false,
            dirname(WP_EC2_BACKOFFICE_PLUGIN_BASENAME) . '/src/languages'
        );
    }
}
