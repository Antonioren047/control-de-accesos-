# Mapa visual del frontend

Este documento define el contrato compartido aplicado por `public/assets/js/ui-map.js` y `public/assets/css/ui-map.css`.

## Iconos de módulos

| Vista | Icono semántico | Superficies |
|---|---|---|
| Inicio | Casa | Menú principal, menú del vigilante y tarjetas |
| Clientes | Ficha | Menú e introducción de página |
| Lugares / unidades | Ubicación / casa | Menú e introducción de página |
| Usuarios | Personas | Menú e introducción de página |
| Turnos | Reloj | Menú, página y portal del vigilante |
| Operación | Objetivo | Menú e introducción de página |
| Asistencias | Verificación | Menú e introducción de página |
| Visitas | Visitante | Menú, página y portal del vigilante |
| Proveedores | Maletín | Menú, página y portal del vigilante |
| Eventos / incidencias | Alerta | Menú, página y portal del vigilante |
| Recorridos | Ruta | Menú, página y portal del vigilante |
| Supervisiones | Escudo verificado | Menú e introducción de página |
| Sincronización | Flechas | Menú e introducción de página |
| Reportes | Documento | Menú e introducción de página |
| Configuración | Engranaje | Menú e introducción de página |
| Auditoría | Escudo | Menú e introducción de página |
| Mantenimiento | Herramienta | Menú e introducción de página |
| Perfil / seguridad / permisos | Persona / candado / matriz | Menú de cuenta |

## Acciones

| Acción o texto | Icono | Tratamiento |
|---|---|---|
| Nuevo, crear, agregar | Más | Acción de alta |
| Editar, modificar | Lápiz | Acción secundaria |
| Eliminar, revocar | Papelera | Color de peligro y confirmación |
| Restaurar, regenerar | Historial | Acción reversible |
| Guardar, registrar | Disco | Acción primaria |
| Actualizar, sincronizar | Recarga | Acción secundaria |
| Escanear QR | Código QR | Acción de cámara destacada |
| Tomar fotografía / evidencia | Cámara | Acción de captura destacada |
| Grabar video | Videocámara | Acción de captura destacada |
| Iniciar / validar | Reproducir | Acción de avance |
| Finalizar / registrar salida | Bandera | Acción de cierre |
| Descargar / imprimir / compartir | Descarga / impresora / compartir | Acción documental |
| Comentar / PIN | Mensaje / llave | Acción complementaria |

## Imágenes y captura

- Las vistas de cámara y las vistas previas usan relación 4:3, fondo oscuro, borde y radio comunes.
- Los modales de fotografía y video tienen ancho responsivo, controles uniformes y fondo de alto contraste.
- Las evidencias en eventos, recorridos y supervisiones usan tarjetas, miniaturas con `object-fit: cover` y leyendas legibles.
- Los controles de cámara, QR y video conservan contraste tanto en modo claro como oscuro.
- El observador de interfaz también aplica este contrato a botones, imágenes y modales creados dinámicamente.
