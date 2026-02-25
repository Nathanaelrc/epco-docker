#!/bin/bash
# =============================================
# EPCO - App Entrypoint
# 1. Espera a que MySQL esté listo (wait-for-db)
# 2. Ajusta permisos de volúmenes montados en runtime
# =============================================

# =============================================
# Wait-for-DB: esperar a que MySQL acepte conexiones
# =============================================
DB_HOST="${DB_HOST:-db}"
DB_USER="${DB_USER:-epco_user}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-epco}"
MAX_RETRIES=30
RETRY_INTERVAL=3

echo "[EPCO] Verificando conexión a MySQL ($DB_HOST)..."

attempt=0
while [ $attempt -lt $MAX_RETRIES ]; do
    attempt=$((attempt + 1))
    # Intentar conexión TCP al puerto 3306
    if php -r "
        try {
            \$pdo = new PDO('mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4', '$DB_USER', '$DB_PASS', [PDO::ATTR_TIMEOUT => 3]);
            echo 'OK';
            exit(0);
        } catch (Exception \$e) {
            echo \$e->getMessage();
            exit(1);
        }
    " 2>/dev/null; then
        echo ""
        echo "[EPCO] ✓ Conexión a MySQL exitosa (intento $attempt)"
        break
    else
        echo "[EPCO] Intento $attempt/$MAX_RETRIES - MySQL no disponible, reintentando en ${RETRY_INTERVAL}s..."
        sleep $RETRY_INTERVAL
    fi
done

if [ $attempt -eq $MAX_RETRIES ]; then
    echo "[EPCO] ⚠ ADVERTENCIA: No se pudo conectar a MySQL después de $MAX_RETRIES intentos."
    echo "[EPCO]   Host=$DB_HOST, User=$DB_USER, DB=$DB_NAME"
    echo "[EPCO]   La app se iniciará de todas formas, pero puede mostrar errores."
fi

# =============================================
# Ajustar permisos de volúmenes montados
# =============================================
chown -R www-data:www-data /var/www/html/logs 2>/dev/null || true
chmod -R 775 /var/www/html/logs 2>/dev/null || true

chown -R www-data:www-data /var/www/html/public/uploads 2>/dev/null || true
chmod -R 775 /var/www/html/public/uploads 2>/dev/null || true

# Crear directorio de logs si no existe
mkdir -p /var/www/html/logs
chown www-data:www-data /var/www/html/logs

echo "[EPCO] Iniciando Apache..."

# Ejecutar comando original (apache2-foreground)
exec "$@"
