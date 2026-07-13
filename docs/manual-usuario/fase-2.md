# Fase 2 — Acceso, permisos y módulos

El menú lateral se construye con los permisos efectivos de la sesión. Si el usuario no cuenta con ninguna acción de un módulo, ese módulo no se muestra y escribir su identificador manualmente en la URL no permite abrirlo.

## Módulos predeterminados por rol

- **Superadministrador:** todos los módulos, matriz de permisos, configuración, auditoría y mantenimiento.
- **Administrador:** clientes, lugares, usuarios, turnos, operación, asistencias, visitas, proveedores, eventos, recorridos, reportes y configuración.
- **Supervisor:** usuarios asignados, turnos, operación, asistencias, visitas, proveedores, eventos, recorridos, supervisiones, sincronización y reportes.
- **Vigilante:** turnos, operación, asistencia propia, visitas, proveedores, eventos, recorridos y actividad propia.
- **Residente:** visitas propias, accesos propios de proveedores e historial propio.

La matriz **Permisos** permite al Superadministrador modificar estas asignaciones. Los cambios se aplican al volver a cargar la sesión y no se restablecen al ejecutar nuevamente las semillas.

Las áreas funcionales de fases posteriores aparecen como módulos autorizados y señalan la fase en la que se implementará su operación. Esto permite validar desde ahora la navegación y separación por rol sin adelantar lógica de negocio.
