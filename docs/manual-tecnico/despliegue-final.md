# Despliegue final

## Validación previa

En XAMPP ejecuta:

```powershell
C:\xampp-8.1\php\php.exe scripts\migrate.php
C:\xampp-8.1\php\php.exe scripts\seed.php --demo
C:\xampp-8.1\php\php.exe vendor\bin\phpunit --do-not-cache-result
C:\xampp-8.1\php\php.exe scripts\release_check.php
```

No publiques si existe un error. Una advertencia de Cron es normal mientras el proyecto permanezca en local.

## cPanel

1. Selecciona PHP 8.1 o superior y habilita PDO MySQL, JSON, Mbstring y Fileinfo.
2. Coloca el proyecto fuera de `public_html` cuando el hosting lo permita y apunta el dominio a `public`.
3. Si no puedes cambiar el DocumentRoot, conserva `.htaccess` en la raíz y verifica que `storage`, `.env`, `vendor` y `database` no sean accesibles por URL.
4. Ejecuta `composer install --no-dev --optimize-autoloader` o sube `vendor` desde un entorno con la misma versión de PHP.
5. Configura `.env` con `APP_ENV=production`, `APP_DEBUG=false`, una URL HTTPS y credenciales limitadas de MySQL.
6. Otorga escritura únicamente a `storage` y ejecuta migraciones.
7. Programa el Cron y ejecuta `scripts/release_check.php` nuevamente.

Cron recomendado cada cinco minutos:

```text
/usr/local/bin/php /home/USUARIO/ruta/control-de-accesos/cron/run.php
```

El ejecutor tiene bloqueo de concurrencia e idempotencia. Revisa el módulo Mantenimiento para resultado, duración, error y próxima ejecución esperada.

## Actualización

Realiza copia de la base y de `storage`, activa una ventana de mantenimiento del hosting, sustituye archivos sin borrar `.env`, `storage` ni `vendor`, ejecuta migraciones y pruebas, limpia caché del navegador y valida login, API, reportes y Cron. Para revertir, restaura archivos y copia de base; no uses comandos destructivos de Git sobre producción.
