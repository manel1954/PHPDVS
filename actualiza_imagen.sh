#!/bin/bash

# ═══════════════════════════════════════════════════════════
#   PHPPLUS — Script de Actualización Segura
#   Associació ADER · EA3EIZ
# ═══════════════════════════════════════════════════════════

REPO_DIR="/home/pi/PHPDVS"
TARGET_DIR="/home/pi/A108"
WEB_DIR="/var/www/html"

export LANG=es_ES.UTF-8
export LC_ALL=es_ES.UTF-8
export LANGUAGE=es_ES:es

# ── Colores ──────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m'

# ── Helpers ───────────────────────────────────────────────
ok()   { echo -e "${GREEN}  ✔  ${NC}$1"; }
err()  { echo -e "${RED}  ✘  ${NC}$1"; }
info() { echo -e "${CYAN}  ➜  ${NC}$1"; }
warn() { echo -e "${YELLOW}  ⚠  ${NC}$1"; }
sep()  { echo -e "${DIM}  ────────────────────────────────────────${NC}"; }

# ── Cabecera ──────────────────────────────────────────────
echo ""
echo -e "${BOLD}${CYAN}  ╔══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${CYAN}  ║       📡  PHPDVS · Actualización         ║${NC}"
echo -e "${BOLD}${CYAN}  ╚══════════════════════════════════════════╝${NC}"
echo ""

START_TIME=$(date +%s)

# ── PASO 1: Repositorio ───────────────────────────────────
sep
info "Entrando al repositorio..."

cd "$REPO_DIR" || {
    err "No se encuentra el directorio: $REPO_DIR"
    echo ""
    exit 1
}

info "Descargando últimos cambios desde git..."
echo ""

if ! git pull 2>&1 | sed 's/^/        /'; then
    echo ""
    err "Falló la conexión con git."
    warn "Tu instalación actual NO ha sido modificada."
    echo ""
    exit 1
fi

echo ""
ok "Repositorio actualizado."

# ── PASO 2: Sincronizar A108 ──────────────────────────────
sep
info "Sincronizando directorio de trabajo..."

if sudo rsync -a --delete \
    "$REPO_DIR/" "$TARGET_DIR/" 2>/dev/null; then
    ok "Directorio $TARGET_DIR sincronizado."
else
    err "Error al sincronizar $TARGET_DIR."
    echo ""
    exit 1
fi

# ── PASO 3: Desplegar web ─────────────────────────────────
sep
info "Desplegando archivos web..."

if sudo rsync -a \
    --exclude='password.json' \
    "$REPO_DIR/html/" "$WEB_DIR/" 2>/dev/null; then
    ok "Archivos web desplegados en $WEB_DIR."
else
    err "Error al desplegar archivos web."
    echo ""
    exit 1
fi

# ── PASO 4: Permisos ──────────────────────────────────────
sep
info "Aplicando permisos..."

sudo chown -R www-data:www-data "$WEB_DIR" 2>/dev/null
sudo chmod -R 777 "$WEB_DIR" 2>/dev/null
ok "Permisos web aplicados (777)."

sudo chmod -R 777 "$TARGET_DIR" 2>/dev/null
ok "Permisos de trabajo aplicados (777)."

# ── Resumen final ─────────────────────────────────────────
sep
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))

echo ""
echo -e "${BOLD}${GREEN}  ╔══════════════════════════════════════════╗${NC}"
echo -e "${BOLD}${GREEN}  ║   🎉  Actualización completada           ║${NC}"
echo -e "${BOLD}${GREEN}  ║   ⏱  Tiempo: ${ELAPSED}s$(printf '%*s' $((24 - ${#ELAPSED})) '')║${NC}"
echo -e "${BOLD}${GREEN}  ╚══════════════════════════════════════════╝${NC}"
echo ""

exit 0