#!/bin/bash
# ============================================
# EPCO - Script de Instalación en Servidor
# Ejecutar como root: bash install.sh
# ============================================

set -e

echo "=========================================="
echo "  EPCO - Instalación Automática"
echo "=========================================="

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Variables de configuración
DB_NAME="epco_db"
DB_USER="epco_user"
DB_PASS="EpcoSecure2026!"
DOMAIN="23.92.28.98"
WEB_ROOT="/var/www/epco"

echo -e "${YELLOW}[1/7] Actualizando sistema...${NC}"
apt update && apt upgrade -y

echo -e "${YELLOW}[2/7] Instalando Apache, MySQL, PHP...${NC}"
apt install -y apache2 mysql-server php php-mysql php-mbstring php-xml php-curl php-zip php-gd php-bcmath unzip curl

echo -e "${YELLOW}[3/7] Configurando MySQL...${NC}"
# Iniciar MySQL si no está corriendo
systemctl start mysql
systemctl enable mysql

# Crear base de datos y usuario
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

echo -e "${GREEN}Base de datos '${DB_NAME}' creada${NC}"

echo -e "${YELLOW}[4/7] Configurando proyecto EPCO...${NC}"
# Crear directorio si no existe
mkdir -p ${WEB_ROOT}

# Copiar archivos (si el script está en /tmp o junto al proyecto)
if [ -d "/tmp/epco" ]; then
    cp -r /tmp/epco/* ${WEB_ROOT}/
elif [ -d "$(dirname $0)/.." ]; then
    cp -r "$(dirname $0)/../"* ${WEB_ROOT}/
fi

# Permisos
chown -R www-data:www-data ${WEB_ROOT}
chmod -R 755 ${WEB_ROOT}
chmod -R 775 ${WEB_ROOT}/public/uploads
chmod -R 775 ${WEB_ROOT}/logs

echo -e "${YELLOW}[5/7] Configurando archivo de base de datos...${NC}"
# Actualizar configuración de BD
cat > ${WEB_ROOT}/config/database.php << 'DBCONFIG'
<?php
/**
 * EPCO - Configuración de Base de Datos
 */

$db_config = [
    'host' => 'localhost',
    'dbname' => 'epco_db',
    'username' => 'epco_user',
    'password' => 'EpcoSecure2026!',
    'charset' => 'utf8mb4'
];

try {
    $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("Error de conexión a BD: " . $e->getMessage());
    die("Error de conexión a la base de datos. Contacte al administrador.");
}
DBCONFIG

echo -e "${YELLOW}[6/7] Importando esquema de base de datos...${NC}"
# Importar SQL
if [ -f "${WEB_ROOT}/database/epco_complete.sql" ]; then
    mysql ${DB_NAME} < ${WEB_ROOT}/database/epco_complete.sql
    echo -e "${GREEN}Esquema importado desde epco_complete.sql${NC}"
elif [ -f "${WEB_ROOT}/database/schema.sql" ]; then
    mysql ${DB_NAME} < ${WEB_ROOT}/database/schema.sql
    echo -e "${GREEN}Esquema importado desde schema.sql${NC}"
fi

echo -e "${YELLOW}[7/7] Configurando Apache...${NC}"
# Habilitar mod_rewrite
a2enmod rewrite

# Crear VirtualHost
cat > /etc/apache2/sites-available/epco.conf << VHOST
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${WEB_ROOT}/public
    
    <Directory ${WEB_ROOT}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/epco_error.log
    CustomLog \${APACHE_LOG_DIR}/epco_access.log combined
</VirtualHost>
VHOST

# Habilitar sitio y deshabilitar default
a2dissite 000-default.conf 2>/dev/null || true
a2ensite epco.conf

# Reiniciar Apache
systemctl restart apache2
systemctl enable apache2

echo ""
echo -e "${GREEN}=========================================="
echo "  ¡INSTALACIÓN COMPLETADA!"
echo "==========================================${NC}"
echo ""
echo "Accede a tu portal en: http://${DOMAIN}"
echo ""
echo "Credenciales de Base de Datos:"
echo "  - Base de datos: ${DB_NAME}"
echo "  - Usuario: ${DB_USER}"
echo "  - Contraseña: ${DB_PASS}"
echo ""
echo "Usuarios por defecto (password: password):"
echo "  - admin@epco.cl (admin)"
echo "  - soporte@epco.cl (soporte)"
echo "  - social@epco.cl (social)"
echo "  - usuario@epco.cl (user)"
echo ""
echo -e "${YELLOW}IMPORTANTE: Cambia las contraseñas en producción${NC}"
