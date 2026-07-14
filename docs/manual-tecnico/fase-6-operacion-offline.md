# Fase 6: operación sin conexión

## Arquitectura

El navegador registra un Service Worker y conserva una cola `vigilancia-offline` en IndexedDB. Al abrir una sesión operativa en línea, el servidor autoriza el dispositivo durante 24 horas y entrega un token aleatorio; en la base solamente se almacena su hash SHA-256.

La cola acepta `entry`, `exit`, `round_start`, `round_end`, `event`, `evidence` y `comment`. Las visitas quedan excluidas deliberadamente: su validación siempre requiere conexión con la API.

## Sincronización

- Endpoint: `POST /api/offline/sync` con encabezado `X-Offline-Token`.
- Máximo de 50 operaciones por lote.
- Cada registro tiene UUID único para que un reintento sea idempotente.
- Al recuperar la conexión se sincroniza automáticamente; el vigilante también dispone de un botón manual.
- Una operación con más de 12 horas queda `expired`; no se elimina.
- Una segunda operación con la misma clave de entidad conserva ambas y las marca `conflict`.
- La evidencia local se borra solamente cuando el servidor devuelve `delete_local_evidence=true`.

## Revisión

Supervisores con `offline_conflicts.manage` ven el módulo Sincronización. `GET /api/offline/conflicts` respeta empresa y lugares asignados. `POST /api/offline/review` acepta o rechaza el registro con un comentario obligatorio de 10 a 500 caracteres y genera auditoría.

## Base de datos

La migración `008_offline_sync` crea `offline_devices` y `offline_operations`, registra `offline_operations.capture` y lo asigna a Vigilante y Superadministrador. Los archivos de evidencia se guardan fuera de `public`, bajo `storage/offline`.

## Despliegue

Ejecutar como administrador:

    powershell -ExecutionPolicy Bypass -File "C:\Users\PC\Documents\Codex\2026-07-13\es\outputs\aplicar-fase6.ps1"

El script copia la versión canónica, aplica migraciones y ejecuta PHPUnit.
