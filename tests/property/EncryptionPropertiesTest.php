<?php
/**
 * Property-Based Tests for Encryption
 *
 * @package WP_EC2_Backoffice
 */

use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use WP_EC2_Backoffice\Configuration_Store;

/**
 * Property-based tests for Configuration_Store encryption
 */
class EncryptionPropertiesTest extends TestCase {
    use TestTrait;

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
     * Feature: wp-ec2-backoffice-plugin, Property 10: Encryption Round-Trip
     * Validates: Requirements 6.2, 9.1
     *
     * Property: For any AWS Secret Access Key, encrypting then decrypting should produce the original value.
     *
     * This property ensures that the encryption/decryption mechanism is lossless and reliable.
     * It validates that:
     * - The encryption algorithm (AES-256-CBC) is correctly implemented
     * - The decryption process correctly reverses the encryption
     * - No data is lost or corrupted during the round-trip
     * - The implementation works for all possible input strings
     */
    public function test_encryption_decryption_round_trip() {
        $this->forAll(
            \Eris\Generator\string()
        )
        ->then(function ($secret) {
            // Encrypt the secret
            $encrypted = $this->store->encrypt_secret($secret);
            
            // Decrypt the encrypted value
            $decrypted = $this->store->decrypt_secret($encrypted);
            
            // The decrypted value should match the original
            $this->assertEquals(
                $secret,
                $decrypted,
                "Encryption round-trip failed for secret of length " . strlen($secret)
            );
        });
    }

    /**
     * Property: Encrypted values should always be different from the original
     *
     * This ensures that secrets are actually being encrypted and not stored in plaintext.
     */
    public function test_encrypted_value_differs_from_original() {
        $this->forAll(
            \Eris\Generator\string()
        )
        ->when(function ($secret) {
            // Only test non-empty strings longer than 5 chars
            return strlen($secret) > 5;
        })
        ->then(function ($secret) {
            $encrypted = $this->store->encrypt_secret($secret);
            
            // The encrypted value should never be the same as the original
            $this->assertNotEquals(
                $secret,
                $encrypted,
                "Encrypted value should differ from original"
            );
        });
    }

    /**
     * Property: Encrypted output should always be valid base64
     *
     * This ensures the encrypted data can be safely stored in the database.
     */
    public function test_encrypted_output_is_valid_base64() {
        $this->forAll(
            \Eris\Generator\string()
        )
        ->then(function ($secret) {
            $encrypted = $this->store->encrypt_secret($secret);
            
            // Should be valid base64
            $decoded = base64_decode($encrypted, true);
            $this->assertNotFalse(
                $decoded,
                "Encrypted output should be valid base64"
            );
            
            // Re-encoding should produce the same result
            $this->assertEquals(
                $encrypted,
                base64_encode($decoded),
                "Base64 encoding should be consistent"
            );
        });
    }

    /**
     * Property: Multiple encryptions of the same value should produce different outputs
     *
     * This ensures that the IV (initialization vector) is properly randomized,
     * which is important for security.
     */
    public function test_multiple_encryptions_produce_different_outputs() {
        $this->forAll(
            \Eris\Generator\string()
        )
        ->when(function ($secret) {
            // Only test non-empty strings
            return strlen($secret) > 0;
        })
        ->then(function ($secret) {
            $encrypted1 = $this->store->encrypt_secret($secret);
            $encrypted2 = $this->store->encrypt_secret($secret);
            
            // Due to random IV, each encryption should produce different output
            $this->assertNotEquals(
                $encrypted1,
                $encrypted2,
                "Multiple encryptions should produce different outputs (random IV)"
            );
            
            // But both should decrypt to the same original value
            $decrypted1 = $this->store->decrypt_secret($encrypted1);
            $decrypted2 = $this->store->decrypt_secret($encrypted2);
            
            $this->assertEquals($secret, $decrypted1);
            $this->assertEquals($secret, $decrypted2);
        });
    }

    /**
     * Property: Encryption should handle special characters
     *
     * This ensures the encryption works with various character encodings.
     */
    public function test_encryption_handles_special_characters() {
        $this->forAll(
            \Eris\Generator\string()
        )
        ->then(function ($baseSecret) {
            // Test with various special character additions
            $testCases = [
                $baseSecret,
                $baseSecret . "!@#$%^&*()",
                $baseSecret . "\n\r\t",
            ];
            
            foreach ($testCases as $secret) {
                $encrypted = $this->store->encrypt_secret($secret);
                $decrypted = $this->store->decrypt_secret($encrypted);
                
                $this->assertEquals(
                    $secret,
                    $decrypted,
                    "Encryption should handle special characters"
                );
            }
        });
    }
}
