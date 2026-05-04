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
     * Render admin page with tabs
     */
    public function render_admin_page() {
        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'wp-ec2-backoffice-plugin'));
        }

        // Get current tab
        $current_tab = 'access';
        if (isset($_GET['tab'])) {
            $current_tab = sanitize_text_field(wp_unslash($_GET['tab']));
        }

        // Handle configuration save
        if (isset($_POST['save_config']) && check_admin_referer('ec2_save_config', 'ec2_config_nonce')) {
            $this->handle_save_configuration();
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('EC2 Backoffice', 'wp-ec2-backoffice-plugin'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=wp-ec2-backoffice&tab=access" class="nav-tab <?php echo $current_tab === 'access' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Access and Control', 'wp-ec2-backoffice-plugin'); ?>
                </a>
                <a href="?page=wp-ec2-backoffice&tab=config" class="nav-tab <?php echo $current_tab === 'config' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Configuration', 'wp-ec2-backoffice-plugin'); ?>
                </a>
            </h2>

            <div class="tab-content">
                <?php
                if ($current_tab === 'access') {
                    $this->render_access_tab();
                } else {
                    $this->render_config_tab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render access tab with instance controls, status, RDP download, and password display
     */
    public function render_access_tab() {
        // Check if plugin is configured
        if (!$this->config_store->is_configured()) {
            ?>
            <div class="notice notice-warning">
                <p><?php echo esc_html__('Please configure the plugin in the Configuration tab before using the controls.', 'wp-ec2-backoffice-plugin'); ?></p>
            </div>
            <?php
            return;
        }

        // Get current instance status
        $status_result = $this->ec2_manager->get_instance_status();
        $instance_state = 'unknown';
        $public_ip = '';
        
        if ($status_result['success']) {
            $instance_state = $status_result['data']['state'];
            $public_ip = $status_result['data']['public_ip'] ?? '';
        }

        // Determine button states based on instance state
        $is_transitional = in_array($instance_state, array('pending', 'stopping'));
        $is_running = $instance_state === 'running';
        $is_stopped = $instance_state === 'stopped';

        // Check whether a Windows password is configured without exposing it to the DOM
        $config = $this->config_store->get_config();
        $has_windows_password = !empty($config['windows_password']);

        ?>
        <div class="ec2-access-tab">
            <h2><?php echo esc_html__('Instance Control', 'wp-ec2-backoffice-plugin'); ?></h2>
            
            <!-- Instance Status Display -->
            <div class="ec2-status-section">
                <h3><?php echo esc_html__('Instance Status', 'wp-ec2-backoffice-plugin'); ?></h3>
                <div id="ec2-status-display" class="ec2-status-box">
                    <p>
                        <strong><?php echo esc_html__('State:', 'wp-ec2-backoffice-plugin'); ?></strong>
                        <span id="ec2-instance-state" class="ec2-state-<?php echo esc_attr($instance_state); ?>">
                            <?php echo esc_html(ucfirst($instance_state)); ?>
                        </span>
                    </p>
                    <?php if ($public_ip): ?>
                    <p>
                        <strong><?php echo esc_html__('Public IP:', 'wp-ec2-backoffice-plugin'); ?></strong>
                        <span id="ec2-public-ip"><?php echo esc_html($public_ip); ?></span>
                    </p>
                    <?php endif; ?>
                    <p class="description">
                        <?php echo esc_html__('The status refreshes automatically every 60 seconds.', 'wp-ec2-backoffice-plugin'); ?>
                    </p>
                </div>
            </div>

            <!-- Instance Control Buttons -->
            <div class="ec2-controls-section">
                <h3><?php echo esc_html__('Controls', 'wp-ec2-backoffice-plugin'); ?></h3>
                <p>
                    <button type="button" id="ec2-start-btn" class="button button-primary" 
                            <?php echo ($is_running || $is_transitional) ? 'disabled' : ''; ?>>
                        <?php echo esc_html__('Start Instance', 'wp-ec2-backoffice-plugin'); ?>
                    </button>
                    <button type="button" id="ec2-stop-btn" class="button" 
                            <?php echo ($is_stopped || $is_transitional) ? 'disabled' : ''; ?>>
                        <?php echo esc_html__('Stop Instance', 'wp-ec2-backoffice-plugin'); ?>
                    </button>
                    <span id="ec2-loading" class="spinner" style="display:none;"></span>
                </p>
            </div>

            <!-- RDP Download -->
            <div class="ec2-rdp-section">
                <h3><?php echo esc_html__('RDP Connection', 'wp-ec2-backoffice-plugin'); ?></h3>
                <p>
                    <button type="button" id="ec2-download-rdp-btn" class="button button-secondary" 
                            <?php echo !$is_running ? 'disabled' : ''; ?>>
                        <?php echo esc_html__('Download RDP File', 'wp-ec2-backoffice-plugin'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php echo esc_html__('The RDP file is only available while the instance is running.', 'wp-ec2-backoffice-plugin'); ?>
                </p>
            </div>

            <!-- Windows Password Actions -->
            <?php if ($has_windows_password): ?>
            <div class="ec2-password-section">
                <h3><?php echo esc_html__('Windows Password', 'wp-ec2-backoffice-plugin'); ?></h3>
                <p>
                    <strong><?php echo esc_html__('Username:', 'wp-ec2-backoffice-plugin'); ?></strong> Administrator
                </p>
                <p>
                    <strong><?php echo esc_html__('Password:', 'wp-ec2-backoffice-plugin'); ?></strong>
                    <span id="ec2-password-display" class="ec2-password-masked">••••••••••••</span>
                    <button type="button" id="ec2-toggle-password-btn" class="button button-small">
                        <?php echo esc_html__('Reveal', 'wp-ec2-backoffice-plugin'); ?>
                    </button>
                    <button type="button" id="ec2-copy-password-btn" class="button button-small">
                        <?php echo esc_html__('Copy', 'wp-ec2-backoffice-plugin'); ?>
                    </button>
                    <span id="ec2-copy-feedback" style="display:none; color:green; margin-left:10px;">
                        <?php echo esc_html__('Copied!', 'wp-ec2-backoffice-plugin'); ?>
                    </span>
                </p>
            </div>
            <?php endif; ?>

            <!-- AJAX Nonce -->
            <input type="hidden" id="ec2-ajax-nonce" value="<?php echo esc_attr(wp_create_nonce('ec2_ajax_nonce')); ?>">
        </div>
        <?php
    }

    /**
     * Render config tab with configuration form
     */
    public function render_config_tab() {
        // Get current configuration
        $config = $this->config_store->get_config();
        
        // Mask the secret access key for display
        $masked_secret = '';
        if (!empty($config['aws_secret_access_key'])) {
            $masked_secret = str_repeat('•', 20);
        }

        ?>
        <div class="ec2-config-tab">
            <h2><?php echo esc_html__('AWS Configuration', 'wp-ec2-backoffice-plugin'); ?></h2>
            
            <form method="post" action="">
                <?php wp_nonce_field('ec2_save_config', 'ec2_config_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="aws_region"><?php echo esc_html__('AWS Region', 'wp-ec2-backoffice-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="aws_region" name="aws_region" 
                                   value="<?php echo esc_attr($config['aws_region'] ?? ''); ?>" 
                                   class="regular-text" required>
                            <p class="description">
                                <?php echo esc_html__('Example: us-east-1, eu-west-2', 'wp-ec2-backoffice-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aws_access_key_id"><?php echo esc_html__('Access Key ID', 'wp-ec2-backoffice-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="aws_access_key_id" name="aws_access_key_id" 
                                   value="<?php echo esc_attr($config['aws_access_key_id'] ?? ''); ?>" 
                                   class="regular-text" required>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="aws_secret_access_key"><?php echo esc_html__('Secret Access Key', 'wp-ec2-backoffice-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="aws_secret_access_key" name="aws_secret_access_key" 
                                   value="<?php echo esc_attr($masked_secret); ?>" 
                                   class="regular-text" 
                                   placeholder="<?php echo esc_attr__('Enter a new key or leave blank to keep the current one', 'wp-ec2-backoffice-plugin'); ?>">
                            <p class="description">
                                <?php echo esc_html__('The key is stored encrypted in the WordPress database.', 'wp-ec2-backoffice-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="ec2_instance_id"><?php echo esc_html__('Instance ID', 'wp-ec2-backoffice-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="ec2_instance_id" name="ec2_instance_id" 
                                   value="<?php echo esc_attr($config['ec2_instance_id'] ?? ''); ?>" 
                                   class="regular-text" required>
                            <p class="description">
                                <?php echo esc_html__('Example: i-1234567890abcdef0', 'wp-ec2-backoffice-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="windows_password"><?php echo esc_html__('Windows Password', 'wp-ec2-backoffice-plugin'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="windows_password" name="windows_password" 
                                   value="" 
                                   class="regular-text"
                                   placeholder="<?php echo esc_attr__('Enter a new password or leave blank to keep the current one', 'wp-ec2-backoffice-plugin'); ?>">
                            <p class="description">
                                <?php echo esc_html__('Password for the Windows Administrator account.', 'wp-ec2-backoffice-plugin'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_config" class="button button-primary" 
                           value="<?php echo esc_attr__('Save Configuration', 'wp-ec2-backoffice-plugin'); ?>">
                    <button type="button" id="ec2-test-connection-btn" class="button button-secondary">
                        <?php echo esc_html__('Test Connection', 'wp-ec2-backoffice-plugin'); ?>
                    </button>
                    <span id="ec2-test-loading" class="spinner" style="display:none;"></span>
                </p>
            </form>

            <div id="ec2-test-result" style="margin-top:20px;"></div>

            <!-- AJAX Nonce -->
            <input type="hidden" id="ec2-ajax-nonce" value="<?php echo esc_attr(wp_create_nonce('ec2_ajax_nonce')); ?>">
        </div>
        <?php
    }

    /**
     * Handle AJAX request to start instance
     */
    public function handle_ajax_start_instance() {
        // Verify nonce and capability
        if (!$this->verify_nonce_and_capability()) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        // Check if plugin is configured
        if (!$this->config_store->is_configured()) {
            wp_send_json_error(array('message' => __('The plugin is not configured.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        // Get instance ID from configuration
        $config = $this->config_store->get_config();
        $instance_id = $config['ec2_instance_id'];

        // Update security group first
        $sg_result = $this->sg_manager->update_rdp_access($instance_id);
        
        if (!$sg_result['success']) {
            wp_send_json_error(array(
                'message' => __('Failed to update the security group: ', 'wp-ec2-backoffice-plugin') . $sg_result['error']
            ));
            return;
        }

        // Start the instance
        $start_result = $this->ec2_manager->start_instance();
        
        if ($start_result['success']) {
            wp_send_json_success(array(
                'message' => __('Instance started successfully.', 'wp-ec2-backoffice-plugin'),
                'data' => $start_result['data']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to start the instance: ', 'wp-ec2-backoffice-plugin') . $start_result['error']
            ));
        }
    }

    /**
     * Handle AJAX request to stop instance
     */
    public function handle_ajax_stop_instance() {
        // Verify nonce and capability
        if (!$this->verify_nonce_and_capability()) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        // Check if plugin is configured
        if (!$this->config_store->is_configured()) {
            wp_send_json_error(array('message' => __('The plugin is not configured.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        // Stop the instance
        $stop_result = $this->ec2_manager->stop_instance();
        
        if ($stop_result['success']) {
            wp_send_json_success(array(
                'message' => __('Instance stopped successfully.', 'wp-ec2-backoffice-plugin'),
                'data' => $stop_result['data']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to stop the instance: ', 'wp-ec2-backoffice-plugin') . $stop_result['error']
            ));
        }
    }

    /**
     * Handle AJAX request to get status
     */
    public function handle_ajax_get_status() {
        // Verify nonce and capability
        if (!$this->verify_nonce_and_capability()) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        // Check if plugin is configured
        if (!$this->config_store->is_configured()) {
            wp_send_json_error(array('message' => __('The plugin is not configured.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        // Get instance status
        $status_result = $this->ec2_manager->get_instance_status();
        
        if ($status_result['success']) {
            wp_send_json_success(array(
                'data' => $status_result['data']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to retrieve the instance status: ', 'wp-ec2-backoffice-plugin') . $status_result['error']
            ));
        }
    }

    /**
     * Handle AJAX request to test connection
     */
    public function handle_ajax_test_connection() {
        // Verify nonce and capability
        if (!$this->verify_nonce_and_capability()) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        // Check if plugin is configured
        if (!$this->config_store->is_configured()) {
            wp_send_json_error(array('message' => __('The plugin is not configured. Please save the configuration first.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        // Test connection
        $test_result = $this->ec2_manager->test_connection();
        
        if ($test_result['success']) {
            wp_send_json_success(array(
                'message' => __('Connection successful. Credentials are valid.', 'wp-ec2-backoffice-plugin'),
                'data' => $test_result['data']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Connection error: ', 'wp-ec2-backoffice-plugin') . $test_result['error']
            ));
        }
    }

    /**
     * Handle AJAX request to retrieve the Windows password on demand.
     */
    public function handle_ajax_get_windows_password() {
        if (!$this->verify_nonce_and_capability()) {
            wp_send_json_error(array('message' => __('Security verification failed.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        $config = $this->config_store->get_config();
        $windows_password = $config['windows_password'] ?? '';

        if (empty($windows_password)) {
            wp_send_json_error(array('message' => __('No Windows password is configured.', 'wp-ec2-backoffice-plugin')));
            return;
        }

        wp_send_json_success(array(
            'password' => $windows_password,
        ));
    }

    /**
     * Handle AJAX request to download RDP file
     */
    public function handle_ajax_download_rdp() {
        // Verify nonce and capability
        if (!$this->verify_nonce_and_capability()) {
            wp_die(esc_html__('Security verification failed.', 'wp-ec2-backoffice-plugin'));
            return;
        }

        // Check if plugin is configured
        if (!$this->config_store->is_configured()) {
            wp_die(esc_html__('The plugin is not configured.', 'wp-ec2-backoffice-plugin'));
            return;
        }

        // Get instance status to verify it's running and get public IP
        $status_result = $this->ec2_manager->get_instance_status();
        
        if (!$status_result['success']) {
            wp_die(esc_html__('Failed to retrieve the instance status.', 'wp-ec2-backoffice-plugin'));
            return;
        }

        $instance_data = $status_result['data'];
        
        // Check if instance is running
        if ($instance_data['state'] !== 'running') {
            wp_die(esc_html__('The instance must be running before you can download the RDP file.', 'wp-ec2-backoffice-plugin'));
            return;
        }

        // Check if public IP is available
        if (empty($instance_data['public_ip'])) {
            wp_die(esc_html__('Could not retrieve the public IP address for the instance.', 'wp-ec2-backoffice-plugin'));
            return;
        }

        // Generate RDP file
        $public_ip = $instance_data['public_ip'];
        $instance_id = $instance_data['instance_id'];
        
        $rdp_content = $this->rdp_generator->generate_rdp_file($public_ip, $instance_id);
        $rdp_filename = $this->rdp_generator->get_rdp_filename($instance_id);

        // Send headers for file download
        header('Content-Type: application/x-rdp');
        header('Content-Disposition: attachment; filename="' . $rdp_filename . '"');
        header('Content-Length: ' . strlen($rdp_content));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');

        // Output RDP content
        echo $rdp_content;
        exit;
    }

    /**
     * Verify nonce and user capability
     *
     * @return bool True if valid, false otherwise
     */
    private function verify_nonce_and_capability() {
        // Check nonce
        if (!isset($_REQUEST['nonce'])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash($_REQUEST['nonce']));
        if (!wp_verify_nonce($nonce, 'ec2_ajax_nonce')) {
            return false;
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Handle configuration save from POST request
     *
     * @return void
     */
    private function handle_save_configuration() {
        // Get POST data
        $config_data = array(
            'aws_region' => isset($_POST['aws_region']) ? sanitize_text_field(wp_unslash($_POST['aws_region'])) : '',
            'aws_access_key_id' => isset($_POST['aws_access_key_id']) ? sanitize_text_field(wp_unslash($_POST['aws_access_key_id'])) : '',
            'ec2_instance_id' => isset($_POST['ec2_instance_id']) ? sanitize_text_field(wp_unslash($_POST['ec2_instance_id'])) : '',
        );

        // Handle secret access key - only update if not masked
        if (isset($_POST['aws_secret_access_key']) && !empty($_POST['aws_secret_access_key'])) {
            $secret_key = sanitize_text_field(wp_unslash($_POST['aws_secret_access_key']));
            // Check if it's not the masked value
            if (strpos($secret_key, '•') === false) {
                $config_data['aws_secret_access_key'] = $secret_key;
            } else {
                // Keep existing secret key
                $existing_config = $this->config_store->get_config();
                if (!empty($existing_config['aws_secret_access_key'])) {
                    $config_data['aws_secret_access_key'] = $existing_config['aws_secret_access_key'];
                }
            }
        } else {
            // Keep existing secret key if field is empty
            $existing_config = $this->config_store->get_config();
            if (!empty($existing_config['aws_secret_access_key'])) {
                $config_data['aws_secret_access_key'] = $existing_config['aws_secret_access_key'];
            }
        }

        // Handle Windows password
        if (isset($_POST['windows_password']) && !empty($_POST['windows_password'])) {
            $config_data['windows_password'] = sanitize_text_field(wp_unslash($_POST['windows_password']));
        } else {
            // Keep existing password if field is empty
            $existing_config = $this->config_store->get_config();
            if (!empty($existing_config['windows_password'])) {
                $config_data['windows_password'] = $existing_config['windows_password'];
            }
        }

        // Validate configuration
        $validation = $this->config_store->validate_config($config_data);
        
        if (!$validation['valid']) {
            // Display error notice
            add_settings_error(
                'ec2_config',
                'ec2_config_error',
                __('Validation error: ', 'wp-ec2-backoffice-plugin') . implode(', ', $validation['errors']),
                'error'
            );
            return;
        }

        // Save configuration
        $save_result = $this->config_store->save_config($config_data);
        
        if ($save_result) {
            // Display success notice
            add_settings_error(
                'ec2_config',
                'ec2_config_success',
                __('Configuration saved successfully.', 'wp-ec2-backoffice-plugin'),
                'success'
            );
        } else {
            // Display error notice
            add_settings_error(
                'ec2_config',
                'ec2_config_error',
                __('Failed to save the configuration.', 'wp-ec2-backoffice-plugin'),
                'error'
            );
        }

        // Display admin notices
        settings_errors('ec2_config');
    }
}
