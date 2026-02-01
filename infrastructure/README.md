# WordPress EC2 Backoffice Plugin - Infrastructure

This directory contains the AWS infrastructure code for deploying the EC2 Windows instance managed by the WordPress plugin.

## Contents

- **cloudformation-template.yaml**: CloudFormation template that creates all required AWS resources
- **scripts/auto-shutdown.ps1**: PowerShell script that automatically stops the instance after 2 hours of RDP inactivity

## Resources Created

The CloudFormation template creates the following resources:

1. **EC2 Instance**: Windows Server 2022 instance with RDP enabled
2. **Security Group**: Firewall rules for RDP access (managed by WordPress plugin)
3. **IAM User**: Programmatic access user for the WordPress plugin
4. **IAM Access Key**: Credentials for the WordPress plugin
5. **IAM Policy**: Permissions for instance control and security group management
6. **IAM Instance Role**: Allows the instance to stop itself
7. **IAM Instance Profile**: Attaches the role to the instance

## Prerequisites

Before deploying, ensure you have:

- AWS CLI installed and configured
- An AWS account with permissions to create EC2, IAM, and CloudFormation resources
- A VPC with a public subnet
- (Optional) An EC2 Key Pair for troubleshooting access

## Deployment Instructions

### Step 1: Prepare Parameters

You'll need the following information:

- **VpcId**: The ID of your VPC (e.g., `vpc-12345678`)
- **SubnetId**: The ID of a public subnet in your VPC (e.g., `subnet-12345678`)
- **WindowsAdminPassword**: A strong password for the Windows Administrator account (8-41 characters)
- **InstanceType**: Instance size (default: `t3.medium`)
- **KeyPairName**: (Optional) EC2 Key Pair name for troubleshooting

### Step 2: Deploy the Stack

Using AWS CLI:

```bash
aws cloudformation create-stack \
  --stack-name wp-ec2-backoffice \
  --template-body file://cloudformation-template.yaml \
  --parameters \
    ParameterKey=VpcId,ParameterValue=vpc-XXXXXXXX \
    ParameterKey=SubnetId,ParameterValue=subnet-XXXXXXXX \
    ParameterKey=WindowsAdminPassword,ParameterValue=YourSecurePassword123! \
    ParameterKey=InstanceType,ParameterValue=t3.medium \
  --capabilities CAPABILITY_NAMED_IAM \
  --region us-east-1
```

Using AWS Console:

1. Go to CloudFormation in the AWS Console
2. Click "Create stack" → "With new resources"
3. Upload the `cloudformation-template.yaml` file
4. Fill in the parameters
5. Check "I acknowledge that AWS CloudFormation might create IAM resources with custom names"
6. Click "Create stack"

### Step 3: Wait for Completion

The stack creation takes approximately 10-15 minutes. Monitor progress:

```bash
aws cloudformation wait stack-create-complete \
  --stack-name wp-ec2-backoffice \
  --region us-east-1
```

Or watch in the AWS Console CloudFormation page.

### Step 4: Retrieve Outputs

Once complete, get the stack outputs:

```bash
aws cloudformation describe-stacks \
  --stack-name wp-ec2-backoffice \
  --region us-east-1 \
  --query 'Stacks[0].Outputs'
```

You'll need these values for the WordPress plugin configuration:

- **InstanceId**: EC2 instance ID (e.g., `i-1234567890abcdef0`)
- **SecurityGroupId**: Security group ID (e.g., `sg-1234567890abcdef0`)
- **IAMUserAccessKeyId**: Access key ID for the plugin
- **IAMUserSecretAccessKey**: Secret access key for the plugin (save securely!)
- **WindowsAdministratorPassword**: The password you provided
- **InstancePublicIP**: Public IP address (when instance is running)

### Step 5: Configure WordPress Plugin

1. Log in to your WordPress admin panel
2. Navigate to "EC2 Backoffice" → "Configuración"
3. Enter the following values from the stack outputs:
   - **AWS Region**: The region where you deployed (e.g., `us-east-1`)
   - **Access Key ID**: Value from `IAMUserAccessKeyId`
   - **Secret Access Key**: Value from `IAMUserSecretAccessKey`
   - **Instance ID**: Value from `InstanceId`
   - **Windows Password**: Value from `WindowsAdministratorPassword`
4. Click "Guardar Configuración"
5. Click "Probar Conexión" to verify the configuration

## Auto-Shutdown Mechanism

The instance includes an automatic shutdown mechanism to save costs:

- **Monitoring Interval**: Checks every 15 minutes
- **Inactivity Threshold**: 2 hours without active RDP sessions
- **Activity Detection**: Uses `qwinsta` to detect active RDP connections
- **Logging**: All actions logged to Windows Event Log (Application → WP-EC2-AutoShutdown)

### How It Works

