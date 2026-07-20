# Sistema de Vigilancia — Control de Accesos

Base técnica, autenticación, organización multiempresa, personal y operación de las **Fases 1 a 9** para una plataforma de vigilancia. PHP 8.1+, MySQL/MariaDB, HTML5, CSS3 y JavaScript vanilla; sin frameworks, Node, npm ni compilación.

La Fase 9 incorpora programación, ejecución, confirmación y reportes PDF de supervisiones. Consulte [la guía técnica](docs/manual-tecnico/fase-9-supervisiones.md) y [el manual de usuario](docs/manual-usuario/fase-9.md). La validación en cPanel de la Fase 6 permanece pendiente por decisión operativa.

## Incluido

- Arquitectura PSR-4, entorno y configuración por ambiente.
- PDO seguro, router ligero, contrato JSON y manejador global de errores.
- Sesión segura, CSRF, validadores y logs fuera de public.
- Migraciones, seeds idempotentes e instalador web bloqueable.
- Catálogos de cinco roles y permisos base.
- Dashboard responsive generado a partir de Google Stitch, tema claro/oscuro/automático y navegación adaptable.
- Health check, OpenAPI 3.0, Swagger UI y páginas 403/404/500.
- PHPUnit y documentación inicial para XAMPP/cPanel.
- Inicio y cierre de sesión, tokens almacenados mediante hash y expiración máxima de 24 horas.
- Bloqueo progresivo desde el quinto fallo, permisos efectivos en backend y auditoría de seguridad.
- Cambio de contraseña, revocación de sesiones y preferencia de tema por usuario.
- Clientes, lugares, puntos de acceso, unidades y residentes con baja lógica.
- Alcances aislados por cliente, lugar, punto y unidad, aplicados en backend.
- Paneles SPA de Fase 3 y datos demo idempotentes.
- Acceso operativo responsive, captura de cámara, datos del dispositivo y cierre mediante QR.
- Asistencias puntuales, retardos, fuera de horario, salidas anticipadas, tiempo extra y turnos incompletos.
- Dispositivo offline preautorizado por 24 horas, cola idempotente y sincronización en lotes de 50.
- Conservación de conflictos y operaciones con más de 12 horas para revisión supervisada.
- Evidencias locales eliminadas solamente después de una confirmación positiva del servidor.
- Visitas con vigencia configurable, límite de QR activos, edición/cancelación previa y detección de duplicados.
- Entrada y salida de visitantes con el mismo QR, fotografías directas de cámara y privacidad auditable.
- Accesos de proveedores preautorizados o registrados por el vigilante sin QR.
- Incidencias con tipo configurable, título, comentario inicial, prioridad escalable, evidencias y cancelación auditada.
- Fotografías y videos de hasta 30 segundos capturados directamente, con almacenamiento privado y endpoint protegido.
- Recorridos programados o libres, sin QR de ruta, con evidencia y observaciones obligatorias.
- Novedad obligatoria antes del cierre y consulta de entregas anteriores en el punto.
- Supervisiones manuales, por turno, diarias, semanales o mensuales sobre uno o varios puntos.
- Consulta contextual del vigilante presente, eventos y recorridos durante la supervisión.
- Evidencia fotográfica privada, doble confirmación por nombre y PIN y motivo de ausencia obligatorio.
- Supervisiones finalizadas inmutables, comentarios posteriores y PDF protegido con folio auditable.

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

Las Fases 1 a 9 están implementadas. Notificaciones, dashboards, reportes generales, auditoría, almacenamiento y Cron corresponden a las Fases 10 a 12.
