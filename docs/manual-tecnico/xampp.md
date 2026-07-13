# Instalación en XAMPP

1. Copiar la carpeta a C:\xampp-8.1\htdocs\control-de-accesos.
2. Habilitar Apache, MySQL, mod_rewrite y mod_headers.
3. Ejecutar composer install desde la raíz.
4. Abrir http://localhost/control-de-accesos/public/install/.
5. Completar los diez pasos del instalador.
6. Confirmar el panel, /api/health y /docs/.

Apache debe permitir AllowOverride All para htdocs. storage y la raíz requieren escritura durante la instalación; en producción debe aplicarse el mínimo permiso necesario.
