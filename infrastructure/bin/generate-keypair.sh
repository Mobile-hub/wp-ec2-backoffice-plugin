#!/bin/bash
#
# Generate RSA KeyPair for EC2 Windows Instance
# This script generates a private/public key pair and saves them locally
# The public key will be used as a CloudFormation parameter
#
# Usage: ./generate-keypair.sh [stack-name]
#

set -e

# Get stack name from argument or use default
STACK_NAME="${1:-wp-ec2-backoffice}"
OUTPUT_DIR="../keypairs"
PRIVATE_KEY_FILE="${OUTPUT_DIR}/${STACK_NAME}-private-key.pem"
PUBLIC_KEY_FILE="${OUTPUT_DIR}/${STACK_NAME}-public-key.pub"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=== EC2 KeyPair Generator ===${NC}"
echo ""

# Create output directory
mkdir -p "${OUTPUT_DIR}"

# Check if keys already exist
if [ -f "${PRIVATE_KEY_FILE}" ]; then
    echo -e "${YELLOW}Warning: Private key already exists at ${PRIVATE_KEY_FILE}${NC}"
    read -p "Do you want to overwrite it? (yes/no): " -r
    if [[ ! $REPLY =~ ^[Yy][Ee][Ss]$ ]]; then
        echo "Aborted."
        exit 1
    fi
fi

# Generate RSA private key (2048 bits)
echo -e "${GREEN}Generating RSA private key...${NC}"
ssh-keygen -t rsa -b 2048 -f "${PRIVATE_KEY_FILE}" -N "" -C "EC2 KeyPair for ${STACK_NAME}"

# Convert private key to PEM format (required for EC2 get-password-data)
echo -e "${GREEN}Converting to PEM format...${NC}"
ssh-keygen -p -m PEM -f "${PRIVATE_KEY_FILE}" -N ""

# Extract public key in OpenSSH format
echo -e "${GREEN}Extracting public key...${NC}"
ssh-keygen -y -f "${PRIVATE_KEY_FILE}" > "${PUBLIC_KEY_FILE}"

# Get the public key content
PUBLIC_KEY_CONTENT=$(cat "${PUBLIC_KEY_FILE}")

echo ""
echo -e "${GREEN}=== KeyPair Generated Successfully ===${NC}"
echo ""
echo -e "Private Key: ${YELLOW}${PRIVATE_KEY_FILE}${NC}"
echo -e "Public Key:  ${YELLOW}${PUBLIC_KEY_FILE}${NC}"
echo ""
echo -e "${RED}IMPORTANT: Keep the private key secure! You'll need it to decrypt the Windows password.${NC}"
echo ""
echo -e "${GREEN}=== CloudFormation Parameter ===${NC}"
echo ""
echo "Use this value for the KeyPairPublicKey parameter:"
echo ""
echo -e "${YELLOW}${PUBLIC_KEY_CONTENT}${NC}"
echo ""
echo "Or use this AWS CLI command to deploy:"
echo ""
echo -e "${YELLOW}aws cloudformation create-stack \\
  --stack-name ${STACK_NAME} \\
  --template-body file://cloudformation-template.yaml \\
  --parameters \\
    ParameterKey=VpcId,ParameterValue=<your-vpc-id> \\
    ParameterKey=SubnetId,ParameterValue=<your-subnet-id> \\
    ParameterKey=KeyPairPublicKey,ParameterValue=\"${PUBLIC_KEY_CONTENT}\" \\
  --capabilities CAPABILITY_NAMED_IAM${NC}"
echo ""
echo -e "${GREEN}=== Get Windows Password (after instance is running) ===${NC}"
echo ""
echo "After the instance is running, get the Windows password with:"
echo ""
echo -e "${YELLOW}aws ec2 get-password-data \\
  --instance-id <instance-id> \\
  --priv-launch-key ${PRIVATE_KEY_FILE} \\
  --region eu-west-1 \\
  --query PasswordData \\
  --output text${NC}"
echo ""
