#!/bin/bash
# =============================================
# EPCO - Script de inicio inteligente v2.0
# Detecta puertos disponibles automáticamente
# sin interferir con otros contenedores/servicios
#
# Uso: bash start.sh [--force-ports]
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
FORCE_PORTS=false

# Parámetro --force-ports para saltar detección
if [[ "$1" == "--force-ports" ]]; then
    FORCE_PORTS=true
fi

echo ""
echo -e "${BOLD}=========================================="
echo -e "  EPCO - Inicio Inteligente v2.0"
echo -e "==========================================${NC}"
echo ""

# =============================================
# Función: obtener TODOS los puertos ocupados
# Combina puertos del sistema + Docker containers
# =============================================
get_all_used_ports() {
    local ports=""

    # 1. Puertos del sistema (ss o netstat)
    if command -v ss &>/dev/null; then
        ports+=$(ss -tlnp 2>/dev/null | awk '{print $4}' | grep -oP ':\K[0-9]+$' | sort -un)
    elif command -v netstat &>/dev/null; then
        ports+=$'\n'
        ports+=$(netstat -tlnp 2>/dev/null | awk '{print $4}' | grep -oP ':\K[0-9]+$' | sort -un)
    fi

    # 2. Puertos mapeados por Docker containers existentes
    if command -v docker &>/dev/null; then
        local docker_ports
        docker_ports=$(docker ps --format '{{.Ports}}' 2>/dev/null | \
            grep -oP '0\.0\.0\.0:\K[0-9]+' | sort -un 2>/dev/null || true)
        if [ -n "$docker_ports" ]; then
            ports+=$'\n'"$docker_ports"
        fi
        # También puertos con :::
        docker_ports=$(docker ps --format '{{.Ports}}' 2>/dev/null | \
            grep -oP ':::\K[0-9]+' | sort -un 2>/dev/null || true)
        if [ -n "$docker_ports" ]; then
            ports+=$'\n'"$docker_ports"
        fi
    fi

    # Devolver lista única ordenada
    echo "$ports" | grep -v '^$' | sort -un
}

# =============================================
# Función: verificar si un puerto está libre
# Chequea sistema + Docker containers
# =============================================
is_port_free() {
    local port=$1
    local used_ports="$2"

    # Verificar contra la lista de puertos usados
    if echo "$used_ports" | grep -qw "^${port}$"; then
        return 1
    fi

    # Doble verificación: intentar bind real (más fiable)
    if command -v python3 &>/dev/null; then
        python3 -c "
import socket
s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
try:
    s.bind(('0.0.0.0', $port))
    s.close()
except OSError:
    exit(1)
" 2>/dev/null
        return $?
    fi

    return 0
}

# =============================================
# Función: encontrar puerto libre desde un base
# evitando puertos ya asignados en esta sesión
# =============================================
find_free_port() {
    local port=$1
    local used_ports="$2"
    local max_port=$((port + 200))
    local assigned_ports="$3"

    while [ $port -lt $max_port ]; do
        # Verificar que no esté en la lista de ya asignados por este script
        if ! echo "$assigned_ports" | grep -qw "$port"; then
            if is_port_free "$port" "$used_ports"; then
                echo "$port"
                return 0
            fi
        fi
        ((port++))
    done

    echo ""
    return 1
}

# =============================================
# Recopilar todos los puertos en uso
# =============================================
echo -e "${CYAN}[1/5] Analizando puertos en uso...${NC}"
USED_PORTS=$(get_all_used_ports)
USED_COUNT=$(echo "$USED_PORTS" | grep -c '[0-9]' || echo 0)
echo -e "  Se detectaron ${BOLD}${USED_COUNT}${NC} puertos en uso (sistema + Docker)"

# Mostrar contenedores Docker existentes si hay
if command -v docker &>/dev/null; then
    RUNNING=$(docker ps --format '{{.Names}}' 2>/dev/null | grep -cv '^$' || echo 0)
    if [ "$RUNNING" -gt 0 ]; then
        echo -e "  Contenedores activos: ${BOLD}${RUNNING}${NC}"
        docker ps --format "    - {{.Names}}: {{.Ports}}" 2>/dev/null | head -10
    fi
