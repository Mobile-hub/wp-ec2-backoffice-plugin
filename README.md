# WP EC2 Backoffice Plugin

Plugin de WordPress para gestionar una instancia EC2 de Windows desde el área de administración.

## Descripción

Este plugin proporciona una interfaz simplificada para gestionar una instancia EC2 de Windows utilizada como escritorio remoto para la gestión de correo electrónico con Thunderbird. El plugin permite:

- Iniciar y detener la instancia EC2
- Gestionar automáticamente las reglas de seguridad para acceso RDP
- Descargar archivos de conexión RDP
- Ver el estado de la instancia en tiempo real
- Configurar credenciales de AWS de forma segura

## Requisitos Previos

- WordPress 5.0 o superior
- PHP 7.4 o superior con extensión OpenSSL
- Composer para gestión de dependencias
- Cuenta de AWS con permisos apropiados
- Infraestructura AWS desplegada (ver sección de Infraestructura)

## Instalación

TODO: Completar en tarea 23.1

### 1. Clonar el repositorio

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/your-username/wp-ec2-backoffice-plugin.git
```

### 2. Instalar dependencias

```bash
cd wp-ec2-backoffice-plugin
composer install --no-dev
```

### 3. Activar el plugin

```bash
wp plugin activate wp-ec2-backoffice-plugin
```

O actívalo desde el panel de administración de WordPress.

## Configuración

TODO: Completar en tarea 23.1

## Uso

TODO: Completar en tarea 23.1

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

## Autor

Your Name - [your.email@example.com](mailto:your.email@example.com)

## Agradecimientos

- AWS SDK for PHP
- WordPress Plugin API
