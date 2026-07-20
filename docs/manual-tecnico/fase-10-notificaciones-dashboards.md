# Fase 10: notificaciones y dashboards

La migración `013_notifications_dashboards` crea `notifications`, los permisos `notifications.view` y `dashboards.view`, retención predeterminada de 12 meses y actualización de dashboard cada 60 segundos.

Cada aviso pertenece a un usuario, conserva tipo, prioridad, vínculo relacionado, fecha de lectura y vencimiento. La restricción única por usuario y `deduplication_key` evita duplicados. Al consultar el centro se materializan de forma idempotente avisos de sesiones, retardos, eventos, comentarios, recorridos, supervisiones y visitas dentro del alcance del destinatario.

Los dashboards consultan directamente las entidades operativas. Superadministrador, administrador y supervisor reciben indicadores agregados dentro de sus lugares; vigilante y residente reciben solamente métricas propias. Los filtros nunca amplían el alcance concedido en backend.

El vigilante usa endpoints separados bajo `/guard`, vinculados a la sesión operativa activa. Esto evita habilitar su acceso al login administrativo.
