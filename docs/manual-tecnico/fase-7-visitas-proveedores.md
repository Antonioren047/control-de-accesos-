# Fase 7: visitas y proveedores

## Modelo y seguridad

La migración `009_visitors_providers` crea políticas por cliente, autorizaciones de visita, movimientos de entrada/salida, bitácora de compartir y accesos de proveedor. Los tokens QR se generan con 256 bits aleatorios y en MySQL solo se conserva SHA-256. Las imágenes quedan fuera de `public`, bajo `storage/access`.

La consulta de identificaciones requiere `access_identifications.view` y alcance sobre el lugar. El vigilante captura las imágenes, pero no recibe permiso para consultarlas posteriormente.

## Visitas

- Solo un Residente con unidad activa puede generar una visita.
- Valores iniciales: 120 minutos de vigencia, 30 días de anticipación, 10 QR activos y 90 días de conservación de identificación.
- Se bloquea la misma persona, lugar y franja de dos horas.
- Puede editarse o cancelarse mientras esté pendiente y sin uso.
- El QR sirve para entrada y salida; si existe entrada, la salida permanece permitida tras el vencimiento.
- La validación se ejecuta contra la API y no forma parte de la cola offline.

## Proveedores

Residente, Administrador y Supervisor pueden generar un acceso previo con QR. El Vigilante puede registrar una entrada sin QR desde una sesión operativa activa. Cada registro conserva empresa, servicio, responsable, materiales, herramientas, identificación, fotografías, privacidad, entrada y salida.

## Endpoints principales

`GET access/catalog`, `GET|POST visits`, `POST visits/action`, `GET visits/validate`, `POST visits/check-in`, `POST visits/check-out`, `GET access/active`, `GET|POST providers`, `POST providers/check-in` y `POST providers/check-out`.

`visit-qr.php` entrega la imagen de forma autenticada, permite descarga y utiliza Web Share cuando el navegador admite compartir archivos. Abrir WhatsApp solo prepara el mensaje y registra que se presionó el botón.
