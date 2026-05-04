=== WP EC2 Backoffice Plugin ===
Contributors: mobile-hub
Tags: aws, ec2, rdp, admin, infrastructure
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Manage a Windows EC2 instance from the WordPress admin area.

== Description ==

WP EC2 Backoffice Plugin provides a WordPress admin interface for starting and stopping a Windows EC2 instance, refreshing RDP access rules, and downloading RDP connection files.

This repository uses a hybrid layout:

- Root bootstrap file: `wp-ec2-backoffice-plugin.php`
- Source code: `src/`
- Infrastructure assets: `infrastructure/`

== Installation ==

1. Deploy the AWS infrastructure first.
2. Install Composer dependencies with:

   `composer install --working-dir=src --no-dev`

3. Activate the plugin from the WordPress admin area.

== Frequently Asked Questions ==

= Does this plugin store AWS credentials? =

Yes. Sensitive values are encrypted before being stored in WordPress.

= Does the plugin expose the Windows password in the page source? =

No. The Windows password is requested on demand through an authenticated AJAX endpoint.

== Changelog ==

= 1.0.0 =
* Initial public OSS-ready release preparation.