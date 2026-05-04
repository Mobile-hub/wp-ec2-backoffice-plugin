<?php
/**
 * Security Group Manager Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages AWS Security Group rules for RDP access
 */
class Security_Group_Manager {
    /**
     * Configuration Store instance
     *
     * @var Configuration_Store
     */
    private $config_store;

    /**
     * IP Detector instance
     *
     * @var IP_Detector
     */
    private $ip_detector;

    /**
     * Plugin identifier for rule descriptions
     *
     * @var string
     */
    private const PLUGIN_RULE_PREFIX = 'wp-ec2-plugin-managed-';

    /**
     * Constructor
     *
     * @param Configuration_Store $config_store Configuration store instance
     * @param IP_Detector $ip_detector IP detector instance
     */
    public function __construct(Configuration_Store $config_store, IP_Detector $ip_detector) {
        $this->config_store = $config_store;
        $this->ip_detector = $ip_detector;
    }

    /**
     * Update RDP access for the current user's IP
     *
     * This method orchestrates the complete security group update workflow:
     * 1. Detects the user's current public IP
     * 2. Gets the security group ID from the instance
     * 3. Removes old plugin-managed rules
     * 4. Adds a new RDP rule for the current IP
     *
     * If any step fails, the method returns an error and does not proceed.
     * This ensures that security group modifications are atomic and safe.
     *
     * @param string $instance_id EC2 instance ID
     * @return array Response array with success, data, and error keys
     */
    public function update_rdp_access(string $instance_id): array {
        try {
            // Step 1: Get current IP address
            $current_ip = $this->ip_detector->get_current_ip();
            
            if (empty($current_ip)) {
                return array(
                    'success' => false,
                    'error' => __('Could not determine the current IP address.', 'wp-ec2-backoffice-plugin'),
                );
            }

            // Step 2: Get security group ID from instance
            $security_group_id = $this->get_security_group_id($instance_id);
            
            if (empty($security_group_id)) {
                return array(
                    'success' => false,
                    'error' => __('Could not determine the security group ID.', 'wp-ec2-backoffice-plugin'),
                );
            }

            // Step 3: Remove old plugin-managed rules
            $this->remove_old_plugin_rules($security_group_id);

            // Step 4: Add new RDP rule for current IP
            $this->add_rdp_rule($security_group_id, $current_ip);

            // Success
            return array(
                'success' => true,
                'data' => array(
                    'security_group_id' => $security_group_id,
                    'ip_address' => $current_ip,
                    'message' => sprintf(
                        __('Updated the RDP rule for IP %s.', 'wp-ec2-backoffice-plugin'),
                        $current_ip
                    ),
                ),
            );

        } catch (\Aws\Exception\AwsException $e) {
            return $this->handle_aws_exception($e);
        } catch (\Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice: Error updating RDP access: ' . $e->getMessage());
            }
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Get security group ID from instance ID
     *
     * Queries AWS to get the instance details and extracts the first security group ID.
     * Most EC2 instances have at least one security group attached.
     *
     * @param string $instance_id EC2 instance ID
     * @return string Security group ID
     * @throws \Exception If security group cannot be determined
     */
    private function get_security_group_id(string $instance_id): string {
        // Get EC2 client
        $ec2_client = $this->get_ec2_client();

        // Describe the instance to get its security groups
        $result = $ec2_client->describeInstances(array(
            'InstanceIds' => array($instance_id),
        ));

        // Extract security groups from response
        $reservations = $result->get('Reservations');
        
        if (empty($reservations) || !isset($reservations[0]['Instances'][0])) {
            throw new \Exception(__('Instance not found.', 'wp-ec2-backoffice-plugin'));
        }

        $instance = $reservations[0]['Instances'][0];
        
        // Get security groups
        if (empty($instance['SecurityGroups']) || !isset($instance['SecurityGroups'][0]['GroupId'])) {
            throw new \Exception(__('No security groups were found on the instance.', 'wp-ec2-backoffice-plugin'));
        }

        // Return the first security group ID
        return $instance['SecurityGroups'][0]['GroupId'];
    }

    /**
     * Remove old plugin-managed rules from security group
     *
     * Searches for ingress rules with descriptions matching the plugin prefix
     * and removes them. This ensures only one plugin-managed rule exists at a time.
     *
     * @param string $sg_id Security group ID
     * @return void
     * @throws \Exception If removal fails
     */
    private function remove_old_plugin_rules(string $sg_id): void {
        // Get EC2 client
        $ec2_client = $this->get_ec2_client();

        // Describe security group to get current rules
        $result = $ec2_client->describeSecurityGroups(array(
            'GroupIds' => array($sg_id),
        ));

        $security_groups = $result->get('SecurityGroups');
        
        if (empty($security_groups) || !isset($security_groups[0])) {
            throw new \Exception(__('Security group not found.', 'wp-ec2-backoffice-plugin'));
        }

        $security_group = $security_groups[0];
        $ip_permissions = isset($security_group['IpPermissions']) ? $security_group['IpPermissions'] : array();

        // Find and remove plugin-managed rules
        foreach ($ip_permissions as $permission) {
            // Check if this is an RDP rule (port 3389)
            if (isset($permission['FromPort']) && $permission['FromPort'] === 3389 &&
                isset($permission['ToPort']) && $permission['ToPort'] === 3389 &&
                isset($permission['IpProtocol']) && $permission['IpProtocol'] === 'tcp') {
                
                // Check IP ranges for plugin-managed descriptions
                if (isset($permission['IpRanges']) && is_array($permission['IpRanges'])) {
                    foreach ($permission['IpRanges'] as $ip_range) {
                        // Check if description matches plugin prefix
                        if (isset($ip_range['Description']) && 
                            strpos($ip_range['Description'], self::PLUGIN_RULE_PREFIX) === 0) {
                            
                            // Remove this rule
                            try {
                                $ec2_client->revokeSecurityGroupIngress(array(
                                    'GroupId' => $sg_id,
                                    'IpPermissions' => array(
                                        array(
                                            'IpProtocol' => 'tcp',
                                            'FromPort' => 3389,
                                            'ToPort' => 3389,
                                            'IpRanges' => array(
                                                array(
                                                    'CidrIp' => $ip_range['CidrIp'],
                                                    'Description' => $ip_range['Description'],
                                                ),
                                            ),
                                        ),
                                    ),
                                ));

                                // Log removal if WP_DEBUG is enabled
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log(sprintf(
                                        'WP EC2 Backoffice: Removed old plugin rule for IP %s',
                                        $ip_range['CidrIp']
                                    ));
                                }
                            } catch (\Aws\Exception\AwsException $e) {
                                // Log but don't fail - rule might have been removed already
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log(sprintf(
                                        'WP EC2 Backoffice: Failed to remove old rule: %s',
                                        $e->getMessage()
                                    ));
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Add RDP rule for specified IP address
     *
     * Creates a new ingress rule allowing TCP port 3389 (RDP) from the specified IP.
     * The rule includes a description with the plugin prefix and timestamp for identification.
     *
     * @param string $sg_id Security group ID
     * @param string $ip IP address to allow
     * @return void
     * @throws \Exception If rule addition fails
     */
    private function add_rdp_rule(string $sg_id, string $ip): void {
        // Get EC2 client
        $ec2_client = $this->get_ec2_client();

        // Create rule description with timestamp
        $timestamp = time();
        $description = self::PLUGIN_RULE_PREFIX . $timestamp;

        // Add ingress rule for RDP (port 3389)
        $ec2_client->authorizeSecurityGroupIngress(array(
            'GroupId' => $sg_id,
            'IpPermissions' => array(
                array(
                    'IpProtocol' => 'tcp',
                    'FromPort' => 3389,
                    'ToPort' => 3389,
                    'IpRanges' => array(
                        array(
                            'CidrIp' => $ip . '/32', // /32 means single IP
                            'Description' => $description,
                        ),
                    ),
                ),
            ),
        ));

        // Log addition if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'WP EC2 Backoffice: Added RDP rule for IP %s with description %s',
                $ip,
                $description
            ));
        }
    }

    /**
     * Get EC2 client instance
     *
     * Initializes and returns an AWS EC2 client with credentials from Configuration_Store.
     * Throws an exception if configuration is missing or invalid.
     *
     * @return \Aws\Ec2\Ec2Client EC2 client instance
     * @throws \Exception If configuration is not available
     */
    private function get_ec2_client() {
        // Get configuration from store
        $config = $this->config_store->get_config();

        // Verify configuration is available
        if (empty($config)) {
            throw new \Exception(__('AWS configuration not found. Please configure the plugin first.', 'wp-ec2-backoffice-plugin'));
        }

        // Verify required fields are present
        $required_fields = array('aws_region', 'aws_access_key_id', 'aws_secret_access_key');
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                throw new \Exception(sprintf(
                    __('Missing required configuration field: %s', 'wp-ec2-backoffice-plugin'),
                    $field
                ));
            }
        }

        // Initialize EC2 client with credentials
        try {
            $ec2_client = new \Aws\Ec2\Ec2Client(array(
                'region' => $config['aws_region'],
                'version' => 'latest',
                'credentials' => array(
                    'key' => $config['aws_access_key_id'],
                    'secret' => $config['aws_secret_access_key'],
                ),
            ));

            return $ec2_client;
        } catch (\Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice: Failed to initialize EC2 client: ' . $e->getMessage());
            }
            throw new \Exception(__('Failed to initialize the AWS EC2 client: ', 'wp-ec2-backoffice-plugin') . $e->getMessage());
        }
    }

    /**
     * Handle AWS exception and return formatted error
     *
     * Extracts error information from AWS exception and returns a formatted error response.
     * Logs detailed error information when WP_DEBUG is enabled.
     *
     * @param \Aws\Exception\AwsException $e AWS exception
     * @return array Error response array with success, error, and error_code keys
     */
    private function handle_aws_exception($e) {
        // Extract error code and message from AWS exception
        $error_code = $e->getAwsErrorCode();
        $error_message = $e->getAwsErrorMessage();

        // Log detailed error if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'WP EC2 Backoffice: AWS Error [%s]: %s',
                $error_code,
                $error_message
            ));
        }

        // Map common AWS error codes to user-friendly messages
        $user_friendly_messages = array(
            'InvalidClientTokenId' => __('Invalid AWS credentials. Please verify your access key ID.', 'wp-ec2-backoffice-plugin'),
            'SignatureDoesNotMatch' => __('Invalid AWS credentials. Please verify your secret access key.', 'wp-ec2-backoffice-plugin'),
            'UnauthorizedOperation' => __('Insufficient permissions. Please verify the IAM policy.', 'wp-ec2-backoffice-plugin'),
            'InvalidInstanceID.NotFound' => __('EC2 instance not found. Please verify the instance ID.', 'wp-ec2-backoffice-plugin'),
            'InvalidGroup.NotFound' => __('Security group not found.', 'wp-ec2-backoffice-plugin'),
            'RequestLimitExceeded' => __('AWS request limit exceeded. Please try again in a few moments.', 'wp-ec2-backoffice-plugin'),
        );

        // Use user-friendly message if available, otherwise use AWS message
        $display_message = isset($user_friendly_messages[$error_code]) 
            ? $user_friendly_messages[$error_code] 
            : $error_message;

        return array(
            'success' => false,
            'error' => $display_message,
            'error_code' => $error_code,
        );
    }
}

