<?php
/**
 * Unit Tests for Configuration_Store
 *
 * @package WP_EC2_Backoffice
 */

use PHPUnit\Framework\TestCase;
use WP_EC2_Backoffice\Configuration_Store;

/**
 * Test Configuration_Store encryption methods
 */
class ConfigurationStoreTest extends TestCase {
    
    /**
     * Configuration Store instance
     *
     * @var Configuration_Store
     */
    private $store;

    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->store = new Configuration_Store();
    }

    /**
     * Test that encrypt_secret returns a non-empty string
     */
    public function test_encrypt_secret_returns_non_empty_string() {
        $secret = 'my-secret-key';
        $encrypted = $this->store->encrypt_secret($secret);
        
        $this->assertNotEmpty($encrypted);
        $this->assertIsString($encrypted);
    }

    /**
     * Test that encrypted value is different from original
     */
    public function test_encrypted_value_differs_from_original() {
        $secret = 'my-secret-key';
        $encrypted = $this->store->encrypt_secret($secret);
        
        $this->assertNotEquals($secret, $encrypted);
    }

    /**
     * Test that encrypt_secret produces different output each time (due to random IV)
     */
    public function test_encrypt_secret_produces_different_output_each_time() {
        $secret = 'my-secret-key';
        $encrypted1 = $this->store->encrypt_secret($secret);
        $encrypted2 = $this->store->encrypt_secret($secret);
        
        // Due to random IV, each encryption should produce different output
        $this->assertNotEquals($encrypted1, $encrypted2);
    }

    /**
     * Test that decrypt_secret correctly decrypts an encrypted value
     */
    public function test_decrypt_secret_returns_original_value() {
        $secret = 'my-secret-key';
        $encrypted = $this->store->encrypt_secret($secret);
        $decrypted = $this->store->decrypt_secret($encrypted);
        
        $this->assertEquals($secret, $decrypted);
    }

    /**
     * Test encryption/decryption round-trip with various strings
     */
    public function test_encryption_round_trip_with_various_strings() {
        $test_cases = [
            'simple-string',
            'AKIAIOSFODNN7EXAMPLE',
            'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
            'special-chars-!@#$%^&*()',
            'unicode-chars-ñáéíóú',
            'long-string-' . str_repeat('x', 1000),
            '',  // empty string
        ];
        
        foreach ($test_cases as $secret) {
            $encrypted = $this->store->encrypt_secret($secret);
            $decrypted = $this->store->decrypt_secret($encrypted);
            
            $this->assertEquals(
                $secret,
                $decrypted,
                "Failed to round-trip encrypt/decrypt: {$secret}"
            );
        }
    }

    /**
     * Test that encrypted output is base64 encoded
     */
    public function test_encrypted_output_is_base64_encoded() {
        $secret = 'my-secret-key';
        $encrypted = $this->store->encrypt_secret($secret);
        
        // Should be valid base64
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded, 'Encrypted output should be valid base64');
        
        // Re-encoding should produce the same result
        $this->assertEquals($encrypted, base64_encode($decoded));
    }

    /**
     * Test encryption with empty string
     */
    public function test_encrypt_empty_string() {
        $secret = '';
        $encrypted = $this->store->encrypt_secret($secret);
        $decrypted = $this->store->decrypt_secret($encrypted);
        
        $this->assertEquals($secret, $decrypted);
    }

    /**
     * Test encryption with very long string
     */
    public function test_encrypt_long_string() {
        $secret = str_repeat('a', 10000);
        $encrypted = $this->store->encrypt_secret($secret);
        $decrypted = $this->store->decrypt_secret($encrypted);
        
        $this->assertEquals($secret, $decrypted);
    }

    /**
     * Test encryption with special characters
     */
    public function test_encrypt_special_characters() {
        $secret = "!@#$%^&*()_+-=[]{}|;':\",./<>?`~\n\r\t";
        $encrypted = $this->store->encrypt_secret($secret);
        $decrypted = $this->store->decrypt_secret($encrypted);
        
        $this->assertEquals($secret, $decrypted);
    }
}
