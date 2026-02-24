#!/bin/bash
# =============================================
# EPCO - Script de inicio inteligente
# Detecta puertos disponibles automáticamente
# Uso: bash start.sh
# =============================================

set -e

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"

echo ""
echo -e "${BOLD}=========================================="
echo -e "  EPCO - Inicio Inteligente"
echo -e "==========================================${NC}"
echo ""

# =============================================
# Función: verificar si un puerto está en uso
# Retorna 0 si está LIBRE, 1 si está OCUPADO
# =============================================
is_port_free() {
    local port=$1
    # Verificar con ss (más portable que netstat)
    if command -v ss &>/dev/null; then
        ! ss -tlnp 2>/dev/null | grep -q ":${port} "
    # Fallback con netstat
    elif command -v netstat &>/dev/null; then
        ! netstat -tlnp 2>/dev/null | grep -q ":${port} "
    # Fallback intentando conectar
    else
        ! (echo >/dev/tcp/localhost/${port}) 2>/dev/null
    fi
}

# =============================================
# Función: encontrar el próximo puerto libre
# a partir de un puerto base
# =============================================
find_free_port() {
    local port=$1
    local max_attempts=100

    for ((i=0; i<max_attempts; i++)); do
        if is_port_free $port; then
            echo $port
            return 0
        fi
        ((port++))
    done

    echo ""
    return 1
}

# =============================================
# Detectar puertos disponibles
# =============================================
echo -e "${CYAN}[1/4] Detectando puertos disponibles...${NC}"

# Puertos deseados por defecto
DEFAULT_APP_PORT=8080
DEFAULT_DB_PORT=3306
DEFAULT_PMA_PORT=8081

# Leer .env existente si hay
if [ -f "$ENV_FILE" ]; then
    source "$ENV_FILE" 2>/dev/null || true
    DEFAULT_APP_PORT=${APP_PORT:-$DEFAULT_APP_PORT}
    DEFAULT_DB_PORT=${DB_PORT:-$DEFAULT_DB_PORT}
    DEFAULT_PMA_PORT=${PMA_PORT:-$DEFAULT_PMA_PORT}
fi

# Encontrar puertos libres
APP_PORT=$(find_free_port $DEFAULT_APP_PORT)
if [ -z "$APP_PORT" ]; then
    echo -e "${RED}Error: No se encontró puerto libre para la app (desde ${DEFAULT_APP_PORT})${NC}"
    exit 1
fi

DB_PORT=$(find_free_port $DEFAULT_DB_PORT)
if [ -z "$DB_PORT" ]; then
    echo -e "${RED}Error: No se encontró puerto libre para MySQL (desde ${DEFAULT_DB_PORT})${NC}"
    exit 1
fi

# Asegurar que PMA_PORT no colisione con APP_PORT
if [ "$DEFAULT_PMA_PORT" -eq "$APP_PORT" ]; then
    DEFAULT_PMA_PORT=$((APP_PORT + 1))
fi

PMA_PORT=$(find_free_port $DEFAULT_PMA_PORT)
if [ -z "$PMA_PORT" ]; then
    echo -e "${RED}Error: No se encontró puerto libre para phpMyAdmin (desde ${DEFAULT_PMA_PORT})${NC}"
    exit 1
fi

# Mostrar resultado
echo -e "  App (HTTP):    ${BOLD}${APP_PORT}${NC} $([ $APP_PORT -ne $DEFAULT_APP_PORT ] && echo -e "${YELLOW}(${DEFAULT_APP_PORT} ocupado)${NC}" || echo -e "${GREEN}(disponible)${NC}")"
echo -e "  MySQL:         ${BOLD}${DB_PORT}${NC} $([ $DB_PORT -ne $DEFAULT_DB_PORT ] && echo -e "${YELLOW}(${DEFAULT_DB_PORT} ocupado)${NC}" || echo -e "${GREEN}(disponible)${NC}")"
echo -e "  phpMyAdmin:    ${BOLD}${PMA_PORT}${NC} $([ $PMA_PORT -ne $DEFAULT_PMA_PORT ] && echo -e "${YELLOW}(${DEFAULT_PMA_PORT} ocupado)${NC}" || echo -e "${GREEN}(disponible)${NC}")"
echo ""

# =============================================
# Generar/actualizar .env
# =============================================
echo -e "${CYAN}[2/4] Generando archivo .env...${NC}"

# Preservar variables existentes que no sean puertos
DB_NAME="${DB_NAME:-epco}"
DB_USER="${DB_USER:-epco_user}"
DB_PASS="${DB_PASS:-EpcoSecure2026}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-EpcoRoot2026}"
APP_ENV="${APP_ENV:-production}"

cat > "$ENV_FILE" << EOF
# =============================================
# EPCO - Variables de Entorno
# Generado automáticamente por start.sh
# Fecha: $(date '+%Y-%m-%d %H:%M:%S')
# =============================================

# Puertos (auto-detectados)
APP_PORT=${APP_PORT}
DB_PORT=${DB_PORT}
PMA_PORT=${PMA_PORT}

# Entorno
APP_ENV=${APP_ENV}

# Base de datos
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
EOF

echo -e "  ${GREEN}Archivo .env actualizado${NC}"
echo ""

# =============================================
# Construir y levantar
# =============================================
echo -e "${CYAN}[3/4] Construyendo imágenes...${NC}"
cd "$SCRIPT_DIR"
docker compose build --quiet 2>&1 | tail -5

echo ""
echo -e "${CYAN}[4/4] Levantando servicios...${NC}"
docker compose up -d

# =============================================
# Esperar a que los servicios estén listos
# =============================================
echo ""
echo -e "${YELLOW}Esperando a que los servicios estén listos...${NC}"
TRIES=0
MAX_TRIES=30
while [ $TRIES -lt $MAX_TRIES ]; do
    if docker compose ps --format json 2>/dev/null | grep -q '"Health":"healthy"' || \
       docker inspect epco-app --format '{{.State.Health.Status}}' 2>/dev/null | grep -q "healthy"; then
        break
    fi
    sleep 2
    ((TRIES++))
    printf "."
done
echo ""

# =============================================
# Resultado final
# =============================================
echo ""
echo -e "${GREEN}${BOLD}=========================================="
echo -e "  EPCO - Servicios Iniciados"
echo -e "==========================================${NC}"
echo ""

# Detectar IP del servidor
SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
if [ -z "$SERVER_IP" ]; then
    SERVER_IP="localhost"
fi

echo -e "  ${BOLD}Portal EPCO:${NC}      http://${SERVER_IP}:${APP_PORT}"
echo -e "  ${BOLD}phpMyAdmin:${NC}       http://${SERVER_IP}:${PMA_PORT}"
echo -e "  ${BOLD}MySQL:${NC}            ${SERVER_IP}:${DB_PORT}"
echo ""
echo -e "  ${BOLD}Estado de contenedores:${NC}"
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || docker compose ps
echo ""
