# Sistema de Vigilancia — Control de Accesos

Base técnica de la **Fase 1** para una plataforma multiempresa de vigilancia. PHP 8.1+, MySQL/MariaDB, HTML5, CSS3 y JavaScript vanilla; sin frameworks, Node, npm ni compilación.

## Incluido

- Arquitectura PSR-4, entorno y configuración por ambiente.
- PDO seguro, router ligero, contrato JSON y manejador global de errores.
- Sesión segura, CSRF, validadores y logs fuera de public.
- Migraciones, seeds idempotentes e instalador web bloqueable.
- Catálogos de cinco roles y permisos base.
- Dashboard responsive generado a partir de Google Stitch, tema claro/oscuro/automático y navegación adaptable.
- Health check, OpenAPI 3.0, Swagger UI y páginas 403/404/500.
- PHPUnit y documentación inicial para XAMPP/cPanel.

## Requisitos

PHP 8.1+, pdo_mysql, json, mbstring, Apache con mod_rewrite/mod_headers, MySQL 5.7+ o MariaDB 10.4+ y Composer 2.

## Instalación rápida en XAMPP

    cd C:\xampp-8.1\htdocs\control-de-accesos
    C:\xampp-8.1\php\php.exe scripts\check_requirements.php
    composer install

Abre http://localhost/control-de-accesos/public/install/. El asistente crea la base si el usuario de MySQL tiene permiso, escribe .env, ejecuta migraciones/seeds, crea empresa y superadministrador y genera storage/installed.lock.

## Consola

    C:\xampp-8.1\php\php.exe scripts\migrate.php
    C:\xampp-8.1\php\php.exe scripts\seed.php
    C:\xampp-8.1\php\php.exe scripts\seed.php --demo
    vendor\bin\phpunit

## Endpoints

- GET /control-de-accesos/public/api/
- GET /control-de-accesos/public/api/health
- GET /control-de-accesos/public/docs/
- GET|POST /control-de-accesos/public/install/

Las respuestas usan {success,message,data} o {success,message,errors}. Fechas internas en UTC; zona inicial America/Mexico_City.

## Seguridad

.env, logs, evidencia y temporales se excluyen de Git. PDO desactiva consultas emuladas. El instalador valida contraseña robusta, usa CSRF, hashes con password_hash y nunca conserva contraseñas en texto plano en la base. En producción APP_DEBUG=false y el DocumentRoot debe apuntar a public.

## Estructura

app contiene capas; bootstrap inicia la aplicación; config expone configuración; database contiene migraciones y seeds; public es el único directorio público; routes define API; scripts contiene runners; storage conserva datos no públicos; tests contiene PHPUnit; docs mantiene OpenAPI y manuales.

## Alcance

No se implementaron autenticación, clientes, lugares, vigilantes, visitas, eventos, supervisiones, reportes ni procesos Cron funcionales. Son Fases 2 a 12 y requieren aprobación expresa.
