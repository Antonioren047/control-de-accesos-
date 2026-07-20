# Fase 11 · Reportes, auditoría, almacenamiento y Cron

La fase incorpora reportes PDF bajo permisos y alcance territorial, con un rango máximo de 90 días. Están disponibles asistencia, retardos, faltas, horas trabajadas, incidencias, eventos, recorridos, visitas, actividad por vigilante, actividad por lugar y supervisiones. Cada generación conserva folio, filtros, usuario y fecha, y cada apertura queda auditada.

La auditoría registra módulo, acción, registro, IP, dispositivo, contexto y valores anteriores/nuevos cuando existen. Superadministración consulta el alcance global y administración el de su empresa. La exportación también es PDF.

El monitor técnico muestra el consumo frente a una cuota predeterminada de 10 GB por cliente. Alcanzar el umbral genera una alerta, pero nunca bloquea evidencia operativa.

## Cron de cPanel

Ejecutar cada cinco minutos:

```text
/usr/local/bin/php /RUTA_DEL_PROYECTO/cron/run.php
```

Las tareas calculan faltas, cierran recorridos vencidos, detectan supervisiones pendientes, expiran QR, controlan cuotas, aplican retención y limpian temporales. El proceso usa bloqueo de base de datos, operaciones idempotentes y conserva resultado, error, duración y próxima ejecución esperada.

Retención inicial: evidencia 12 meses, identificaciones 90 días y aviso 7 días antes. Los valores están en `system_settings`.
