#!/bin/bash
# =========================================================
# EPCO - Script de optimización de imágenes
# Convierte imágenes a WebP y optimiza originales
# Uso: ./optimize_images.sh [directorio]
# =========================================================

set -e

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Verificar dependencias
for cmd in cwebp jpegoptim optipng; do
    if ! command -v $cmd &> /dev/null; then
        echo -e "${YELLOW}⚠ $cmd no encontrado. Instalar con: sudo apt-get install -y webp jpegoptim optipng${NC}"
        exit 1
    fi
done

# Directorio a procesar (default: public/img y public/uploads)
TARGET_DIR="${1:-public/}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"

echo -e "${BLUE}══════════════════════════════════════════${NC}"
echo -e "${BLUE}  EPCO - Optimización de Imágenes${NC}"
echo -e "${BLUE}══════════════════════════════════════════${NC}"
echo ""

# Configuración de calidad
WEBP_QUALITY_PHOTO=65    # Fotos/fondos (usados con overlay)
WEBP_QUALITY_LOGO=85     # Logos/iconos (necesitan más detalle)
JPEG_MAX_QUALITY=80      # Calidad máxima JPEG
PNG_OPTIMIZATION=5       # Nivel optimización PNG (1-7)

TOTAL_SAVED=0
FILES_PROCESSED=0

optimize_jpeg() {
    local file="$1"
    local original_size=$(stat -c%s "$file")
    
    # Optimizar JPEG original
    jpegoptim --max=$JPEG_MAX_QUALITY --strip-all --all-progressive "$file" > /dev/null 2>&1
    
    # Crear versión WebP
    local webp_file="${file%.*}.webp"
    cwebp -q $WEBP_QUALITY_PHOTO -m 6 -sharp_yuv "$file" -o "$webp_file" > /dev/null 2>&1
    
    local new_size=$(stat -c%s "$file")
    local webp_size=$(stat -c%s "$webp_file")
    local saved=$((original_size - webp_size))
    TOTAL_SAVED=$((TOTAL_SAVED + saved))
    FILES_PROCESSED=$((FILES_PROCESSED + 1))
    
    echo -e "  ${GREEN}✓${NC} $(basename "$file"): ${original_size}B → JPEG:${new_size}B / WebP:${webp_size}B (${GREEN}-$((saved/1024))KB${NC})"
}

optimize_png() {
    local file="$1"
    local original_size=$(stat -c%s "$file")
    
    # Optimizar PNG original
    optipng -o$PNG_OPTIMIZATION "$file" > /dev/null 2>&1
    
    # Crear versión WebP (con alpha)
    local webp_file="${file%.*}.webp"
    cwebp -q $WEBP_QUALITY_LOGO -alpha_q 90 "$file" -o "$webp_file" > /dev/null 2>&1
    
    local new_size=$(stat -c%s "$file")
    local webp_size=$(stat -c%s "$webp_file")
    local saved=$((original_size - webp_size))
    TOTAL_SAVED=$((TOTAL_SAVED + saved))
    FILES_PROCESSED=$((FILES_PROCESSED + 1))
    
    echo -e "  ${GREEN}✓${NC} $(basename "$file"): ${original_size}B → PNG:${new_size}B / WebP:${webp_size}B (${GREEN}-$((saved/1024))KB${NC})"
}

# Procesar JPEG
echo -e "${YELLOW}📷 Procesando JPEG...${NC}"
find "$PROJECT_ROOT/$TARGET_DIR" -type f \( -iname "*.jpg" -o -iname "*.jpeg" \) | while read file; do
    # Saltar si ya tiene WebP más reciente
    webp_file="${file%.*}.webp"
    if [ -f "$webp_file" ] && [ "$webp_file" -nt "$file" ]; then
        echo -e "  ⏭ $(basename "$file") ya optimizado"
        continue
    fi
    optimize_jpeg "$file"
done

# Procesar PNG
echo ""
echo -e "${YELLOW}🖼 Procesando PNG...${NC}"
find "$PROJECT_ROOT/$TARGET_DIR" -type f -iname "*.png" | while read file; do
    webp_file="${file%.*}.webp"
    if [ -f "$webp_file" ] && [ "$webp_file" -nt "$file" ]; then
        echo -e "  ⏭ $(basename "$file") ya optimizado"
        continue
    fi
    optimize_png "$file"
done

echo ""
echo -e "${BLUE}══════════════════════════════════════════${NC}"
echo -e "${GREEN}✅ Optimización completada${NC}"
echo -e "   Archivos procesados: $FILES_PROCESSED"
echo -e "   Ahorro total estimado: ~$((TOTAL_SAVED/1024))KB"
echo -e "${BLUE}══════════════════════════════════════════${NC}"
