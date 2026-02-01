<?php
/**
 * Instance Status Model Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice\Models;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * EC2 Instance status data model
 */
class Instance_Status_Model {
    /**
     * Instance ID
     *
     * @var string
     */
    public $instance_id;

    /**
     * Instance state (running, stopped, pending, stopping, terminated)
     *
     * @var string
     */
    public $state;

    /**
     * Public IP address
     *
     * @var string|null
     */
    public $public_ip;

    /**
     * Private IP address
     *
     * @var string|null
     */
    public $private_ip;

    /**
     * Instance type
     *
     * @var string
     */
    public $instance_type;

    /**
     * Instance tags
     *
     * @var array
     */
    public $tags;

    /**
     * Check if instance is running
     *
     * @return bool
     */
    public function is_running() {
        // TODO: Implement in task 8
        return false;
    }

    /**
     * Check if instance is stopped
     *
     * @return bool
     */
    public function is_stopped() {
        // TODO: Implement in task 8
        return false;
    }

    /**
     * Check if instance is in transitional state
     *
     * @return bool
     */
    public function is_transitional() {
        // TODO: Implement in task 8
        return false;
    }

    /**
     * Convert to array
     *
     * @return array Instance status as array
     */
    public function to_array() {
        // TODO: Implement in task 8
        return array();
    }
}
