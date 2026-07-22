# Manual de instalación en cPanel

Este paquete instala el Sistema de Vigilancia en modo producción. No contiene información de prueba y el asistente crea únicamente una empresa y un usuario global con el rol interno `superadmin`.

## 1. Requisitos del servidor

- PHP 8.1 o superior (se recomienda 8.2).
- MySQL 5.7+ o MariaDB 10.4+.
- Extensiones PHP: `pdo_mysql`, `json`, `mbstring`, `dom`, `fileinfo` y `openssl`.
- Apache con `mod_rewrite` habilitado y soporte para archivos `.htaccess`.
- Certificado HTTPS activo, indispensable para cámara, QR y funciones offline del navegador.
- Permiso de escritura para `.env` y para todo el directorio `storage`.

El ZIP ya incluye las dependencias PHP de producción. No es necesario ejecutar Composer en el servidor.

## 2. Crear la base de datos

En **cPanel > Bases de datos MySQL**:

1. Crea una base de datos nueva y vacía.
2. Crea un usuario MySQL con una contraseña robusta.
3. Asocia el usuario a la base y activa **Todos los privilegios**.
4. Conserva el nombre completo que muestra cPanel. Normalmente tendrá el prefijo de la cuenta, por ejemplo `cuenta_vigilancia` y `cuenta_appuser`.

No reutilices la base local ni importes datos de demostración. El instalador rechazará cualquier base que ya contenga tablas.

## 3. Subir y extraer el paquete

1. Abre **cPanel > Administrador de archivos**.
2. Crea el directorio de la aplicación, por ejemplo `/home/USUARIO/control-de-accesos`.
3. Sube el archivo ZIP y extráelo dentro de ese directorio.
4. Confirma que en la raíz extraída existan `app`, `bootstrap`, `database`, `public`, `storage`, `vendor`, `.htaccess` e `index.php`.
5. Elimina el ZIP del servidor después de extraerlo.

### DocumentRoot recomendado

Crea un dominio o subdominio, por ejemplo `seguridad.tudominio.com`, y apunta su **DocumentRoot** a:

```text
/home/USUARIO/control-de-accesos/public
```

Es la configuración más segura porque `.env`, logs y evidencias quedan fuera del directorio público. Si el proveedor no permite cambiar el DocumentRoot, instala el proyecto en un subdirectorio de `public_html`; el `.htaccess` raíz enviará las solicitudes a `public`, aunque la URL incluirá `/public`.

## 4. Seleccionar PHP y permisos

En **MultiPHP Manager** selecciona PHP 8.1 o superior para el dominio. En **Select PHP Version** verifica las extensiones indicadas en la sección de requisitos.

Permisos habituales:

```text
Directorios: 755
Archivos: 644
storage y sus subdirectorios: 775
```

No uses `777` de forma permanente. Si el instalador marca `.env escribible`, permite temporalmente escritura en la raíz o crea manualmente `.env` a partir de `.env.example`; después déjalo en `600` o `640`, según la configuración del hosting.

## 5. Ejecutar el instalador

Con DocumentRoot apuntando a `public`, abre:

```text
https://seguridad.tudominio.com/install/
```

Completa:

- Host MySQL: normalmente `localhost`.
- Puerto: normalmente `3306`.
- Nombre completo de la base vacía.
- Usuario y contraseña MySQL.
- Nombre de la empresa de vigilancia.
- Nombre, correo y contraseña del usuario global.
- Zona horaria.
- URL pública HTTPS exacta, sin `/install` y sin diagonal final.

La contraseña global exige al menos 12 caracteres e incluye mayúscula, minúscula, número y símbolo. Al finalizar:

- se ejecutan todas las migraciones;
- se crean solo roles, permisos y catálogos indispensables;
- se crea una empresa;
- se crea exactamente un usuario global;
- no se crean clientes, lugares, residentes, vigilantes ni registros operativos;
- se genera `.env` con `APP_ENV=production` y `APP_DEBUG=false`;
- se crea `storage/installed.lock` para bloquear el instalador.

Guarda las credenciales globales en un gestor de contraseñas. El sistema no puede recuperar la contraseña en texto plano.

## 6. Configurar Cron

En **cPanel > Trabajos Cron**, programa cada cinco minutos:

```cron
*/5 * * * * /usr/local/bin/php -q /home/USUARIO/control-de-accesos/cron/run.php >/dev/null 2>&1
```

Sustituye la ruta por la ruta absoluta real que muestra cPanel. Algunos proveedores usan `php`, `/usr/bin/php` o una ruta versionada como `/opt/cpanel/ea-php81/root/usr/bin/php`.

## 7. Verificación posterior

1. Abre la URL principal e inicia sesión con el usuario global.
2. Comprueba que no existan clientes, lugares, usuarios operativos, turnos ni asignaciones.
3. Crea primero la estructura real: administradores, clientes, lugares, puntos, unidades y usuarios.
4. Prueba en HTTPS cámara, lectura QR, carga de evidencias y cierre de sesión.
5. Verifica que `https://tu-dominio/.env` y `https://tu-dominio/storage/` no sean accesibles.
6. Revisa `storage/logs/app.log` si aparece un error.
7. Configura copias de seguridad de la base y de `storage/evidence` fuera de la cuenta de hosting.

## 8. Problemas frecuentes

### El instalador indica PHP incorrecto

La versión del dominio puede ser distinta a la versión global de cPanel. Selecciona PHP 8.1+ específicamente para el dominio en MultiPHP Manager.

### Error 403

Confirma que el dominio apunte al directorio `public`, que los directorios sean `755`, los archivos `644` y que Apache permita `.htaccess`.

### Error 500 o pantalla en blanco

Revisa `storage/logs/app.log` y el registro **Errors** de cPanel. Verifica extensiones PHP, permisos y que `vendor/autoload.php` exista.

### No se puede conectar a MySQL

Usa los nombres completos con prefijo de cPanel, confirma que el usuario esté asociado a la base y que tenga todos los privilegios. En hosting compartido el host suele ser `localhost`.

### La base debe estar vacía

Crea otra base desde cPanel. No borres tablas de una base productiva ni intentes instalar sobre datos existentes.

### Cámara o lector QR no disponibles

Accede mediante HTTPS, concede permisos de cámara al navegador y prueba con Chrome o Edge actualizado. La cámara suele estar bloqueada en HTTP fuera de `localhost`.

## 9. Actualizaciones futuras

Antes de actualizar:

1. Haz respaldo completo de la base, `.env` y `storage`.
2. No vuelvas a ejecutar el instalador.
3. No sobrescribas `.env`, `storage` ni `storage/installed.lock`.
4. Aplica las migraciones específicas de la versión y valida primero en un entorno de prueba.

Conserva este manual fuera del servidor junto con el ZIP y su archivo SHA-256.
