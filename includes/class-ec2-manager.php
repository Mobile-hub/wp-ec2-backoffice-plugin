<?php
/**
 * EC2 Manager Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages EC2 instance operations via AWS SDK
 */
class EC2_Manager {
    /**
     * Configuration Store instance
     *
     * @var Configuration_Store
     */
    private $config_store;

    /**
     * Constructor
     *
     * @param Configuration_Store $config_store Configuration store instance
     */
    public function __construct(Configuration_Store $config_store) {
        $this->config_store = $config_store;
    }

    /**
     * Start EC2 instance
     *
     * @return array Response array with success, data, and error keys
     */
    public function start_instance() {
        try {
            // Get configuration
            $config = $this->config_store->get_config();
            
            // Verify instance ID is configured
            if (empty($config['ec2_instance_id'])) {
                return array(
                    'success' => false,
                    'error' => 'EC2 Instance ID not configured. Please configure the plugin first.',
                );
            }

            // Get EC2 client
            $ec2_client = $this->get_ec2_client();

            // Call AWS SDK startInstances
            $result = $ec2_client->startInstances(array(
                'InstanceIds' => array($config['ec2_instance_id']),
            ));

            // Extract instance state information
            $starting_instances = $result->get('StartingInstances');
            if (!empty($starting_instances) && isset($starting_instances[0])) {
                $instance_state = $starting_instances[0];
                
                return array(
                    'success' => true,
                    'data' => array(
                        'instance_id' => $instance_state['InstanceId'],
                        'previous_state' => $instance_state['PreviousState']['Name'],
                        'current_state' => $instance_state['CurrentState']['Name'],
                    ),
                );
            }

            // Fallback if response structure is unexpected
            return array(
                'success' => true,
                'data' => array(
                    'instance_id' => $config['ec2_instance_id'],
                    'message' => 'Start command sent successfully',
                ),
            );

        } catch (\Aws\Exception\AwsException $e) {
            return $this->handle_aws_exception($e);
        } catch (\Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice: Error starting instance: ' . $e->getMessage());
            }
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Stop EC2 instance
     *
     * @return array Response array with success, data, and error keys
     */
    public function stop_instance() {
        try {
            // Get configuration
            $config = $this->config_store->get_config();
            
            // Verify instance ID is configured
            if (empty($config['ec2_instance_id'])) {
                return array(
                    'success' => false,
                    'error' => 'EC2 Instance ID not configured. Please configure the plugin first.',
                );
            }

            // Get EC2 client
            $ec2_client = $this->get_ec2_client();

            // Call AWS SDK stopInstances
            $result = $ec2_client->stopInstances(array(
                'InstanceIds' => array($config['ec2_instance_id']),
            ));

            // Extract instance state information
            $stopping_instances = $result->get('StoppingInstances');
            if (!empty($stopping_instances) && isset($stopping_instances[0])) {
                $instance_state = $stopping_instances[0];
                
                return array(
                    'success' => true,
                    'data' => array(
                        'instance_id' => $instance_state['InstanceId'],
                        'previous_state' => $instance_state['PreviousState']['Name'],
                        'current_state' => $instance_state['CurrentState']['Name'],
                    ),
                );
            }

            // Fallback if response structure is unexpected
            return array(
                'success' => true,
                'data' => array(
                    'instance_id' => $config['ec2_instance_id'],
                    'message' => 'Stop command sent successfully',
                ),
            );

        } catch (\Aws\Exception\AwsException $e) {
            return $this->handle_aws_exception($e);
        } catch (\Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice: Error stopping instance: ' . $e->getMessage());
            }
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Get instance status
     *
     * Returns the current status of the EC2 instance including state, IPs, and instance type.
     * This method calls describe_instance() and formats the response for UI consumption.
     *
     * @return array Response array with success, data (instance status), and error keys
     */
    public function get_instance_status() {
        try {
            // Get full instance details
            $describe_result = $this->describe_instance();
            
            // If describe failed, return the error
            if (!$describe_result['success']) {
                return $describe_result;
            }
            
            // Extract instance data
            $instance_data = $describe_result['data'];
            
            // Format status response for UI
            return array(
                'success' => true,
                'data' => array(
                    'state' => $instance_data['state'],
                    'public_ip' => $instance_data['public_ip'],
                    'private_ip' => $instance_data['private_ip'],
                    'instance_id' => $instance_data['instance_id'],
                    'instance_type' => $instance_data['instance_type'],
                ),
            );
            
        } catch (\Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice: Error getting instance status: ' . $e->getMessage());
            }
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Describe instance details
     *
     * Calls AWS SDK describeInstances() to retrieve full instance information.
     * Parses the response and extracts state, public_ip, private_ip, instance_type, and tags.
     *
     * @return array Response array with success, data (instance details), and error keys
     */
    public function describe_instance() {
        try {
            // Get configuration
            $config = $this->config_store->get_config();
            
            // Verify instance ID is configured
            if (empty($config['ec2_instance_id'])) {
                return array(
                    'success' => false,
                    'error' => 'EC2 Instance ID not configured. Please configure the plugin first.',
                );
            }

            // Get EC2 client
            $ec2_client = $this->get_ec2_client();

            // Call AWS SDK describeInstances
            $result = $ec2_client->describeInstances(array(
                'InstanceIds' => array($config['ec2_instance_id']),
            ));

            // Extract instance information from response
            $reservations = $result->get('Reservations');
            
            if (empty($reservations) || !isset($reservations[0]['Instances'][0])) {
                return array(
                    'success' => false,
                    'error' => 'Instance not found in AWS response',
                );
            }

            $instance = $reservations[0]['Instances'][0];
            
            // Extract state
            $state = isset($instance['State']['Name']) ? $instance['State']['Name'] : 'unknown';
            
            // Extract public IP (only available when running)
            $public_ip = isset($instance['PublicIpAddress']) ? $instance['PublicIpAddress'] : null;
            
            // Extract private IP
            $private_ip = isset($instance['PrivateIpAddress']) ? $instance['PrivateIpAddress'] : null;
            
            // Extract instance type
            $instance_type = isset($instance['InstanceType']) ? $instance['InstanceType'] : 'unknown';
            
            // Extract tags
            $tags = array();
            if (isset($instance['Tags']) && is_array($instance['Tags'])) {
                foreach ($instance['Tags'] as $tag) {
                    if (isset($tag['Key']) && isset($tag['Value'])) {
                        $tags[$tag['Key']] = $tag['Value'];
                    }
                }
            }

            return array(
                'success' => true,
                'data' => array(
                    'instance_id' => $config['ec2_instance_id'],
                    'state' => $state,
                    'public_ip' => $public_ip,
                    'private_ip' => $private_ip,
                    'instance_type' => $instance_type,
                    'tags' => $tags,
                ),
            );

        } catch (\Aws\Exception\AwsException $e) {
            return $this->handle_aws_exception($e);
        } catch (\Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice: Error describing instance: ' . $e->getMessage());
            }
            
            return array(
                'success' => false,
                'error' => $e->getMessage(),
            );
        }
    }

    /**
     * Test AWS connection
     *
     * Tests the AWS credentials by attempting to describe the configured instance.
     * This validates that credentials are correct and have necessary permissions.
     *
     * @return array Response array with success, data (instance details), and error keys
     */
    public function test_connection() {
        try {
            // Attempt to describe the instance
            $result = $this->describe_instance();
            
            // If describe succeeded, connection test passed
            if ($result['success']) {
                return array(
                    'success' => true,
                    'data' => array(
                        'message' => 'Connection successful',
                        'instance_id' => $result['data']['instance_id'],
                        'state' => $result['data']['state'],
                        'instance_type' => $result['data']['instance_type'],
                    ),
                );
            }
            
            // If describe failed, return the error
            return $result;
            
        } catch (\Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice: Error testing connection: ' . $e->getMessage());
            }
            
            return array(
                'success' => false,
                'error' => 'Connection test failed: ' . $e->getMessage(),
            );
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
            throw new \Exception('AWS configuration not found. Please configure the plugin first.');
        }

        // Verify required fields are present
        $required_fields = array('aws_region', 'aws_access_key_id', 'aws_secret_access_key');
        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                throw new \Exception("Missing required configuration field: {$field}");
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
            throw new \Exception('Failed to initialize AWS EC2 client: ' . $e->getMessage());
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
            'InvalidClientTokenId' => 'Invalid AWS credentials. Please check your Access Key ID.',
            'SignatureDoesNotMatch' => 'Invalid AWS credentials. Please check your Secret Access Key.',
            'UnauthorizedOperation' => 'Insufficient permissions. Please verify your IAM policy.',
            'InvalidInstanceID.NotFound' => 'EC2 instance not found. Please check your Instance ID.',
            'RequestLimitExceeded' => 'AWS rate limit exceeded. Please try again in a few moments.',
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
