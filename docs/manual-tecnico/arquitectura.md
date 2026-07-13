# Arquitectura inicial

La Fase 1 usa PHP 8.1 sin framework y una SPA ligera en HTML, CSS y módulos ES. La petición entra por Apache, se dirige al front controller y pasa al router. Los controladores coordinan servicios; los servicios usan PDO, migraciones y seeds. La presentación no contiene lógica de negocio.

## Capas

- **Http:** petición, router y respuestas JSON.
- **Controllers:** adaptadores de entrada.
- **Services:** casos de uso, incluido el instalador.
- **Database:** conexión, migraciones y seeds.
- **Validation/Middleware:** validación reutilizable y CSRF.
- **Support:** entorno, sesión y logging.

La Fase 3 agrega `OrganizationController`, `OrganizationService`, `ScopeService` y `OrganizationRepository`. Esta cadena aplica autorización y alcance antes de consultar o modificar clientes, lugares, puntos, unidades y residentes. Los módulos de vigilancia, visitas, eventos y reportes se reservan para sus fases correspondientes.

## Datos fundacionales

La migración 001 crea: migrations, system_settings, security_logs, users, user_sessions, roles, permissions, role_permissions, user_permissions, surveillance_companies e installer_logs. user_permissions permite excepciones explícitas sobre el rol base y evita rediseñar autorización en la Fase 2.

La migración 003 incorpora el árbol organizacional y tablas de alcance. Las relaciones `user_client_scopes`, `user_location_scopes`, `user_access_point_scopes` y `resident_units` delimitan las consultas sin depender de filtros enviados por el navegador.
