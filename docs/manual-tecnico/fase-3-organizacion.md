# Fase 3 — Organización y alcances

La migración `003_organization` incorpora clientes, lugares, puntos de acceso, unidades, perfiles de residente y relaciones de alcance. Los registros principales usan desactivación lógica mediante `is_active`; no se eliminan físicamente.

## Aislamiento

- El Superadministrador opera sobre toda su empresa de vigilancia.
- El Administrador consulta clientes asignados en `user_client_scopes` y lugares asignados explícitamente en `user_location_scopes`.
- El Supervisor consulta exclusivamente lugares de `user_location_scopes` y sus puntos/unidades descendientes.
- El Residente consulta exclusivamente unidades activas de `resident_units`.
- `user_access_point_scopes` queda preparado para limitar la sesión operativa del Vigilante en la Fase 5.

Cada servicio comprueba permiso y alcance antes de leer o escribir. La interfaz solo muestra módulos autorizados, pero la API constituye la barrera de seguridad definitiva. Creaciones y cambios de estado se registran en `security_logs`.

## Datos demo

Ejecutar `php scripts/seed.php --demo` crea idempotentemente un cliente, dos lugares, dos puntos, tres unidades y asigna alcances a las cuentas demo existentes.
