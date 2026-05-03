<?php
/**
 * Response Model Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice\Models;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API response data model
 */
class Response_Model {
    /**
     * Success flag
     *
     * @var bool
     */
    public $success;

    /**
     * Response data
     *
     * @var mixed
     */
    public $data;

    /**
     * Error message
     *
     * @var string|null
     */
    public $error;

    /**
     * Error code
     *
     * @var string|null
     */
    public $error_code;

    /**
     * Create success response
     *
     * @param mixed $data Response data
     * @return self
     */
    public static function success($data) {
        $response = new self();
        $response->success = true;
        $response->data = $data;
        $response->error = null;
        $response->error_code = null;
        return $response;
    }

    /**
     * Create error response
     *
     * @param string $message Error message
     * @param string $code Error code
     * @return self
     */
    public static function error(string $message, string $code = '') {
        $response = new self();
        $response->success = false;
        $response->data = null;
        $response->error = $message;
        $response->error_code = $code;
        return $response;
    }

    /**
     * Convert to JSON
     *
     * @return string JSON representation
     */
    public function to_json() {
        return json_encode(array(
            'success' => $this->success,
            'data' => $this->data,
            'error' => $this->error,
            'error_code' => $this->error_code
        ));
    }
}
