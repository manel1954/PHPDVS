#!/bin/bash
set -e

# ── Entorno para git ──────────────────────────────────────────
export HOME=/home/pi
export GIT_CONFIG_NOSYSTEM=1
export GIT_TERMINAL_PROMPT=0

echo "▶ Iniciando actualización PHPDVS..."

# ── 1. Actualizar repositorio ─────────────────────────────────
echo "▶ Actualizando repositorio git..."
git config --global --add safe.directory /home/pi/PHPDVS 2>/dev/null || true
cd /home/pi/PHPDVS
git fetch --all
git reset --hard origin/main
echo "✔ Repositorio actualizado"

# ── 2. Backup de ficheros protegidos ─────────────────────────
echo "▶ Guardando ficheros protegidos..."
[ -f /var/www/html/password.json ] && cp /var/www/html/password.json /tmp/password_backup.json
[ -f /var/www/html/enlaces.json ]  && cp /var/www/html/enlaces.json  /tmp/enlaces_backup.json
echo "✔ Backup realizado"

# ── 3. Preparar directorio A108 ───────────────────────────────
echo "▶ Preparando /home/pi/A108..."
rm -rf /home/pi/A108
mkdir -p /home/pi/A108
cp -R /home/pi/PHPDVS/* /home/pi/A108/
rm -rf /home/pi/A108/html
echo "✔ A108 listo"

# ── 4. Desplegar en /var/www/html ────────────────────────────
echo "▶ Copiando ficheros web..."
cp -R /home/pi/PHPDVS/html/ /var/www/
echo "✔ Ficheros web copiados"

# ── 5. Restaurar ficheros protegidos ─────────────────────────
echo "▶ Restaurando ficheros protegidos..."
[ -f /tmp/password_backup.json ] && cp /tmp/password_backup.json /var/www/html/password.json
[ -f /tmp/enlaces_backup.json ]  && cp /tmp/enlaces_backup.json  /var/www/html/enlaces.json
echo "✔ Ficheros protegidos restaurados"

# ── 6. Permisos ───────────────────────────────────────────────
echo "▶ Aplicando permisos..."
chmod 755 -R /home/pi/A108
chmod 755 -R /var/www/html
chown -R www-data:www-data /var/www/html
echo "✔ Permisos aplicados"

echo ""
echo "✔ Actualización completada correctamente"