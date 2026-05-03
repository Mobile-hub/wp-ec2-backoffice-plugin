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

## Configuración

### Configuración Inicial

1. En el panel de administración de WordPress, ve a **EC2 Backoffice** en el menú lateral
2. Haz clic en la pestaña **Configuración**
3. Completa los siguientes campos con los valores obtenidos del despliegue de CloudFormation:

   - **Región de AWS**: La región donde desplegaste la infraestructura (ej: `us-east-1`)
   - **Access Key ID**: El Access Key ID del usuario IAM creado
   - **Secret Access Key**: El Secret Access Key del usuario IAM
   - **Instance ID**: El ID de la instancia EC2 (ej: `i-1234567890abcdef0`)
   - **Contraseña de Windows**: La contraseña del usuario Administrator de Windows

4. Haz clic en **Guardar Configuración**
5. Haz clic en **Probar Conexión** para verificar que las credenciales son correctas

### Obtener los Valores de CloudFormation

Después de desplegar el stack de CloudFormation, puedes obtener los valores necesarios:

```bash
aws cloudformation describe-stacks \
  --stack-name wp-ec2-backoffice \
  --query 'Stacks[0].Outputs'
```

O desde la consola de AWS:
1. Ve a **CloudFormation** → **Stacks**
2. Selecciona tu stack
3. Haz clic en la pestaña **Outputs**

## Uso

### Iniciar la Instancia

1. Ve a **EC2 Backoffice** en el menú de administración de WordPress
2. En la pestaña **Acceso y Control**, verás el estado actual de la instancia
3. Haz clic en **Iniciar Instancia**
4. El plugin automáticamente:
   - Detectará tu dirección IP pública actual
   - Actualizará las reglas del grupo de seguridad para permitir acceso RDP desde tu IP
   - Iniciará la instancia EC2
5. Espera a que el estado cambie a **running** (esto puede tomar 1-2 minutos)

### Conectarse por RDP

Una vez que la instancia esté en estado **running**:

1. Haz clic en **Descargar Archivo RDP**
2. Abre el archivo descargado con tu cliente de Escritorio Remoto
3. Cuando se te solicite, usa las siguientes credenciales:
   - **Usuario**: `Administrator`
   - **Contraseña**: La contraseña mostrada en la sección "Contraseña de Windows" (puedes copiarla con el botón de copiar)

### Detener la Instancia

Cuando termines de usar la instancia:

1. Cierra la sesión de Escritorio Remoto
2. En el panel de WordPress, haz clic en **Detener Instancia**
3. La instancia se detendrá y dejarás de incurrir en costos de cómputo

**Nota**: La instancia también se detendrá automáticamente después de 2 horas sin sesiones RDP activas, gracias al script de auto-apagado.

### Monitoreo del Estado

El plugin actualiza automáticamente el estado de la instancia cada 10 segundos mientras la página está abierta. Los estados posibles son:

- **stopped**: La instancia está detenida (no genera costos de cómputo)
- **pending**: La instancia se está iniciando
- **running**: La instancia está activa y lista para conexión RDP
- **stopping**: La instancia se está deteniendo
- **terminated**: La instancia ha sido terminada (no debería ocurrir en uso normal)

## Troubleshooting

### Error: "No se pudo determinar la IP actual"

**Causa**: El plugin no puede detectar tu dirección IP pública.

**Solución**:
- Verifica que tu servidor WordPress tenga acceso a internet
- Si estás detrás de un proxy o firewall corporativo, contacta a tu administrador de red
- Verifica que las variables `$_SERVER['REMOTE_ADDR']` o `$_SERVER['HTTP_X_FORWARDED_FOR']` estén disponibles

### Error: "Credenciales de AWS inválidas"

**Causa**: El Access Key ID o Secret Access Key son incorrectos.

**Solución**:
1. Ve a la pestaña **Configuración**
2. Verifica que hayas copiado correctamente las credenciales del output de CloudFormation
3. Asegúrate de no haber incluido espacios adicionales al copiar
4. Haz clic en **Probar Conexión** para verificar

### Error: "No tienes permisos suficientes"

