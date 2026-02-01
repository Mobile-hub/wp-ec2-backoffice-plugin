<?php
/**
 * Configuration Store Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles secure storage and retrieval of AWS credentials and configuration
 */
class Configuration_Store {
    /**
     * WordPress option name for storing configuration
     */
    const OPTION_NAME = 'wp_ec2_backoffice_config';

    /**
     * Save configuration to WordPress database
     *
     * Validates required fields and encrypts the Secret Access Key before storing.
     *
     * @param array $config Configuration array with keys:
     *                      - aws_region: AWS region (e.g., 'us-east-1')
     *                      - aws_access_key_id: AWS Access Key ID
     *                      - aws_secret_access_key: AWS Secret Access Key (will be encrypted)
     *                      - ec2_instance_id: EC2 Instance ID
     *                      - windows_password: Windows Administrator password (optional, will be encrypted if provided)
     * @return bool True on success, false on failure
     */
    public function save_config(array $config) {
        // Validate configuration before saving
        $validation = $this->validate_config($config);
        if (!$validation['valid']) {
            // Log validation errors if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('WP EC2 Backoffice: Configuration validation failed: ' . implode(', ', $validation['errors']));
            }
            return false;
        }

        // Prepare configuration for storage
        $config_to_store = array(
            'aws_region' => sanitize_text_field($config['aws_region']),
            'aws_access_key_id' => sanitize_text_field($config['aws_access_key_id']),
            'aws_secret_access_key' => $this->encrypt_secret($config['aws_secret_access_key']),
            'ec2_instance_id' => sanitize_text_field($config['ec2_instance_id']),
        );

        // Encrypt and store Windows password if provided
        if (!empty($config['windows_password'])) {
            $config_to_store['windows_password'] = $this->encrypt_secret($config['windows_password']);
        }

        // Save to WordPress options table
        $result = update_option(self::OPTION_NAME, $config_to_store);

        // Log success if WP_DEBUG is enabled
        if ($result && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WP EC2 Backoffice: Configuration saved successfully');
        }

        return $result;
    }

    /**
     * Get configuration from WordPress database
     *
     * Retrieves configuration and decrypts the Secret Access Key.
     *
     * @return array Configuration array with decrypted secrets, or empty array if not configured
     */
    public function get_config() {
        // Get configuration from WordPress options
        $config = get_option(self::OPTION_NAME, array());

        // Return empty array if not configured
        if (empty($config)) {
            return array();
        }

        // Decrypt the Secret Access Key
        if (!empty($config['aws_secret_access_key'])) {
            $config['aws_secret_access_key'] = $this->decrypt_secret($config['aws_secret_access_key']);
        }

        // Decrypt Windows password if present
        if (!empty($config['windows_password'])) {
            $config['windows_password'] = $this->decrypt_secret($config['windows_password']);
        }

        return $config;
    }

    /**
     * Check if plugin is configured
     *
     * Verifies that all required configuration fields are present.
     *
     * @return bool True if configured, false otherwise
     */
    public function is_configured() {
        $config = get_option(self::OPTION_NAME, array());

        // Check if all required fields are present and non-empty
        $required_fields = array(
            'aws_region',
            'aws_access_key_id',
            'aws_secret_access_key',
            'ec2_instance_id',
        );

        foreach ($required_fields as $field) {
            if (empty($config[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate configuration array
     *
     * Checks that all required fields are present and non-empty.
     *
     * @param array $config Configuration to validate
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validate_config(array $config) {
        $errors = array();

        // Define required fields
        $required_fields = array(
            'aws_region' => 'AWS Region',
            'aws_access_key_id' => 'AWS Access Key ID',
            'aws_secret_access_key' => 'AWS Secret Access Key',
            'ec2_instance_id' => 'EC2 Instance ID',
        );

        // Check each required field
        foreach ($required_fields as $field => $label) {
            if (empty($config[$field]) || trim($config[$field]) === '') {
                $errors[] = sprintf('%s is required', $label);
            }
        }

        // Additional validation for AWS region format (basic check)
        if (!empty($config['aws_region'])) {
            // AWS regions follow pattern: us-east-1, eu-west-2, etc.
            if (!preg_match('/^[a-z]{2}-[a-z]+-\d+$/', $config['aws_region'])) {
                $errors[] = 'AWS Region format is invalid (expected format: us-east-1)';
            }
        }

        // Additional validation for instance ID format
        if (!empty($config['ec2_instance_id'])) {
            // Instance IDs start with 'i-' followed by alphanumeric characters
            if (!preg_match('/^i-[a-f0-9]+$/', $config['ec2_instance_id'])) {
                $errors[] = 'EC2 Instance ID format is invalid (expected format: i-1234567890abcdef0)';
            }
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Encrypt a secret string
     *
     * Uses AES-256-CBC encryption with WordPress salt as the key.
     * The encrypted data is base64 encoded for safe storage.
     *
     * @param string $secret Secret to encrypt
     * @return string Encrypted secret (base64 encoded)
     */
    public function encrypt_secret(string $secret) {
        // Get encryption key from WordPress salt
        $key = $this->get_encryption_key();
        
        // Generate a random initialization vector
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        // Encrypt the secret
        $encrypted = openssl_encrypt(
            $secret,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Combine IV and encrypted data, then base64 encode
        // Format: base64(iv + encrypted_data)
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt an encrypted secret
     *
     * Decrypts data that was encrypted with encrypt_secret().
     *
     * @param string $encrypted Encrypted secret (base64 encoded)
     * @return string Decrypted secret
     */
    public function decrypt_secret(string $encrypted) {
        // Get encryption key from WordPress salt
        $key = $this->get_encryption_key();
        
        // Decode the base64 encoded data
        $data = base64_decode($encrypted);
        
        // Extract IV and encrypted data
        $iv_length = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $iv_length);
        $encrypted_data = substr($data, $iv_length);
        
        // Decrypt the data
        $decrypted = openssl_decrypt(
            $encrypted_data,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return $decrypted;
    }

    /**
     * Get encryption key from WordPress salt
     *
     * Uses wp_salt() to generate a consistent encryption key.
     * The key is hashed to ensure it's the correct length for AES-256 (32 bytes).
     *
     * @return string 32-byte encryption key
     */
    private function get_encryption_key() {
        // Use WordPress salt as the base for the encryption key
        // Hash it to ensure we get exactly 32 bytes for AES-256
        return hash('sha256', wp_salt('auth'), true);
    }
}
