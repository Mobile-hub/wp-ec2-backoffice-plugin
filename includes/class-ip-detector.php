<?php
/**
 * IP Detector Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Detects the user's current public IP address
 */
class IP_Detector {
    /**
     * Get the current user's public IP address
     *
     * Checks multiple HTTP headers to detect the user's IP address,
     * handling proxy and load balancer scenarios.
     *
     * @return string IP address
     * @throws \Exception If IP cannot be determined or is invalid
     */
    public function get_current_ip(): string {
        $ip = $this->check_headers();
        
        if (empty($ip)) {
            throw new \Exception(__('No se pudo determinar la dirección IP actual', 'wp-ec2-backoffice-plugin'));
        }
        
        if (!$this->validate_ip($ip)) {
            throw new \Exception(
                sprintf(
                    __('La dirección IP detectada no es válida: %s', 'wp-ec2-backoffice-plugin'),
                    $ip
                )
            );
        }
        
        return $ip;
    }

    /**
     * Validate IP address format
     *
     * Validates that the provided string is a valid IPv4 address.
     *
     * @param string $ip IP address to validate
     * @return bool True if valid IPv4 address
     */
    private function validate_ip(string $ip): bool {
        // Validate IPv4 format using filter_var
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        
        // Additional check: ensure it's not a private or reserved IP
        // We want public IPs only for security group rules
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }
        
        return true;
    }

    /**
     * Check various HTTP headers for IP address
     *
     * Checks headers in order of priority:
     * 1. HTTP_X_FORWARDED_FOR - Set by proxies and load balancers
     * 2. HTTP_CLIENT_IP - Set by some proxies
     * 3. REMOTE_ADDR - Direct connection IP
     *
     * @return string IP address or empty string if not found
     */
    private function check_headers(): string {
        // Check X-Forwarded-For header (proxy/load balancer)
        // This header can contain multiple IPs, we want the first one (client IP)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ip_list[0]);
            
            if ($this->validate_ip($ip)) {
                return $ip;
            }
        }
        
        // Check Client-IP header (some proxies)
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = trim($_SERVER['HTTP_CLIENT_IP']);
            
            if ($this->validate_ip($ip)) {
                return $ip;
            }
        }
        
        // Check REMOTE_ADDR (direct connection)
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = trim($_SERVER['REMOTE_ADDR']);
            
            if ($this->validate_ip($ip)) {
                return $ip;
            }
        }
        
        return '';
    }
}
