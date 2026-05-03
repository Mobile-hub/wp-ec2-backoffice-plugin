# Makefile for WP EC2 Backoffice Plugin
# Handles dependency checks, installation, testing, and packaging

.PHONY: help check setup build clean install test deploy deploy-validate deploy-status deploy-outputs deploy-delete deploy-keypair

# Variables
PLUGIN_NAME = wp-ec2-backoffice-plugin
PLUGIN_VERSION = $(shell grep "Version:" wp-ec2-backoffice-plugin.php | awk '{print $$3}')
BUILD_DIR = build
DIST_DIR = dist
ZIP_FILE = $(DIST_DIR)/$(PLUGIN_NAME).zip
SRC_DIR = src

# AWS CloudFormation variables
STACK_NAME ?= wp-ec2-backoffice
AWS_REGION ?= eu-west-1
CF_TEMPLATE = infrastructure/cloudformation-template.yaml
KEYPAIR_DIR = infrastructure/keypairs
KEYPAIR_PRIVATE = $(KEYPAIR_DIR)/$(STACK_NAME)-private-key.pem
KEYPAIR_PUBLIC = $(KEYPAIR_DIR)/$(STACK_NAME)-public-key.pub

# CloudFormation parameters (override with environment variables or command line)
INSTANCE_TYPE ?= t3.medium
VPC_ID ?=
SUBNET_ID ?=

