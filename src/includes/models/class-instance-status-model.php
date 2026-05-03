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
        return $this->state === 'running';
    }

    /**
     * Check if instance is stopped
     *
     * @return bool
     */
    public function is_stopped() {
        return $this->state === 'stopped';
    }

    /**
     * Check if instance is in transitional state
     *
     * @return bool
     */
    public function is_transitional() {
        return in_array($this->state, array('pending', 'stopping'), true);
    }

    /**
     * Convert to array
     *
     * @return array Instance status as array
     */
    public function to_array() {
        return array(
            'instance_id' => $this->instance_id,
            'state' => $this->state,
            'public_ip' => $this->public_ip,
            'private_ip' => $this->private_ip,
            'instance_type' => $this->instance_type,
            'tags' => $this->tags
        );
    }
}
