# Arquitectura inicial

La Fase 1 usa PHP 8.1 sin framework y una SPA ligera en HTML, CSS y módulos ES. La petición entra por Apache, se dirige al front controller y pasa al router. Los controladores coordinan servicios; los servicios usan PDO, migraciones y seeds. La presentación no contiene lógica de negocio.

## Capas

- **Http:** petición, router y respuestas JSON.
- **Controllers:** adaptadores de entrada.
- **Services:** casos de uso, incluido el instalador.
- **Database:** conexión, migraciones y seeds.
- **Validation/Middleware:** validación reutilizable y CSRF.
- **Support:** entorno, sesión y logging.

Los módulos de clientes, vigilancia, visitas, eventos y reportes se reservan para sus fases correspondientes.

## Datos fundacionales

La migración 001 crea: migrations, system_settings, security_logs, users, user_sessions, roles, permissions, role_permissions, user_permissions, surveillance_companies e installer_logs. user_permissions permite excepciones explícitas sobre el rol base y evita rediseñar autorización en la Fase 2.
