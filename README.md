# KND Store

Tienda digital de servicios y productos tecnológicos con temática gamer y cósmica.

## Requisitos

- PHP 7.4 o superior
- MySQL/MariaDB
- Servidor web (Apache/Nginx) con mod_rewrite habilitado
- Extensiones PHP: PDO, PDO_MySQL, mbstring, json

## Instalación y Deployment

### 1. Clonar el repositorio

```bash
git clone <repository-url>
cd kndstore
```

### 2. Crear archivo de configuración

**IMPORTANTE**: El archivo `includes/config.php` **NO está versionado** y debe crearse en el servidor.

Crea `includes/config.php` con la siguiente estructura mínima:

```php
<?php
// KND Store - Configuración Principal

// Configuración de errores
$serverName = $_SERVER['SERVER_NAME'] ?? '';
$isLocal = in_array($serverName, ['localhost', '127.0.0.1']);

error_reporting(E_ALL);

if ($isLocal) {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-error.log');
}

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de base de datos
define('DB_HOST', 'tu_host');
define('DB_NAME', 'tu_base_de_datos');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
define('DB_CHARSET', 'utf8mb4');

// Configuración del sitio
define('SITE_NAME', 'KND Store');
define('SITE_URL', 'https://tudominio.com');
define('SITE_EMAIL', 'support@tudominio.com');

// Sistema de internacionalización (i18n)
require_once __DIR__ . '/i18n.php';

// Headers de seguridad
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 0');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ... (resto de funciones y utilidades)
```

**Nota**: Puedes usar `includes/config.php` del repositorio como referencia, pero asegúrate de actualizar las credenciales de base de datos y configuración del sitio.

### 3. Configurar secretos (opcional)

Si usas la API de Death Roll, copia `includes/secrets.local.php.example` a `includes/secrets.local.php` y configura:

```php
define('DEATHROLL_HMAC_SECRET', 'tu_secret_generado');
```

Genera un secret seguro con:
```bash
openssl rand -hex 32
```

### 4. Permisos de directorios

Asegúrate de que el servidor web tenga permisos de escritura en:
- `logs/` (si se usa logging de errores)

### 5. Configuración del servidor web

#### Apache (.htaccess ya incluido)

El archivo `.htaccess` ya está configurado con:
- Redirección HTTP a HTTPS
- Headers de seguridad
- Configuración de cache
- Rewrite rules

#### Nginx

Configura las reglas de rewrite equivalentes y headers de seguridad.

### 6. Base de datos

Importa los esquemas SQL desde `sql/` si es necesario:
- `apparel_services_migration.sql`
- `deathroll_setup.sql`

## Estructura del proyecto

```
kndstore/
├── includes/
│   ├── config.php              # NO VERSIONADO - Crear en servidor
│   ├── secrets.local.php       # NO VERSIONADO - Opcional
│   ├── header.php              # Header común
│   ├── footer.php              # Footer común
│   ├── i18n.php                # Sistema de internacionalización
│   ├── session.php             # Gestión de sesiones
│   └── lang/                   # Diccionarios de traducción
│       ├── es.php
│       └── en.php
├── assets/
│   ├── css/                    # Estilos
│   ├── js/                     # Scripts JavaScript
│   └── images/                 # Imágenes
├── api/                        # Endpoints API
├── sql/                       # Scripts SQL
└── *.php                       # Páginas principales
```

## Características

- Sistema de internacionalización (ES/EN)
- Panel de personalización de colores
- Gestión de pedidos
- API Death Roll
- Diseño responsive
- PWA ready

## Troubleshooting

### Error 500 - Pantalla en blanco

Si ves un error 500 o pantalla en blanco, verifica:

1. **`includes/config.php` existe**: El archivo debe estar presente en el servidor
2. **Permisos de archivos**: El servidor web debe tener permisos de lectura
3. **Logs de errores**: Revisa `logs/php-error.log` (si está configurado)
4. **Extensiones PHP**: Verifica que todas las extensiones requeridas estén instaladas

### Mensaje: "Missing includes/config.php on server"

Este mensaje aparece cuando `includes/config.php` no existe. Crea el archivo siguiendo las instrucciones de la sección "Crear archivo de configuración".

## Desarrollo

Para desarrollo local:

1. Configura un servidor local (XAMPP, WAMP, Laragon, etc.)
2. Crea `includes/config.php` con configuración local
3. Asegúrate de que `$isLocal` detecte correctamente el entorno local

## Licencia

[Especificar licencia]

## Soporte

Para soporte técnico, contacta: support@kndstore.com
