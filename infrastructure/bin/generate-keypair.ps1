#
# Generate RSA KeyPair for EC2 Windows Instance
# This script generates a private/public key pair and saves them locally
# The public key will be used as a CloudFormation parameter
#
# Usage: .\generate-keypair.ps1 [-StackName "wp-ec2-backoffice"]
#

param(
    [string]$StackName = "wp-ec2-backoffice"
)

$ErrorActionPreference = "Stop"

# Configuration
$OutputDir = ".\keypairs"
$PrivateKeyFile = Join-Path $OutputDir "$StackName-private-key.pem"
$PublicKeyFile = Join-Path $OutputDir "$StackName-public-key.pub"

Write-Host "=== EC2 KeyPair Generator ===" -ForegroundColor Green
Write-Host ""

# Create output directory
if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir | Out-Null
}

# Check if keys already exist
if (Test-Path $PrivateKeyFile) {
    Write-Host "Warning: Private key already exists at $PrivateKeyFile" -ForegroundColor Yellow
    $response = Read-Host "Do you want to overwrite it? (yes/no)"
    if ($response -ne "yes") {
        Write-Host "Aborted."
        exit 1
    }
}

# Check if ssh-keygen is available
try {
    $null = Get-Command ssh-keygen -ErrorAction Stop
} catch {
    Write-Host "Error: ssh-keygen not found. Please install OpenSSH or Git for Windows." -ForegroundColor Red
    Write-Host ""
    Write-Host "Install options:" -ForegroundColor Yellow
    Write-Host "  1. Windows 10/11: Settings > Apps > Optional Features > Add OpenSSH Client"
    Write-Host "  2. Git for Windows: https://git-scm.com/download/win"
    Write-Host "  3. Chocolatey: choco install openssh"
    exit 1
}

# Generate RSA private key (2048 bits)
Write-Host "Generating RSA private key..." -ForegroundColor Green
& ssh-keygen -t rsa -b 2048 -f $PrivateKeyFile -N '""' -C "EC2 KeyPair for $StackName"

if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to generate private key" -ForegroundColor Red
    exit 1
}

# Convert private key to PEM format (required for EC2 get-password-data)
Write-Host "Converting to PEM format..." -ForegroundColor Green
& ssh-keygen -p -m PEM -f $PrivateKeyFile -N '""'

if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to convert to PEM format" -ForegroundColor Red
    exit 1
}

# Extract public key in OpenSSH format
Write-Host "Extracting public key..." -ForegroundColor Green
& ssh-keygen -y -f $PrivateKeyFile | Out-File -FilePath $PublicKeyFile -Encoding ASCII

if ($LASTEXITCODE -ne 0) {
    Write-Host "Error: Failed to extract public key" -ForegroundColor Red
    exit 1
}

# Get the public key content
$PublicKeyContent = Get-Content $PublicKeyFile -Raw
$PublicKeyContent = $PublicKeyContent.Trim()

Write-Host ""
Write-Host "=== KeyPair Generated Successfully ===" -ForegroundColor Green
Write-Host ""
Write-Host "Private Key: " -NoNewline
Write-Host $PrivateKeyFile -ForegroundColor Yellow
Write-Host "Public Key:  " -NoNewline
Write-Host $PublicKeyFile -ForegroundColor Yellow
Write-Host ""
Write-Host "IMPORTANT: Keep the private key secure! You'll need it to decrypt the Windows password." -ForegroundColor Red
Write-Host ""
Write-Host "=== CloudFormation Parameter ===" -ForegroundColor Green
Write-Host ""
Write-Host "Use this value for the KeyPairPublicKey parameter:"
Write-Host ""
Write-Host $PublicKeyContent -ForegroundColor Yellow
Write-Host ""
Write-Host "Or use this AWS CLI command to deploy:"
Write-Host ""
Write-Host @"
aws cloudformation create-stack \
  --stack-name $StackName \
  --template-body file://cloudformation-template.yaml \
  --parameters \
    ParameterKey=VpcId,ParameterValue=<your-vpc-id> \
    ParameterKey=SubnetId,ParameterValue=<your-subnet-id> \
    ParameterKey=KeyPairPublicKey,ParameterValue="$PublicKeyContent" \
  --capabilities CAPABILITY_NAMED_IAM
"@ -ForegroundColor Yellow
Write-Host ""
Write-Host "=== Get Windows Password (after instance is running) ===" -ForegroundColor Green
Write-Host ""
Write-Host "After the instance is running, get the Windows password with:"
Write-Host ""
Write-Host @"
aws ec2 get-password-data \
  --instance-id <instance-id> \
  --priv-launch-key $PrivateKeyFile \
  --region eu-west-1 \
  --query PasswordData \
  --output text
"@ -ForegroundColor Yellow
Write-Host ""