fi
echo ""

# =============================================
# Puertos por defecto con fallbacks amplios
# =============================================

# Leer .env existente si hay
if [ -f "$ENV_FILE" ] && [ "$FORCE_PORTS" = false ]; then
    source "$ENV_FILE" 2>/dev/null || true
fi

DEFAULT_APP_PORT=${APP_PORT:-8080}
DEFAULT_DB_PORT=${DB_PORT:-3306}
DEFAULT_PMA_PORT=${PMA_PORT:-8081}

# =============================================
# Detectar puertos libres (sin colisiones entre sí)
# =============================================
echo -e "${CYAN}[2/5] Asignando puertos disponibles...${NC}"

ASSIGNED=""

APP_PORT=$(find_free_port $DEFAULT_APP_PORT "$USED_PORTS" "$ASSIGNED")
if [ -z "$APP_PORT" ]; then
    echo -e "${RED}  ✗ No se encontró puerto libre para la App (desde ${DEFAULT_APP_PORT})${NC}"
    exit 1
fi
ASSIGNED+="$APP_PORT "

DB_PORT=$(find_free_port $DEFAULT_DB_PORT "$USED_PORTS" "$ASSIGNED")
if [ -z "$DB_PORT" ]; then
    echo -e "${RED}  ✗ No se encontró puerto libre para MySQL (desde ${DEFAULT_DB_PORT})${NC}"
    exit 1
fi
ASSIGNED+="$DB_PORT "

# PMA no debe colisionar con APP
if [ "$DEFAULT_PMA_PORT" -le "$APP_PORT" ] && [ "$DEFAULT_PMA_PORT" -ne 8081 ]; then
    DEFAULT_PMA_PORT=$((APP_PORT + 1))
fi
PMA_PORT=$(find_free_port $DEFAULT_PMA_PORT "$USED_PORTS" "$ASSIGNED")
if [ -z "$PMA_PORT" ]; then
    echo -e "${RED}  ✗ No se encontró puerto libre para phpMyAdmin (desde ${DEFAULT_PMA_PORT})${NC}"
    exit 1
fi
ASSIGNED+="$PMA_PORT "

# Mostrar asignación
show_port() {
    local name=$1 assigned=$2 default=$3
    if [ "$assigned" -eq "$default" ]; then
        echo -e "  ${GREEN}✓${NC} ${name}: ${BOLD}${assigned}${NC}"
    else
        echo -e "  ${YELLOW}⚠${NC} ${name}: ${BOLD}${assigned}${NC} ${YELLOW}(${default} estaba ocupado)${NC}"
    fi
}

show_port "App (HTTP)  " "$APP_PORT" "$DEFAULT_APP_PORT"
show_port "MySQL       " "$DB_PORT" "$DEFAULT_DB_PORT"
show_port "phpMyAdmin  " "$PMA_PORT" "$DEFAULT_PMA_PORT"
echo ""

# =============================================
# Generar .env
# =============================================
echo -e "${CYAN}[3/5] Generando .env...${NC}"

DB_NAME="${DB_NAME:-epco}"
DB_USER="${DB_USER:-epco_user}"
DB_PASS="${DB_PASS:-EpcoSecure2026}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-EpcoRoot2026}"
APP_ENV="${APP_ENV:-production}"

cat > "$ENV_FILE" << EOF
# =============================================
# EPCO - Variables de Entorno
# Auto-generado por start.sh - $(date '+%Y-%m-%d %H:%M:%S')
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

echo -e "  ${GREEN}✓${NC} Archivo .env generado"
echo ""

# =============================================
# Verificar conflictos de nombres de contenedores
# =============================================
echo -e "${CYAN}[4/5] Verificando contenedores...${NC}"

