#!/bin/bash

# Configuración de rutas
REPO_DIR="/home/pi/PHPDVS"
TARGET_DIR="/home/pi/A108"
WEB_DIR="/var/www/html"
BACKUP_FILE="/tmp/password_backup.json"

export LANG=es_ES.UTF-8
export LC_ALL=es_ES.UTF-8
export LANGUAGE=es_ES:es

echo "🔄 Iniciando actualización segura..."

# 1. Entrar al repositorio
cd "$REPO_DIR" || { echo "❌ ERROR: No existe $REPO_DIR"; exit 1; }

# 2. Intentar actualizar. SI FALLA, el script se detiene AQUÍ sin tocar nada más
if ! git pull --force; then
    echo "❌ ERROR: Falló 'git pull'. Verifica la conexión a internet o permisos de git."
    echo "🛡️ Tu instalación actual NO ha sido modificada."
    exit 1
fi
echo "✅ Repositorio actualizado correctamente."

# 3. Backup de configuración crítica
if [ -f "$WEB_DIR/password.json" ]; then
    cp "$WEB_DIR/password.json" "$BACKUP_FILE"
    echo "💾 Backup de password.json guardado."
else
    echo "⚠️  No se encontró password.json, se omitirá el backup."
fi

# 4. Preparar directorio temporal (evita corrupción si algo falla a mitad)
TEMP_DIR=$(mktemp -d /tmp/PHPDVS_update.XXXXXX)
# Limpiar temp dir al salir (éxito o fallo)
trap 'rm -rf "$TEMP_DIR"; echo "🧹 Archivos temporales eliminados."' EXIT

echo "📦 Preparando nuevos archivos en entorno temporal..."
cp -a "$REPO_DIR"/. "$TEMP_DIR/"
rm -rf "$TEMP_DIR/html"  # Excluir html del staging

# 5. Reemplazar carpetas SOLO si todo lo anterior fue bien
echo "🔄 Aplicando cambios al sistema..."
sudo rm -rf "$TARGET_DIR"
sudo cp -a "$TEMP_DIR" "$TARGET_DIR"

sudo cp -a "$REPO_DIR/html/." "$WEB_DIR/"

# 6. Restaurar configuración protegida
if [ -f "$BACKUP_FILE" ]; then
    sudo cp "$BACKUP_FILE" "$WEB_DIR/password.json"
    echo "🔐 password.json restaurado correctamente."
fi

# 7. Permisos (ver nota de seguridad abajo)
sudo chmod -R 777 "$TARGET_DIR"
sudo chmod -R 777 "$WEB_DIR"

echo "🎉 Actualización completada con éxito."
exit 0



