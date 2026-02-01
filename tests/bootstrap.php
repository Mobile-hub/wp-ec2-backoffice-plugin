<?php
/**
 * PHPUnit Bootstrap File
 *
 * @package WP_EC2_Backoffice
 */

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define test constants
if (!defined('WP_EC2_BACKOFFICE_TESTS')) {
    define('WP_EC2_BACKOFFICE_TESTS', true);
}

// Define ABSPATH for WordPress compatibility
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

// Mock WordPress functions if not defined
if (!function_exists('wp_salt')) {
    /**
     * Mock wp_salt function for testing
     *
     * @param string $scheme Salt scheme
     * @return string Salt value
     */
    function wp_salt($scheme = 'auth') {
        return 'test-salt-key-for-encryption-' . $scheme;
    }
}

