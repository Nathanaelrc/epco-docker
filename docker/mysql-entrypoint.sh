#!/bin/bash
# =============================================
# EPCO - MySQL Custom Entrypoint
# Asegura que las tablas se creen siempre,
# incluso si el volumen ya existía
# =============================================

set -e

# Función que verifica e inicializa las tablas después de que MySQL arranque
epco_ensure_tables() {
    echo "[EPCO] Esperando a que MySQL esté listo..."
    
    local retries=60
    while [ $retries -gt 0 ]; do
        if mysqladmin ping -h localhost -u root -p"${MYSQL_ROOT_PASSWORD}" --silent 2>/dev/null; then
            break
        fi
        retries=$((retries - 1))
        sleep 2
    done

    if [ $retries -eq 0 ]; then
        echo "[EPCO] ERROR: MySQL no respondió a tiempo"
        return 1
    fi

    # Verificar si la tabla 'users' existe
    TABLE_EXISTS=$(mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -sN \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${MYSQL_DATABASE}' AND table_name='users'" 2>/dev/null || echo "0")

    if [ "$TABLE_EXISTS" = "0" ] || [ -z "$TABLE_EXISTS" ]; then
        echo "[EPCO] Tablas no encontradas. Inicializando base de datos..."
        
        # Crear la base de datos si no existe
        mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
        
        # Cargar el esquema completo
        if [ -f /sql/init.sql ]; then
            mysql -u root -p"${MYSQL_ROOT_PASSWORD}" < /sql/init.sql 2>&1
            echo "[EPCO] ✓ Base de datos inicializada exitosamente"
        else
            echo "[EPCO] ERROR: /sql/init.sql no encontrado"
            return 1
        fi
        
        # Asegurar que el usuario tenga permisos
        mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%'; FLUSH PRIVILEGES;" 2>/dev/null
        echo "[EPCO] ✓ Permisos configurados para ${MYSQL_USER}"
    else
        echo "[EPCO] ✓ Base de datos ya inicializada (tablas existentes)"
    fi
}

# Ejecutar verificación en background después de que MySQL arranque
epco_ensure_tables &

# Delegar al entrypoint original de MySQL
exec docker-entrypoint.sh "$@"
