<?php
/**
 * UI Controller Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles UI rendering and AJAX requests
 */
class UI_Controller {
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
     * Constructor
     *
     * @param Configuration_Store $config_store Configuration store instance
     * @param EC2_Manager $ec2_manager EC2 manager instance
     * @param Security_Group_Manager $sg_manager Security group manager instance
     * @param RDP_Generator $rdp_generator RDP generator instance
     */
    public function __construct(
        Configuration_Store $config_store,
        EC2_Manager $ec2_manager,
        Security_Group_Manager $sg_manager,
        RDP_Generator $rdp_generator
    ) {
        $this->config_store = $config_store;
        $this->ec2_manager = $ec2_manager;
        $this->sg_manager = $sg_manager;
        $this->rdp_generator = $rdp_generator;
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        // TODO: Implement in task 10
        echo '<div class="wrap"><h1>' . esc_html__('EC2 Backoffice', 'wp-ec2-backoffice-plugin') . '</h1></div>';
    }

    /**
     * Render access tab
     */
    public function render_access_tab() {
        // TODO: Implement in task 10
    }

    /**
     * Render config tab
     */
    public function render_config_tab() {
        // TODO: Implement in task 10
    }

    /**
     * Handle AJAX request to start instance
     */
    public function handle_ajax_start_instance() {
        // TODO: Implement in task 11
        wp_send_json_error('Not implemented');
    }

    /**
     * Handle AJAX request to stop instance
     */
    public function handle_ajax_stop_instance() {
        // TODO: Implement in task 11
        wp_send_json_error('Not implemented');
    }

    /**
     * Handle AJAX request to get status
     */
    public function handle_ajax_get_status() {
        // TODO: Implement in task 11
        wp_send_json_error('Not implemented');
    }

    /**
     * Handle AJAX request to test connection
     */
    public function handle_ajax_test_connection() {
        // TODO: Implement in task 11
        wp_send_json_error('Not implemented');
    }

    /**
     * Handle AJAX request to download RDP file
     */
    public function handle_ajax_download_rdp() {
        // TODO: Implement in task 11
        wp_send_json_error('Not implemented');
    }

    /**
     * Verify nonce and user capability
     *
     * @return bool True if valid, false otherwise
     */
    private function verify_nonce_and_capability() {
        // TODO: Implement in task 11
        return false;
    }
}