for cname in epco-app epco-db epco-phpmyadmin; do
    if docker ps -a --format '{{.Names}}' 2>/dev/null | grep -qw "^${cname}$"; then
        local_label=$(docker inspect "$cname" --format '{{index .Config.Labels "com.docker.compose.project"}}' 2>/dev/null || echo "")
        if echo "$local_label" | grep -qi "epco"; then
            echo -e "  ${GREEN}✓${NC} '${cname}' es del stack EPCO - se recreará"
        else
            echo -e "  ${YELLOW}⚠${NC} '${cname}' existe de otro proyecto - podría haber conflicto"
        fi
    else
        echo -e "  ${GREEN}✓${NC} '${cname}' disponible"
    fi
done
echo ""

# =============================================
# Construir y levantar
# =============================================
echo -e "${CYAN}[5/5] Construyendo y levantando servicios...${NC}"
cd "$SCRIPT_DIR"

docker compose build 2>&1 | tail -20
echo ""
docker compose up -d 2>&1

# =============================================
# Esperar a que MySQL tenga las tablas listas
# =============================================
echo ""
echo -ne "${YELLOW}Esperando inicialización de la base de datos${NC}"
DB_CONTAINER="epco-db"
TRIES=0
MAX_TRIES=60

while [ $TRIES -lt $MAX_TRIES ]; do
    # Verificar que el contenedor esté corriendo
    if ! docker ps --format '{{.Names}}' 2>/dev/null | grep -qw "$DB_CONTAINER"; then
        sleep 2
        ((TRIES++))
        printf "."
        continue
    fi

    # Verificar que la tabla users exista
    TABLE_CHECK=$(docker exec "$DB_CONTAINER" mysql -u root -p"${DB_ROOT_PASSWORD}" -sN \
        -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users'" 2>/dev/null || echo "0")

    if [ "$TABLE_CHECK" = "1" ]; then
        echo ""
        echo -e "  ${GREEN}✓${NC} Tablas verificadas correctamente"
        break
    fi

    sleep 2
    ((TRIES++))
    printf "."
done

if [ $TRIES -eq $MAX_TRIES ]; then
    echo ""
    echo -e "  ${YELLOW}⚠ Timeout esperando tablas. Verificar: docker logs epco-db${NC}"
fi

# =============================================
# Verificar que la app responda
# =============================================
echo -ne "${YELLOW}Verificando aplicación${NC}"
TRIES=0
MAX_TRIES=30
while [ $TRIES -lt $MAX_TRIES ]; do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:${APP_PORT}/" 2>/dev/null || echo "000")
    if [ "$HTTP_CODE" != "000" ] && [ "$HTTP_CODE" != "502" ] && [ "$HTTP_CODE" != "503" ]; then
        echo ""
        echo -e "  ${GREEN}✓${NC} App respondiendo (HTTP ${HTTP_CODE})"
        break
    fi
    sleep 2
    ((TRIES++))
    printf "."
done

if [ $TRIES -eq $MAX_TRIES ]; then
    echo ""
    echo -e "  ${YELLOW}⚠ La app no respondió. Verificar: docker logs epco-app${NC}"
fi

# =============================================
# Resultado final
# =============================================
echo ""
echo -e "${GREEN}${BOLD}=========================================="
echo -e "  EPCO - Servicios Listos"
echo -e "==========================================${NC}"
echo ""

SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
[ -z "$SERVER_IP" ] && SERVER_IP="localhost"

echo -e "  ${BOLD}Portal EPCO:${NC}      http://${SERVER_IP}:${APP_PORT}"
echo -e "  ${BOLD}phpMyAdmin:${NC}       http://${SERVER_IP}:${PMA_PORT}"
echo -e "  ${BOLD}MySQL:${NC}            ${SERVER_IP}:${DB_PORT}"
echo ""
echo -e "  ${BOLD}Credenciales:${NC}"
echo -e "    Usuario: admin.epco  /  Contraseña: password"
echo ""
echo -e "  ${BOLD}Estado:${NC}"
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}" 2>/dev/null || docker compose ps
echo ""
