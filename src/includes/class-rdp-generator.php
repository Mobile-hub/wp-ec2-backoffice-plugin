<?php
/**
 * RDP Generator Class
 *
 * @package WP_EC2_Backoffice
 */

namespace WP_EC2_Backoffice;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generates RDP connection files
 */
class RDP_Generator {
    /**
     * Generate RDP file content
     *
     * @param string $public_ip Instance public IP address
     * @param string $instance_id Instance ID
     * @return string RDP file content
     */
    public function generate_rdp_file(string $public_ip, string $instance_id): string {
        return $this->build_rdp_content($public_ip);
    }

    /**
     * Get RDP filename for instance
     *
     * @param string $instance_id Instance ID
     * @return string Filename following pattern: ec2-instance-{instance-id}.rdp
     */
    public function get_rdp_filename(string $instance_id): string {
        return "ec2-instance-{$instance_id}.rdp";
    }

    /**
     * Build RDP file content with complete RDP format
     *
     * @param string $public_ip Instance public IP address
     * @return string RDP file content
     */
    private function build_rdp_content(string $public_ip): string {
        // Build RDP file content according to Microsoft RDP specification
        // Includes full address, username, screen mode, and other settings
        $rdp_content = <<<RDP
full address:s:{$public_ip}:3389
username:s:Administrator
screen mode id:i:2
use multimon:i:0
desktopwidth:i:1920
desktopheight:i:1080
session bpp:i:32
compression:i:1
keyboardhook:i:2
audiocapturemode:i:0
videoplaybackmode:i:1
connection type:i:7
networkautodetect:i:1
bandwidthautodetect:i:1
displayconnectionbar:i:1
enableworkspacereconnect:i:0
disable wallpaper:i:0
allow font smoothing:i:0
allow desktop composition:i:0
disable full window drag:i:1
disable menu anims:i:1
disable themes:i:0
disable cursor setting:i:0
bitmapcachepersistenable:i:1
audiomode:i:0
redirectprinters:i:1
redirectcomports:i:0
redirectsmartcards:i:1
redirectclipboard:i:1
redirectposdevices:i:0
autoreconnection enabled:i:1
authentication level:i:2
prompt for credentials:i:0
negotiate security layer:i:1
remoteapplicationmode:i:0
alternate shell:s:
shell working directory:s:
gatewayhostname:s:
gatewayusagemethod:i:4
gatewaycredentialssource:i:4
gatewayprofileusagemethod:i:0
promptcredentialonce:i:0
gatewaybrokeringtype:i:0
use redirection server name:i:0
rdgiskdcproxy:i:0
kdcproxyname:s:
RDP;

        return $rdp_content;
    }
}