# Colores para output
RED = \033[0;31m
GREEN = \033[0;32m
YELLOW = \033[1;33m
NC = \033[0m # No Color

# Default target
help:
	@echo "$(GREEN)WordPress EC2 Backoffice Plugin - Makefile$(NC)"
	@echo ""
	@echo "Available targets:"
	@echo ""
	@echo "$(GREEN)Plugin:$(NC)"
	@echo "  $(YELLOW)make check$(NC)            - Verify required local dependencies"
	@echo "  $(YELLOW)make setup$(NC)            - Install Composer dependencies in src/"
	@echo "  $(YELLOW)make build$(NC)            - Build the ZIP package for WordPress"
	@echo "  $(YELLOW)make clean$(NC)            - Remove build artifacts and installed dependencies"
	@echo "  $(YELLOW)make install$(NC)          - Run check + setup + build"
	@echo "  $(YELLOW)make test$(NC)             - Run plugin tests"
	@echo ""
	@echo "$(GREEN)AWS Infrastructure:$(NC)"
	@echo "  $(YELLOW)make deploy-keypair$(NC)   - Generate the RSA key pair for EC2"
	@echo "  $(YELLOW)make deploy$(NC)           - Deploy AWS infrastructure with CloudFormation"
	@echo "  $(YELLOW)make deploy-validate$(NC)  - Validate the CloudFormation template"
	@echo "  $(YELLOW)make deploy-status$(NC)    - Show CloudFormation stack status"
	@echo "  $(YELLOW)make deploy-outputs$(NC)   - Show stack outputs (credentials, IPs, etc.)"
	@echo "  $(YELLOW)make deploy-delete$(NC)    - Delete the CloudFormation stack"
	@echo ""

# Verifica que todas las herramientas necesarias estén instaladas
check:
	@echo "$(YELLOW)Verificando dependencias...$(NC)"
	@echo ""
	@command -v php >/dev/null 2>&1 || { echo "$(RED)✗ PHP no está instalado$(NC)"; exit 1; }
	@echo "$(GREEN)✓ PHP encontrado:$(NC) $$(php -v | head -n 1)"
	@php -r "exit(version_compare(PHP_VERSION, '7.4.0', '>=') ? 0 : 1);" || { echo "$(RED)✗ Se requiere PHP 7.4 o superior$(NC)"; exit 1; }
	@echo "$(GREEN)✓ Versión de PHP válida$(NC)"
	@echo ""
	@command -v composer >/dev/null 2>&1 || { echo "$(RED)✗ Composer no está instalado$(NC)"; exit 1; }
	@echo "$(GREEN)✓ Composer encontrado:$(NC) $$(composer --version 2>/dev/null | head -n 1)"
	@echo ""
	@command -v zip >/dev/null 2>&1 || { echo "$(RED)✗ zip no está instalado$(NC)"; exit 1; }
	@echo "$(GREEN)✓ zip encontrado:$(NC) $$(zip -v 2>/dev/null | head -n 2 | tail -n 1)"
	@echo ""
	@php -m | grep -q openssl || { echo "$(RED)✗ Extensión OpenSSL de PHP no está disponible$(NC)"; exit 1; }
	@echo "$(GREEN)✓ Extensión OpenSSL de PHP disponible$(NC)"
	@echo ""
	@echo "$(GREEN)✓ Todas las dependencias están instaladas correctamente$(NC)"

# Install Composer dependencies
setup: check
	@echo "$(YELLOW)Instalando dependencias de Composer...$(NC)"
	@echo ""
	@if [ ! -f "$(SRC_DIR)/composer.json" ]; then \
		echo "$(RED)✗ No se encontró $(SRC_DIR)/composer.json$(NC)"; \
		exit 1; \
	fi
	composer install --no-dev --optimize-autoloader --working-dir=$(SRC_DIR)
	@echo ""
	@echo "$(GREEN)✓ Dependencias instaladas correctamente$(NC)"

# Build the installable plugin ZIP
build: check
	@echo "$(YELLOW)Generando archivo ZIP del plugin...$(NC)"
	@echo ""
	@# Verify that vendor exists in src/
	@if [ ! -d "$(SRC_DIR)/vendor" ]; then \
		echo "$(RED)✗ Directorio $(SRC_DIR)/vendor no encontrado. Ejecuta 'make setup' primero$(NC)"; \
		exit 1; \
	fi
	@# Crear directorios de build
	@mkdir -p $(BUILD_DIR)
	@mkdir -p $(DIST_DIR)
	@# Limpiar build anterior
	@rm -rf $(BUILD_DIR)/$(PLUGIN_NAME)
	@# Crear directorio temporal del plugin
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)
	@echo "$(GREEN)✓ Directorios de build creados$(NC)"
	@# Copy the root bootstrap plus the source tree
	@echo "$(YELLOW)Copying plugin files...$(NC)"
	@cp wp-ec2-backoffice-plugin.php $(BUILD_DIR)/$(PLUGIN_NAME)/
	@mkdir -p $(BUILD_DIR)/$(PLUGIN_NAME)/src
	@cp -r $(SRC_DIR)/admin $(BUILD_DIR)/$(PLUGIN_NAME)/src/
	@cp -r $(SRC_DIR)/includes $(BUILD_DIR)/$(PLUGIN_NAME)/src/
	@cp -r $(SRC_DIR)/languages $(BUILD_DIR)/$(PLUGIN_NAME)/src/
	@cp -r $(SRC_DIR)/tests $(BUILD_DIR)/$(PLUGIN_NAME)/src/
	@cp -r $(SRC_DIR)/vendor $(BUILD_DIR)/$(PLUGIN_NAME)/src/
	@cp $(SRC_DIR)/wp-ec2-backoffice-plugin.php $(BUILD_DIR)/$(PLUGIN_NAME)/src/
	@cp $(SRC_DIR)/composer.json $(BUILD_DIR)/$(PLUGIN_NAME)/src/
	@cp $(SRC_DIR)/phpunit.xml $(BUILD_DIR)/$(PLUGIN_NAME)/src/
	@if [ -f "$(SRC_DIR)/composer.lock" ]; then cp $(SRC_DIR)/composer.lock $(BUILD_DIR)/$(PLUGIN_NAME)/src/; fi
	@cp LICENSE $(BUILD_DIR)/$(PLUGIN_NAME)/
	@cp README.md $(BUILD_DIR)/$(PLUGIN_NAME)/
	@echo "$(GREEN)✓ Archivos copiados$(NC)"
	@# Crear ZIP
	@echo "$(YELLOW)Creando archivo ZIP...$(NC)"
	@cd $(BUILD_DIR) && zip -r ../$(ZIP_FILE) $(PLUGIN_NAME) -q
	@echo "$(GREEN)✓ Archivo ZIP creado: $(ZIP_FILE)$(NC)"
	@# Mostrar información del archivo
	@echo ""
	@echo "$(GREEN)Información del build:$(NC)"
	@echo "  Archivo: $(ZIP_FILE)"
	@echo "  Tamaño: $$(du -h $(ZIP_FILE) | cut -f1)"
	@echo "  Versión: $(PLUGIN_VERSION)"
	@echo ""
	@echo "$(GREEN)✓ Build completado exitosamente$(NC)"
	@echo ""
	@echo "Para instalar el plugin en WordPress:"
	@echo "  1. Ve a Plugins → Añadir nuevo → Subir plugin"
	@echo "  2. Selecciona el archivo: $(ZIP_FILE)"
	@echo "  3. Haz clic en 'Instalar ahora'"

# Clean build artifacts and local dependencies
clean:
	@echo "$(YELLOW)Limpiando archivos temporales...$(NC)"
	@rm -rf $(BUILD_DIR)
	@rm -rf $(DIST_DIR)
	@rm -rf $(SRC_DIR)/vendor
	@rm -f $(SRC_DIR)/composer.lock
	@rm -f $(SRC_DIR)/.phpunit.result.cache
	@echo "$(GREEN)✓ Limpieza completada$(NC)"

# Flujo completo: check + setup + build
install: check setup build
	@echo ""
	@echo "$(GREEN)✓✓✓ Instalación completa exitosa ✓✓✓$(NC)"
	@echo ""
	@echo "El plugin está listo para instalar en WordPress."
	@echo "Archivo generado: $(ZIP_FILE)"

# Run tests
test: check
	@echo "$(YELLOW)Ejecutando tests...$(NC)"
	@echo ""
	@if [ ! -d "$(SRC_DIR)/vendor" ]; then \
		echo "$(RED)✗ Dependencias no instaladas. Ejecuta 'make setup' primero$(NC)"; \
		exit 1; \
	fi
	@if [ ! -f "$(SRC_DIR)/vendor/bin/phpunit" ]; then \
		echo "$(YELLOW)Instalando dependencias de desarrollo...$(NC)"; \
		composer install --working-dir=$(SRC_DIR); \
	fi
	$(SRC_DIR)/vendor/bin/phpunit --configuration $(SRC_DIR)/phpunit.xml
	@echo ""
	@echo "$(GREEN)✓ Tests completados$(NC)"

# ============================================================================
# AWS CloudFormation Deployment Targets
# ============================================================================

# Genera el keypair RSA para la instancia EC2
deploy-keypair:
	@echo "$(YELLOW)Generando keypair RSA para EC2...$(NC)"
	@echo ""
	@# Verificar si ya existe
	@if [ -f "$(KEYPAIR_PRIVATE)" ]; then \
		echo "$(YELLOW)⚠ El keypair ya existe en $(KEYPAIR_PRIVATE)$(NC)"; \
		echo ""; \
		read -p "¿Quieres sobrescribirlo? (yes/no): " confirm; \
		if [ "$confirm" != "yes" ]; then \
			echo "$(YELLOW)Operación cancelada$(NC)"; \
			exit 1; \
		fi; \
	fi
	@# Ejecutar el script de generación
	@cd infrastructure/bin && ./generate-keypair.sh $(STACK_NAME)
	@echo ""
	@echo "$(GREEN)✓ Keypair generado exitosamente$(NC)"
	@echo ""
	@echo "$(RED)IMPORTANTE: Guarda el archivo $(KEYPAIR_PRIVATE) de forma segura$(NC)"
	@echo "$(RED)Lo necesitarás para descifrar la contraseña de Windows$(NC)"
	@echo ""

# Valida el template de CloudFormation
deploy-validate:
	@echo "$(YELLOW)Validando template de CloudFormation...$(NC)"
	@echo ""
	@command -v aws >/dev/null 2>&1 || { echo "$(RED)✗ AWS CLI no está instalado$(NC)"; exit 1; }
	@if [ ! -f "$(CF_TEMPLATE)" ]; then \
		echo "$(RED)✗ Template no encontrado: $(CF_TEMPLATE)$(NC)"; \
		exit 1; \
	fi
	@aws cloudformation validate-template \
		--template-body file://$(CF_TEMPLATE) \
		--region $(AWS_REGION) > /dev/null
	@echo "$(GREEN)✓ Template válido$(NC)"
	@echo ""

# Despliega la infraestructura en AWS
deploy: deploy-validate
	@echo "$(YELLOW)Desplegando infraestructura en AWS...$(NC)"
	@echo ""
	@# Verificar parámetros requeridos
	@if [ -z "$(VPC_ID)" ]; then \
		echo "$(RED)✗ VPC_ID es requerido$(NC)"; \
		echo ""; \
		echo "Uso:"; \
		echo "  make deploy VPC_ID='vpc-xxx' SUBNET_ID='subnet-xxx'"; \
		echo ""; \
		echo "Parámetros opcionales:"; \
		echo "  STACK_NAME='wp-ec2-backoffice' (default)"; \
		echo "  AWS_REGION='eu-west-1' (default)"; \
		echo "  INSTANCE_TYPE='t3.medium' (default)"; \
		echo ""; \
		echo "Nota: Si no has generado el keypair, ejecuta primero:"; \
		echo "  make deploy-keypair"; \
		exit 1; \
	fi
	@if [ -z "$(SUBNET_ID)" ]; then \
		echo "$(RED)✗ SUBNET_ID es requerido$(NC)"; \
		echo "Ejemplo: make deploy VPC_ID='vpc-12345678' SUBNET_ID='subnet-12345678'"; \
		exit 1; \
	fi
	@# Verificar que existe el keypair
	@if [ ! -f "$(KEYPAIR_PUBLIC)" ]; then \
		echo "$(RED)✗ Keypair público no encontrado: $(KEYPAIR_PUBLIC)$(NC)"; \
		echo ""; \
		echo "Genera el keypair primero con:"; \
		echo "  make deploy-keypair"; \
		exit 1; \
	fi
	@# Leer la clave pública
	@PUBLIC_KEY=$$(cat $(KEYPAIR_PUBLIC)); \
	echo "$(GREEN)Parámetros de despliegue:$(NC)"; \
	echo "  Stack Name: $(STACK_NAME)"; \
	echo "  Region: $(AWS_REGION)"; \
	echo "  Instance Type: $(INSTANCE_TYPE)"; \
	echo "  VPC ID: $(VPC_ID)"; \
	echo "  Subnet ID: $(SUBNET_ID)"; \
	echo "  KeyPair: $(KEYPAIR_PUBLIC)"; \
	echo ""; \
	echo "$(YELLOW)Creando stack de CloudFormation...$(NC)"; \
	aws cloudformation create-stack \
		--stack-name $(STACK_NAME) \
		--template-body file://$(CF_TEMPLATE) \
		--parameters \
			ParameterKey=InstanceType,ParameterValue=$(INSTANCE_TYPE) \
			ParameterKey=VpcId,ParameterValue=$(VPC_ID) \
			ParameterKey=SubnetId,ParameterValue=$(SUBNET_ID) \
			ParameterKey=KeyPairPublicKey,ParameterValue="$$PUBLIC_KEY" \
		--capabilities CAPABILITY_NAMED_IAM \
		--region $(AWS_REGION) \
		--tags \
			Key=Project,Value=wp-ec2-backoffice-plugin \
			Key=ManagedBy,Value=Makefile
	@echo ""
	@echo "$(GREEN)✓ Stack creado exitosamente$(NC)"
	@echo ""
	@echo "$(YELLOW)Esperando a que el stack se complete...$(NC)"
	@echo "Esto puede tomar 10-15 minutos (la instancia Windows debe inicializarse)"
	@echo ""
	@aws cloudformation wait stack-create-complete \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION)
	@echo ""
	@echo "$(GREEN)✓✓✓ Despliegue completado exitosamente ✓✓✓$(NC)"
	@echo ""
	@echo "Para ver las credenciales y configuración:"
	@echo "  $(YELLOW)make deploy-outputs$(NC)"

# Muestra el estado del stack
deploy-status:
	@echo "$(YELLOW)Estado del stack de CloudFormation...$(NC)"
	@echo ""
	@aws cloudformation describe-stacks \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION) \
		--query 'Stacks[0].[StackName,StackStatus,StackStatusReason]' \
		--output table
	@echo ""

# Muestra los outputs del stack (credenciales, IPs, etc.)
deploy-outputs:
	@echo "$(YELLOW)Outputs del stack de CloudFormation...$(NC)"
	@echo ""
	@echo "$(GREEN)Información del stack:$(NC)"
	@echo ""
	@aws cloudformation describe-stacks \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION) \
		--query 'Stacks[0].Outputs' \
		--output table
	@echo ""
	@echo "$(YELLOW)═══════════════════════════════════════════════════════════$(NC)"
	@echo "$(GREEN)Configuración para el plugin de WordPress:$(NC)"
	@echo "$(YELLOW)═══════════════════════════════════════════════════════════$(NC)"
	@echo ""
	@echo "  $(GREEN)1. Región de AWS:$(NC)"
	@echo "    $(AWS_REGION)"
	@echo ""
	@echo "  $(GREEN)2. Instance ID:$(NC)"
	@aws cloudformation describe-stacks \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION) \
		--query 'Stacks[0].Outputs[?OutputKey==`InstanceId`].OutputValue' \
		--output text | sed 's/^/    /'
	@echo ""
	@echo "  $(GREEN)3. Obtener credenciales desde Secrets Manager:$(NC)"
	@echo ""
	@SECRET_NAME=$$(aws cloudformation describe-stacks \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION) \
		--query 'Stacks[0].Outputs[?OutputKey==`SecretName`].OutputValue' \
		--output text); \
	echo "    $(YELLOW)Comando:$(NC)"; \
	echo "    aws secretsmanager get-secret-value --secret-id $$SECRET_NAME --region $(AWS_REGION) --query SecretString --output text | jq ."; \
	echo ""; \
	echo "    $(YELLOW)Ejecutando...$(NC)"; \
	aws secretsmanager get-secret-value \
		--secret-id $$SECRET_NAME \
		--region $(AWS_REGION) \
		--query SecretString \
		--output text | jq -r 'to_entries | .[] | "    \(.key): \(.value)"'
	@echo ""
	@echo "  $(GREEN)4. Obtener contraseña de Windows:$(NC)"
	@echo ""
	@INSTANCE_ID=$$(aws cloudformation describe-stacks \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION) \
		--query 'Stacks[0].Outputs[?OutputKey==`InstanceId`].OutputValue' \
		--output text); \
	SECRET_NAME=$$(aws cloudformation describe-stacks \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION) \
		--query 'Stacks[0].Outputs[?OutputKey==`SecretName`].OutputValue' \
		--output text); \
	echo "    $(YELLOW)Obteniendo clave privada del KeyPair...$(NC)"; \
	PRIVATE_KEY=$$(aws secretsmanager get-secret-value \
		--secret-id $$SECRET_NAME \
		--region $(AWS_REGION) \
		--query SecretString \
		--output text | jq -r '.KeyPairPrivateKey'); \
	echo "$$PRIVATE_KEY" > /tmp/keypair-$${STACK_NAME}.pem; \
	chmod 600 /tmp/keypair-$${STACK_NAME}.pem; \
	echo "    $(YELLOW)Descifrando contraseña de Windows...$(NC)"; \
	PASSWORD=$$(aws ec2 get-password-data \
		--instance-id $$INSTANCE_ID \
		--priv-launch-key /tmp/keypair-$${STACK_NAME}.pem \
		--region $(AWS_REGION) \
		--query PasswordData \
		--output text 2>/dev/null || echo "Aún no disponible - espera 5-10 minutos después del inicio"); \
	echo "    Contraseña: $$PASSWORD"; \
	rm -f /tmp/keypair-$${STACK_NAME}.pem
	@echo ""
	@echo "$(YELLOW)═══════════════════════════════════════════════════════════$(NC)"
	@echo ""
	@echo "$(GREEN)Notas:$(NC)"
	@echo "  - La contraseña de Windows puede tardar 5-10 minutos en estar disponible"
	@echo "  - Todas las credenciales están almacenadas en Secrets Manager"
	@echo "  - La clave privada del KeyPair está en el secret (no la expongas)"
	@echo ""

# Elimina el stack de CloudFormation
deploy-delete:
	@echo "$(RED)¿Estás seguro de que quieres eliminar el stack $(STACK_NAME)?$(NC)"
	@echo "$(YELLOW)Esto eliminará:$(NC)"
	@echo "  - La instancia EC2 y todos sus datos"
	@echo "  - El usuario IAM y sus credenciales"
	@echo "  - El grupo de seguridad"
	@echo "  - Todos los recursos asociados"
	@echo ""
	@read -p "Escribe 'yes' para confirmar: " confirm; \
	if [ "$$confirm" != "yes" ]; then \
		echo "$(YELLOW)Operación cancelada$(NC)"; \
		exit 1; \
	fi
	@echo ""
	@echo "$(YELLOW)Eliminando stack...$(NC)"
	@aws cloudformation delete-stack \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION)
	@echo ""
	@echo "$(YELLOW)Esperando a que el stack se elimine...$(NC)"
	@aws cloudformation wait stack-delete-complete \
		--stack-name $(STACK_NAME) \
		--region $(AWS_REGION)
	@echo ""
	@echo "$(GREEN)✓ Stack eliminado exitosamente$(NC)"

