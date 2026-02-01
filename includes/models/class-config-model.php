<?php
/**
 * Configuration Model Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice\Models;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration data model
 */
class Config_Model {
    /**
     * AWS Region
     *
     * @var string
     */
    public $aws_region;

    /**
     * AWS Access Key ID
     *
     * @var string
     */
    public $aws_access_key_id;

    /**
     * AWS Secret Access Key
     *
     * @var string
     */
    public $aws_secret_access_key;

    /**
     * EC2 Instance ID
     *
     * @var string
     */
    public $ec2_instance_id;

    /**
     * Windows Administrator Password
     *
     * @var string
     */
    public $windows_password;

    /**
     * Validate configuration
     *
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validate() {
        // TODO: Implement in task 8
        return array('valid' => false, 'errors' => array());
    }

    /**
     * Convert to array
     *
     * @return array Configuration as array
     */
    public function to_array() {
        // TODO: Implement in task 8
        return array();
    }

    /**
     * Create from array
     *
     * @param array $data Configuration data
     * @return self
     */
    public static function from_array(array $data) {
        // TODO: Implement in task 8
        return new self();
    }
}
