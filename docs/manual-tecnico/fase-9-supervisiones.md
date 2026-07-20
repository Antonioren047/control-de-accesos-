# Fase 9: supervisiones

La migración `012_supervisions` crea programaciones, puntos seleccionados, supervisiones, evidencias y comentarios. También incorpora `users.confirmation_pin_hash`; nunca se conserva un PIN en texto plano.

Los permisos están separados: `supervisions.schedule` permite programar y se asigna a superadministrador y administrador; `supervisions.manage` permite consultar y ejecutar dentro del alcance y se asigna también al supervisor. Toda consulta valida el lugar en backend.

Una supervisión iniciada registra folio, supervisor, cliente, lugar, puntos y hora UTC. Mientras está en proceso admite fotografías privadas. Al finalizar exige observaciones, confirmación del supervisor y confirmación del vigilante o responsable mediante nombre exacto y PIN. Si el responsable está ausente, exige motivo. El registro final no vuelve a editarse; los comentarios posteriores son filas inmutables.

Los archivos se guardan en `storage/supervisions/YYYY/MM`. `supervision-evidence.php` y `supervision-report.php` requieren sesión, permiso y alcance. El PDF se genera bajo demanda con Dompdf y su generación queda en el log de seguridad.

Datos demo: el PIN de confirmación para cuentas administrativas y supervisoras existentes es `102938`. En producción debe establecerse un PIN propio mediante un flujo administrativo antes de operar.
