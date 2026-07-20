# Fase 8: eventos, evidencias, recorridos y novedades

## Modelo y permisos

La migración `010_events_rounds_novelties` crea eventos, comentarios, evidencias, políticas, recorridos y novedades. `011_incident_event_fields` separa los tipos de incidencia de los eventos internos y agrega un título obligatorio. `events.create` y `rounds.execute` pertenecen al vigilante; `events.review` y `rounds.review` al supervisor; `events.manage` y `rounds.view` al administrador. El backend vuelve a comprobar alcance y permiso en cada operación.

## Reglas protegidas en servidor

- El módulo Incidencias solo muestra tipos con alcance `incident`; visitas, proveedores, recorridos y novedades usan eventos internos invisibles en ese selector.
- El título contiene entre 5 y 180 caracteres y el comentario inicial entre 10 y 2000.
- La prioridad del vigilante nunca puede ser inferior a la predeterminada del tipo.
- Una incidencia enviada no se edita ni elimina. La cancelación conserva incidencia y evidencia, exige motivo y deja auditoría.
- Creación, carga posterior de evidencia, comentarios y cancelación registran actor, fecha, IP, agente y contexto en el registro de seguridad.
- Los comentarios son anexos inmutables de supervisores autorizados.
- Cada evento admite hasta 10 evidencias y 20 MB totales. Se valida el MIME real.
- Los archivos viven en `storage/events`, fuera de `public`, y se sirven por `event-evidence.php` después de comprobar sesión y alcance.
- Solo existe un recorrido abierto por vigilante. Finalizar exige una evidencia y observaciones.
- Un recorrido que excede la política queda incompleto y un supervisor puede cerrarlo con comentario.
- El cierre normal de turno exige una novedad registrada, incluida la opción “Turno entregado sin novedades”.

## Cámara y offline

La interfaz no contiene selector de archivos. Usa `getUserMedia`, canvas y `MediaRecorder`; el video se detiene a los 30 segundos. Las fotos reciben marca con fecha, usuario, cliente y lugar cuando GD está disponible. Eventos, evidencias e inicio/fin de recorridos usan la cola IndexedDB de Fase 6 cuando no hay conexión; su validación final en cPanel continúa pendiente.

## API principal

`GET/POST /event-types`, `GET/POST /events`, `GET /events/detail`, `POST /events/evidence`, `POST /events/comment`, `POST /events/cancel`, `GET /phase8/guard`, `GET /rounds`, `POST /rounds/start`, `POST /rounds/finish`, `POST /rounds/close` y `POST /shift-novelties`.
