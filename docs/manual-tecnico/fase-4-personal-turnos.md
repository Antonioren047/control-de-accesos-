# Fase 4: personal, credenciales y turnos

La migración `004_workforce` agrega expedientes de vigilantes, credenciales, turnos, ubicaciones de turno, asignaciones, historial y solicitudes de cambio.

## Seguridad de credenciales

- El QR contiene únicamente 64 caracteres hexadecimales aleatorios.
- En la base se conserva SHA-256 del token, nunca el token original ni datos personales.
- Una credencial es permanente hasta que un Administrador la revoque o regenere.
- El PIN es de seis dígitos, generado por el sistema y mostrado una sola vez. Solo Superadministrador o Administrador pueden restablecerlo.
- Los contadores `pin_failed_attempts` y `pin_blocked_until` quedan preparados para el acceso QR + PIN de la Fase 5.

## Reglas operativas

Los turnos admiten cruce de medianoche, días, uno o varios lugares y tolerancias. Las asignaciones relacionan vigilante, cliente, lugar, punto y turno. El servicio rechaza relaciones incoherentes y cualquier traslape de fechas y días para el mismo vigilante. Cancelaciones y altas se guardan en historial.

El Supervisor solo consulta datos dentro de sus lugares y puede enviar solicitudes de cambio; no puede crear, cancelar, emitir credenciales ni restablecer PIN.

## PDF y QR

Ejecute `composer update dompdf/dompdf endroid/qr-code` al desplegar la fase. La vista imprimible está en `public/credential.php?id=ID`; agregue `&format=pdf` para descargar PDF.