**Causa**: El usuario IAM no tiene los permisos necesarios.

**Solución**:
- Verifica que el stack de CloudFormation se haya desplegado correctamente
- Revisa que las políticas IAM estén adjuntas al usuario
- Consulta la [documentación de infraestructura](infrastructure/README.md)

### La instancia no inicia

**Causa**: Puede haber varios motivos.

**Solución**:
1. Verifica el estado de la instancia en la consola de AWS EC2
2. Revisa los logs de CloudWatch para la instancia
3. Asegúrate de que la instancia no esté en estado `terminated`
4. Verifica que no hayas alcanzado límites de servicio de AWS

### No puedo conectarme por RDP

**Causa**: Las reglas del grupo de seguridad pueden no estar configuradas correctamente.

**Solución**:
1. Verifica que tu IP actual sea la misma desde la que intentas conectarte
2. Si tu IP cambió (ej: conexión móvil), inicia la instancia nuevamente desde el plugin
3. Verifica en la consola de AWS que el grupo de seguridad tenga una regla para el puerto 3389 desde tu IP
4. Asegúrate de que tu firewall local permita conexiones RDP salientes

### Error: "Las dependencias no están instaladas"

**Causa**: El AWS SDK para PHP no está instalado.

**Solución**:
```bash
cd /path/to/wordpress/wp-content/plugins/wp-ec2-backoffice-plugin
composer install --no-dev
```

### La instancia no se detiene automáticamente

**Causa**: El script de auto-apagado puede no estar funcionando correctamente.

**Solución**:
1. Conéctate a la instancia por RDP
2. Abre el Visor de Eventos de Windows (Event Viewer)
3. Ve a **Registros de Windows** → **Aplicación**
4. Busca eventos con origen "WP-EC2-AutoShutdown"
5. Verifica que el script se esté ejecutando cada 15 minutos
6. Si no hay eventos, verifica que la tarea programada esté configurada correctamente en el Programador de Tareas de Windows

## Seguridad

### Mejores Prácticas

1. **Usa HTTPS**: Siempre accede al panel de WordPress a través de HTTPS para proteger las credenciales en tránsito
2. **Limita el acceso**: Solo otorga acceso al plugin a usuarios administradores de confianza
3. **Rota credenciales**: Cambia periódicamente las credenciales del usuario IAM
4. **Monitorea el uso**: Revisa regularmente los logs de CloudWatch para detectar actividad inusual
5. **Mantén actualizado**: Actualiza el plugin y WordPress regularmente

### Almacenamiento de Credenciales

- El Secret Access Key se almacena encriptado en la base de datos de WordPress usando AES-256-CBC
- La clave de encriptación se deriva de las sales de WordPress (`wp_salt()`)
- Las credenciales nunca se registran en logs ni se muestran en mensajes de error

### Acceso a la Instancia

- Solo tu IP actual puede acceder a la instancia por RDP
- Las reglas del grupo de seguridad se actualizan automáticamente cada vez que inicias la instancia
- Las reglas antiguas se eliminan automáticamente para evitar acumulación de IPs permitidas

## Infraestructura

Ver [infrastructure/README.md](infrastructure/README.md) para instrucciones de despliegue de la infraestructura AWS.

## Desarrollo

### Estructura del Proyecto

```
wp-ec2-backoffice-plugin/
├── wp-ec2-backoffice-plugin.php  # Archivo principal del plugin
├── composer.json                  # Dependencias
├── includes/                      # Clases PHP
├── admin/                         # Assets de administración
├── languages/                     # Archivos de traducción
├── infrastructure/                # Templates de CloudFormation
└── tests/                         # Tests unitarios y de propiedades
```

### Ejecutar Tests

```bash
composer test
```

## Licencia

Este proyecto está licenciado bajo la Licencia MIT. Ver el archivo [LICENSE](LICENSE) para más detalles.

## Contribuir

Las contribuciones son bienvenidas. Por favor, abre un issue o pull request en GitHub.

## Soporte

Para reportar bugs o solicitar funcionalidades, por favor abre un issue en GitHub.

## Agradecimientos

- AWS SDK for PHP
- WordPress Plugin API
