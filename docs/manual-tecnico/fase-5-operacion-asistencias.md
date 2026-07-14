# Fase 5: operación y asistencias

La migración `005_operations` crea `operational_sessions` y `attendance_records`. Toda fecha se conserva en UTC y se presenta en `APP_TIMEZONE`.

El acceso operativo es independiente del inicio administrativo. El vigilante selecciona cliente, lugar y punto; presenta el token QR vigente, su PIN de seis dígitos y una fotografía tomada con la cámara. El backend valida relaciones y asignación activa, calcula el turno incluso cuando cruza medianoche y registra dispositivo, navegador, sistema operativo, IP e identificador local cifrado mediante SHA-256.

Solo puede existir una sesión activa por vigilante y punto de acceso. Una sesión previa nunca se cierra automáticamente: un usuario con `operational_sessions.close` debe registrar un comentario de 10 a 500 caracteres. La acción y su motivo quedan en `security_logs`.

El PIN aplica espera progresiva desde el quinto intento. La fotografía se valida por MIME real, acepta JPEG, PNG o WebP hasta 5 MB y se guarda fuera de `public`.

Endpoints principales: `GET operations/catalog`, `POST operations/start`, `GET operations/current`, `POST operations/close`, `GET operations/sessions`, `GET operations/attendance` y `POST operations/manual-close`.
