# Instalación inicial en cPanel

Configura PHP 8.1 o superior y apunta el DocumentRoot a public. Sube vendor generado por Composer o ejecuta composer install --no-dev --optimize-autoloader. Crea una base y usuario con privilegios limitados. Permite escritura temporal sobre la raíz para crear .env y permanente solo sobre storage. Después del instalador confirma installed.lock y retira permisos de escritura innecesarios. La tarea Cron futura usará: php /ruta/al/proyecto/cron/run.php.
