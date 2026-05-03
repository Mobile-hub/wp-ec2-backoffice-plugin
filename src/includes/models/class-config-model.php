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
        $errors = array();

        // Validate AWS Region
        if (empty($this->aws_region)) {
            $errors[] = __('AWS Region is required', 'wp-ec2-backoffice-plugin');
        }

        // Validate AWS Access Key ID
        if (empty($this->aws_access_key_id)) {
            $errors[] = __('AWS Access Key ID is required', 'wp-ec2-backoffice-plugin');
        }

        // Validate AWS Secret Access Key
        if (empty($this->aws_secret_access_key)) {
            $errors[] = __('AWS Secret Access Key is required', 'wp-ec2-backoffice-plugin');
        }

        // Validate EC2 Instance ID
        if (empty($this->ec2_instance_id)) {
            $errors[] = __('EC2 Instance ID is required', 'wp-ec2-backoffice-plugin');
        } elseif (!preg_match('/^i-[a-f0-9]{8,17}$/', $this->ec2_instance_id)) {
            $errors[] = __('EC2 Instance ID format is invalid', 'wp-ec2-backoffice-plugin');
        }

        // Windows password is optional but validate if provided
        // (it may not be set initially)

        return array(
            'valid' => empty($errors),
            'errors' => $errors
        );
    }

    /**
     * Convert to array
     *
     * @return array Configuration as array
     */
    public function to_array() {
        return array(
            'aws_region' => $this->aws_region,
            'aws_access_key_id' => $this->aws_access_key_id,
            'aws_secret_access_key' => $this->aws_secret_access_key,
            'ec2_instance_id' => $this->ec2_instance_id,
            'windows_password' => $this->windows_password
        );
    }

    /**
     * Create from array
     *
     * @param array $data Configuration data
     * @return self
     */
    public static function from_array(array $data) {
        $model = new self();
        $model->aws_region = isset($data['aws_region']) ? $data['aws_region'] : '';
        $model->aws_access_key_id = isset($data['aws_access_key_id']) ? $data['aws_access_key_id'] : '';
        $model->aws_secret_access_key = isset($data['aws_secret_access_key']) ? $data['aws_secret_access_key'] : '';
        $model->ec2_instance_id = isset($data['ec2_instance_id']) ? $data['ec2_instance_id'] : '';
        $model->windows_password = isset($data['windows_password']) ? $data['windows_password'] : '';
        return $model;
    }
}
