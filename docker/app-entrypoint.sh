#!/bin/bash
# =============================================
# EPCO - App Entrypoint
# Ajusta permisos de volúmenes montados en runtime
# =============================================

# Ajustar permisos de directorios montados como volúmenes
# (los volúmenes del host pueden tener permisos de root)
chown -R www-data:www-data /var/www/html/logs 2>/dev/null || true
chmod -R 775 /var/www/html/logs 2>/dev/null || true

chown -R www-data:www-data /var/www/html/public/uploads 2>/dev/null || true
chmod -R 775 /var/www/html/public/uploads 2>/dev/null || true

# Crear directorio de logs si no existe
mkdir -p /var/www/html/logs
chown www-data:www-data /var/www/html/logs

# Ejecutar comando original (apache2-foreground)
exec "$@"
