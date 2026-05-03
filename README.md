# WP EC2 Backoffice Plugin

A WordPress plugin for managing a Windows EC2 instance from the WordPress admin area.

## Overview

This plugin provides a simplified admin interface for managing a Windows EC2 instance used as a remote desktop. It supports:

- Starting and stopping the EC2 instance
- Automatically managing RDP access rules
- Downloading RDP connection files
- Checking instance status from WordPress
- Storing AWS credentials in WordPress settings

## Repository Layout

This repository uses a hybrid layout:

- `wp-ec2-backoffice-plugin.php` at the repository root is the WordPress bootstrap file.
- `src/` contains the actual plugin source code, Composer files, assets, and tests.
- `infrastructure/` contains the AWS deployment assets.

## Prerequisites

- WordPress 5.0 or later
- PHP 7.4 or later with OpenSSL enabled
- Composer
- An AWS account with appropriate permissions
- The AWS infrastructure deployed first (see `infrastructure/`)

## Installation

### Option A: Build the plugin ZIP locally

```bash
git clone https://github.com/Mobile-hub/wp-ec2-backoffice-plugin.git
cd wp-ec2-backoffice-plugin
make install
```

This creates `dist/wp-ec2-backoffice-plugin.zip`, which can be uploaded from the WordPress admin panel.

### Option B: Use the repository directly during development

Clone the repository into `wp-content/plugins/`, then install dependencies inside `src/`:

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/Mobile-hub/wp-ec2-backoffice-plugin.git
cd wp-ec2-backoffice-plugin
composer install --working-dir=src --no-dev
```

### Deploy the AWS infrastructure first

Before using the plugin, deploy the required AWS infrastructure. See [infrastructure/README.md](infrastructure/README.md).

The CloudFormation template creates:

- A Windows Server 2022 EC2 instance
- An IAM user with limited permissions
- A security group for RDP access
- An auto-shutdown script to reduce costs

### Activate the plugin

From the command line:

```bash
wp plugin activate wp-ec2-backoffice-plugin
```

Or from the WordPress admin panel:

1. Go to **Plugins** → **Installed Plugins**
2. Find **WP EC2 Backoffice Plugin**
3. Click **Activate**

## Configuration

### Initial setup

1. In the WordPress admin panel, go to **EC2 Backoffice** in the sidebar.
2. Open the **Configuration** tab.
3. Fill in the values produced by the CloudFormation deployment:

   - **AWS Region**: The region where you deployed the infrastructure (for example `us-east-1`)
   - **Access Key ID**: The IAM access key ID created for the plugin
   - **Secret Access Key**: The IAM secret access key created for the plugin
   - **Instance ID**: The EC2 instance ID (for example `i-1234567890abcdef0`)
   - **Windows Password**: The Windows Administrator password for the instance

4. Click **Save Configuration**.
5. Click **Test Connection** to verify that the credentials are valid.

### Retrieve the CloudFormation values

After the CloudFormation stack is deployed, you can retrieve the required values with:

```bash
aws cloudformation describe-stacks \
  --stack-name wp-ec2-backoffice \
  --query 'Stacks[0].Outputs'
