# Fase 2 — Autenticación, sesiones y permisos

## Flujo

El acceso web recibe correo y contraseña. La contraseña se verifica con `password_verify`; después se regenera el ID de la sesión PHP y se crea un token aleatorio de 256 bits. Solo su SHA-256 se almacena en `user_sessions`.

Cada petición protegida exige una sesión PHP válida, token no revocado, expiración futura, usuario activo, rol activo y empresa activa. Los permisos efectivos combinan `role_permissions` con excepciones de `user_permissions`. Toda autorización se aplica en backend.

## Protección contra intentos

`auth_attempts` identifica la combinación de hash del correo e IP. Desde el quinto fallo aplica 60 segundos de espera y duplica la espera en cada fallo posterior hasta 3600 segundos. Los intentos y accesos se registran en `security_logs` sin guardar contraseñas.

## Contraseñas y sesiones

- 12 caracteres como mínimo, mayúscula, minúscula, número y símbolo.
- El cambio propio exige la contraseña actual y revoca las demás sesiones.
- El restablecimiento administrativo requiere `users.password_reset`, fuerza cambio posterior y revoca todas las sesiones del usuario.
- La duración máxima es 24 horas.
- El máximo predeterminado es cinco sesiones; el rol vigilante queda limitado a una como preparación para su flujo operativo futuro.

## Alcance

No se implementó el acceso QR/PIN de vigilantes, recuperación de contraseña por correo ni alcances por clientes/lugares. Corresponden a fases posteriores.
