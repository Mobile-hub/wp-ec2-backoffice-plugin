#!/bin/bash
#
# Script de despliegue simplificado para WordPress EC2 Backoffice Plugin
# Automatiza la generación de keypair y despliegue de CloudFormation
#
# Uso: ./deploy.sh [stack-name] [vpc-id] [subnet-id] [region] [instance-type]
#

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Parámetros
STACK_NAME="${1:-wp-ec2-backoffice}"
VPC_ID="${2}"
SUBNET_ID="${3}"
AWS_REGION="${4:-eu-west-1}"
INSTANCE_TYPE="${5:-t3.medium}"

# Rutas
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
KEYPAIR_DIR="${SCRIPT_DIR}/keypairs"
KEYPAIR_PRIVATE="${KEYPAIR_DIR}/${STACK_NAME}-private-key.pem"
KEYPAIR_PUBLIC="${KEYPAIR_DIR}/${STACK_NAME}-public-key.pub"
CF_TEMPLATE="${SCRIPT_DIR}/cloudformation-template.yaml"

echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}WordPress EC2 Backoffice Plugin - Despliegue Automatizado${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo ""

# Validar parámetros
if [ -z "$VPC_ID" ] || [ -z "$SUBNET_ID" ]; then
    echo -e "${RED}Error: VPC_ID y SUBNET_ID son requeridos${NC}"
    echo ""
    echo "Uso:"
    echo "  ./deploy.sh [stack-name] <vpc-id> <subnet-id> [region] [instance-type]"
    echo ""
    echo "Ejemplo:"
    echo "  ./deploy.sh wp-ec2-backoffice vpc-12345678 subnet-12345678 eu-west-1 t3.medium"
    echo ""
    exit 1
fi

echo -e "${YELLOW}Parámetros de despliegue:${NC}"
echo "  Stack Name: $STACK_NAME"
echo "  VPC ID: $VPC_ID"
echo "  Subnet ID: $SUBNET_ID"
echo "  Region: $AWS_REGION"
echo "  Instance Type: $INSTANCE_TYPE"
echo ""

# Paso 1: Generar keypair si no existe
if [ ! -f "$KEYPAIR_PRIVATE" ]; then
    echo -e "${YELLOW}Paso 1/4: Generando keypair RSA...${NC}"
    echo ""
    ./scripts/generate-keypair.sh "$STACK_NAME"
    echo ""
else
    echo -e "${GREEN}Paso 1/4: Keypair ya existe${NC}"
    echo "  Usando: $KEYPAIR_PRIVATE"
    echo ""
fi

# Paso 2: Validar template
echo -e "${YELLOW}Paso 2/4: Validando template de CloudFormation...${NC}"
aws cloudformation validate-template \
    --template-body file://"$CF_TEMPLATE" \
    --region "$AWS_REGION" > /dev/null
echo -e "${GREEN}✓ Template válido${NC}"
echo ""

# Paso 3: Desplegar stack
echo -e "${YELLOW}Paso 3/4: Desplegando stack de CloudFormation...${NC}"
echo ""

# Leer la clave pública
PUBLIC_KEY=$(cat "$KEYPAIR_PUBLIC")

# Crear el stack
aws cloudformation create-stack \
    --stack-name "$STACK_NAME" \
    --template-body file://"$CF_TEMPLATE" \
    --parameters \
        ParameterKey=InstanceType,ParameterValue="$INSTANCE_TYPE" \
        ParameterKey=VpcId,ParameterValue="$VPC_ID" \
        ParameterKey=SubnetId,ParameterValue="$SUBNET_ID" \
        ParameterKey=KeyPairPublicKey,ParameterValue="$PUBLIC_KEY" \
    --capabilities CAPABILITY_NAMED_IAM \
    --region "$AWS_REGION" \
    --tags \
        Key=Project,Value=wp-ec2-backoffice-plugin \
        Key=ManagedBy,Value=deploy-script

echo -e "${GREEN}✓ Stack creado${NC}"
echo ""
echo -e "${YELLOW}Esperando a que el stack se complete...${NC}"
echo "Esto puede tomar 10-15 minutos (la instancia Windows debe inicializarse)"
echo ""

aws cloudformation wait stack-create-complete \
    --stack-name "$STACK_NAME" \
    --region "$AWS_REGION"

echo ""
echo -e "${GREEN}✓ Stack desplegado exitosamente${NC}"
echo ""

# Paso 4: Mostrar outputs
echo -e "${YELLOW}Paso 4/4: Obteniendo credenciales y configuración...${NC}"
echo ""

# Obtener Instance ID
INSTANCE_ID=$(aws cloudformation describe-stacks \
    --stack-name "$STACK_NAME" \
    --region "$AWS_REGION" \
    --query 'Stacks[0].Outputs[?OutputKey==`InstanceId`].OutputValue' \
    --output text)

# Obtener Secret Name
SECRET_NAME=$(aws cloudformation describe-stacks \
    --stack-name "$STACK_NAME" \
    --region "$AWS_REGION" \
    --query 'Stacks[0].Outputs[?OutputKey==`SecretName`].OutputValue' \
    --output text)

# Obtener credenciales IAM desde Secrets Manager
echo -e "${GREEN}Credenciales IAM (desde Secrets Manager):${NC}"
aws secretsmanager get-secret-value \
    --secret-id "$SECRET_NAME" \
    --region "$AWS_REGION" \
    --query SecretString \
    --output text | jq -r 'to_entries | .[] | "  \(.key): \(.value)"'
echo ""

# Obtener contraseña de Windows
echo -e "${GREEN}Contraseña de Windows:${NC}"
echo -e "${YELLOW}Descifrando...${NC}"
PASSWORD=$(aws ec2 get-password-data \
    --instance-id "$INSTANCE_ID" \
    --priv-launch-key "$KEYPAIR_PRIVATE" \
    --region "$AWS_REGION" \
    --query PasswordData \
    --output text 2>/dev/null || echo "Aún no disponible - espera 5-10 minutos")
echo "  Contraseña: $PASSWORD"
echo ""

echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓✓✓ Despliegue completado exitosamente ✓✓✓${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════════════${NC}"
echo ""
echo -e "${YELLOW}Configuración para el plugin de WordPress:${NC}"
echo ""
echo "  1. Región de AWS: $AWS_REGION"
echo "  2. Instance ID: $INSTANCE_ID"
echo "  3. Access Key ID: (ver arriba en credenciales IAM)"
echo "  4. Secret Access Key: (ver arriba en credenciales IAM)"
echo "  5. Contraseña de Windows: $PASSWORD"
echo ""
echo -e "${YELLOW}Notas importantes:${NC}"
echo "  - Guarda estas credenciales de forma segura"
echo "  - La clave privada está en: $KEYPAIR_PRIVATE"
echo "  - La contraseña puede tardar 5-10 minutos en estar disponible"
echo ""