```

Or from the AWS Console:

1. Go to **CloudFormation** → **Stacks**
2. Select your stack
3. Open the **Outputs** tab

## Usage

### Start the instance

1. Open **EC2 Backoffice** in the WordPress admin menu.
2. In the **Access and Control** tab, review the current instance status.
3. Click **Start Instance**.
4. The plugin will:
   - Detect your current public IP address
   - Update the security group so RDP is only allowed from your current IP
   - Start the EC2 instance
5. Wait until the state changes to **running**. This usually takes 1–2 minutes.

### Connect over RDP

Once the instance is **running**:

1. Click **Download RDP File**.
2. Open the downloaded file with your RDP client.
3. When prompted, use:
   - **Username**: `Administrator`
   - **Password**: The Windows password configured in the plugin

### Stop the instance

When you are done using the instance:

1. Close your Remote Desktop session.
2. In WordPress, click **Stop Instance**.
3. The instance will stop and compute charges will stop accruing.

> The instance can also stop automatically after 2 hours without active RDP sessions, depending on the infrastructure configuration.

### Status monitoring

The plugin refreshes the instance status automatically while the admin page is open. Supported states include:

- **stopped**
- **pending**
- **running**
- **stopping**
- **terminated**

## Troubleshooting

### Error: "Could not determine the current IP address"

**Cause**: The plugin cannot detect a valid public client IP.

**Solution**:
- Verify that your WordPress host can access the internet
- If your site is behind a trusted reverse proxy, make sure your proxy configuration is explicit and reliable
- Verify that `$_SERVER['REMOTE_ADDR']` is available on the server

### Error: "Invalid AWS credentials"

**Cause**: The configured access key or secret key is invalid.

**Solution**:
1. Open the **Configuration** tab.
2. Verify the credentials copied from the CloudFormation outputs or Secrets Manager.
3. Make sure no leading or trailing spaces were pasted.
4. Click **Test Connection** again.

### Error: "Insufficient permissions"

**Cause**: The IAM user does not have the required permissions.

**Solution**:
- Verify that the CloudFormation stack completed successfully
- Confirm that the IAM policy is attached as expected
- Review the infrastructure documentation in [infrastructure/README.md](infrastructure/README.md)

### The instance does not start

**Cause**: Several AWS-side issues can cause this.

**Solution**:
1. Check the instance status in the AWS EC2 console.
2. Review any relevant CloudWatch logs.
3. Make sure the instance has not been terminated.
4. Check whether your AWS account hit service quotas.

### RDP connection fails

**Cause**: The security group rule or your client network may be blocking access.

**Solution**:
1. Confirm that your current public IP matches the one expected by the plugin.
2. If your IP changed, start the instance again so the rule is refreshed.
3. Verify in AWS that port 3389 is allowed from your current IP.
4. Confirm that your local firewall allows outbound RDP traffic.

### Error: "Dependencies are not installed"

**Cause**: The AWS SDK for PHP is missing.

**Solution**:
```bash
cd /path/to/wordpress/wp-content/plugins/wp-ec2-backoffice-plugin
composer install --working-dir=src --no-dev
```

### Automatic shutdown does not trigger

**Cause**: The auto-shutdown script or scheduled task is not running as expected.

**Solution**:
1. Connect to the instance over RDP.
2. Open Event Viewer.
3. Review **Windows Logs** → **Application**.
4. Filter for the `WP-EC2-AutoShutdown` source.
5. Confirm that the task is running every 15 minutes.
6. If necessary, inspect the Windows Task Scheduler configuration.

## Security Notes

### Recommended practices

1. Use HTTPS for WordPress admin access.
2. Restrict plugin access to trusted administrators only.
3. Rotate IAM credentials periodically.
4. Monitor AWS usage and logs regularly.
5. Keep WordPress, PHP, and the plugin dependencies updated.

### Credential storage

- The AWS secret access key is encrypted before being stored in WordPress.
- The encryption key is derived from WordPress salts.
- Sensitive values are no longer rendered directly into the admin DOM on page load.

### Instance access

- RDP access is restricted to the current public IP detected by the plugin.
- Security group rules are refreshed automatically when the instance is started.
- Old plugin-managed rules are removed to avoid stale IP allow-lists.

## Infrastructure

See [infrastructure/README.md](infrastructure/README.md) for deployment details.

## Development

### Run tests

```bash
composer install --working-dir=src
src/vendor/bin/phpunit --configuration src/phpunit.xml
```

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome. Please open an issue or a pull request on GitHub.

## Support

To report a bug or request a feature, open an issue on GitHub.

## Acknowledgements

- AWS SDK for PHP
- WordPress Plugin API