1. Every 15 minutes, the scheduled task runs `auto-shutdown.ps1`
2. The script checks for active RDP sessions using `qwinsta`
3. If active sessions are found:
   - Updates the "last activity" timestamp in the registry
   - Logs the activity detection
4. If no active sessions are found:
   - Calculates time since last activity
   - If > 2 hours, stops the instance using AWS CLI
   - Logs the shutdown action

### Viewing Auto-Shutdown Logs

Connect to the instance via RDP and:

1. Open Event Viewer (eventvwr.msc)
2. Navigate to Windows Logs → Application
3. Filter by Source: "WP-EC2-AutoShutdown"

Event IDs:
- **1000**: Active RDP session detected
- **1001**: Instance stopped due to inactivity
- **1002**: No active sessions (informational)
- **1003**: Error occurred
- **1004**: Auto-shutdown system initialized

### Disabling Auto-Shutdown

If you need to disable the auto-shutdown mechanism:

1. Connect to the instance via RDP
2. Open Task Scheduler (taskschd.msc)
3. Find "WP-EC2-AutoShutdown" in the task list
4. Right-click → Disable

To re-enable, right-click → Enable.

## Cost Considerations

### Instance Costs

- **t3.medium**: ~$0.0416/hour (~$30/month if running 24/7)
- **t3.small**: ~$0.0208/hour (~$15/month if running 24/7)

With auto-shutdown after 2 hours of inactivity, actual costs will be much lower depending on usage patterns.

### Storage Costs

- **50 GB gp3 EBS**: ~$4/month

### Data Transfer

- Outbound data transfer charges may apply for RDP sessions and Windows updates

## Security Considerations

### Network Security

- The security group initially has **no ingress rules**
- The WordPress plugin dynamically adds RDP rules for your current IP
- Old rules are automatically removed when new ones are added
- Only TCP port 3389 (RDP) is opened

### IAM Security

- The IAM user has **minimal permissions**:
  - Can only control the specific instance created by this stack
  - Can only modify the specific security group created by this stack
  - Cannot access AWS Console (programmatic access only)
- The instance role can only stop itself (not other instances)

### Credential Protection

- Store the IAM Secret Access Key securely
- The WordPress plugin encrypts credentials in the database
- Never commit credentials to version control

### Windows Security

- Use a strong Administrator password
- Keep Windows updated
- Consider enabling Windows Firewall rules
- Use RDP Network Level Authentication (NLA)

## Troubleshooting

### Stack Creation Fails

**Issue**: Stack creation fails with "CREATE_FAILED"

**Solution**: Check the CloudFormation Events tab for specific error messages. Common issues:
- Invalid VPC or Subnet ID
- Insufficient IAM permissions
- Password doesn't meet complexity requirements

### Instance Not Starting

**Issue**: WordPress plugin shows error when starting instance

**Solution**:
1. Verify the instance exists in EC2 console
2. Check IAM user permissions
3. Test AWS credentials using AWS CLI:
   ```bash
   aws ec2 describe-instances --instance-ids i-XXXXXXXXX --region us-east-1
   ```

### Auto-Shutdown Not Working

**Issue**: Instance doesn't stop after 2 hours of inactivity

**Solution**:
1. Connect via RDP and check Event Viewer logs
2. Verify the scheduled task is enabled and running
3. Check that AWS CLI is installed: `aws --version`
4. Verify instance role has stop permissions

### Cannot Connect via RDP

**Issue**: RDP connection times out or is refused

**Solution**:
1. Verify instance is running in EC2 console
2. Check security group has your current IP allowed on port 3389
3. Verify Windows Firewall allows RDP
4. Check that RDP is enabled on the instance

## Updating the Stack

To update the stack with new parameters or template changes:

```bash
aws cloudformation update-stack \
  --stack-name wp-ec2-backoffice \
  --template-body file://cloudformation-template.yaml \
  --parameters \
    ParameterKey=VpcId,UsePreviousValue=true \
    ParameterKey=SubnetId,UsePreviousValue=true \
    ParameterKey=WindowsAdminPassword,UsePreviousValue=true \
    ParameterKey=InstanceType,ParameterValue=t3.large \
  --capabilities CAPABILITY_NAMED_IAM \
  --region us-east-1
```

**Note**: Changing the instance type will require stopping and starting the instance.

## Deleting the Stack

To remove all resources:

```bash
aws cloudformation delete-stack \
  --stack-name wp-ec2-backoffice \
  --region us-east-1
```

**Warning**: This will permanently delete:
- The EC2 instance and all data on it
- The IAM user and access keys
- The security group
- All associated resources

Make sure to backup any important data before deleting!

## Support

For issues or questions:
- Check the WordPress plugin logs (WP_DEBUG mode)
- Review CloudFormation Events in AWS Console
- Check Windows Event Logs on the instance
- Review the plugin documentation in the main README.md

## License

This infrastructure code is part of the WordPress EC2 Backoffice Plugin and is released under the MIT License.
