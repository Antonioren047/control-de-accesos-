# Instalación en cPanel

Configura PHP 8.1 o superior con PDO MySQL, JSON, Mbstring y Fileinfo. El DocumentRoot recomendado es `public`; `.env`, `storage`, migraciones y código PHP de aplicación deben permanecer fuera del alcance web directo.

Instala dependencias con `composer install --no-dev --optimize-autoloader`, configura `.env` con `APP_ENV=production`, `APP_DEBUG=false` y `APP_URL` HTTPS, y otorga escritura permanente solamente a `storage`.

Después de subir el proyecto ejecuta:

```text
php scripts/migrate.php
php scripts/release_check.php
```

Configura Cron cada cinco minutos:

```text
/usr/local/bin/php /home/USUARIO/ruta/control-de-accesos/cron/run.php
```

Consulta [despliegue-final.md](despliegue-final.md) para copias de seguridad, actualización, reversión y validación. La operación offline y las cámaras deben probarse finalmente en el dominio HTTPS real.
