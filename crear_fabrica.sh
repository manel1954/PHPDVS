#!/bin/bash
# crear_fabrica.sh
# Empaqueta ./fabrica/ en fabrica.zip listo para restaurar_de_fabrica.sh
#
# Estructura esperada:
#   /home/pi/.local/fabrica/    ← pon aquí tus ficheros ya editados
#   /home/pi/.local/fabrica.zip ← se genera aquí

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_DIR="$SCRIPT_DIR/fabrica"
ZIPFILE="$SCRIPT_DIR/fabrica.zip"

# Ficheros reconocidos por restaurar_de_fabrica.sh
VALID_FILES=(
  "MMDVMHost.ini"
  "MMDVMYSF.ini"
  "MMDVMDSTAR.ini"
  "MMDVMNXDN.ini"
  "DisplayDriver.ini"
  "YSFGateway.ini"
  "DMRGateway.ini"
  "DStarGateway.ini"
  "NXDNGateway.ini"
  "station.cfg"
  "rbfeeder.ini"
  "fr24feed.ini"
  "ModuleEchoLink.conf"
  "svxlink.conf"
  "enlaces.json"
  "AMBEserver.ini"
  "dump1090.args"
  "bluetooth.sh"
)

if [ ! -d "$SRC_DIR" ]; then
  echo "ERROR: No existe la carpeta fabrica/ en $SCRIPT_DIR"
  echo "       Créala y pon ahí los ficheros que quieres como estado de fábrica."
  exit 1
fi

echo "=== Leyendo ficheros desde: $SRC_DIR ==="
echo ""

INCLUDED=()
SKIPPED=()
UNKNOWN=()

for name in "${VALID_FILES[@]}"; do
  if [ -f "$SRC_DIR/$name" ]; then
    INCLUDED+=("$name")
    echo "  [+] $name"
  else
    SKIPPED+=("$name")
    echo "  [ ] $name  (no está en fabrica/)"
  fi
done

# Avisar de ficheros no reconocidos presentes en fabrica/
for f in "$SRC_DIR"/*; do
  fname=$(basename "$f")
  [ -d "$f" ] && continue
  found=0
  for v in "${VALID_FILES[@]}"; do
    [ "$fname" = "$v" ] && found=1 && break
  done
  [ $found -eq 0 ] && UNKNOWN+=("$fname")
done

if [ ${#UNKNOWN[@]} -gt 0 ]; then
  echo ""
  echo "  [!] Ficheros en fabrica/ no reconocidos por el restaurador (se incluyen igualmente):"
  for u in "${UNKNOWN[@]}"; do
    echo "       - $u"
  done
fi

if [ ${#INCLUDED[@]} -eq 0 ]; then
  echo ""
  echo "ERROR: No hay ningún fichero válido en fabrica/"
  exit 1
fi

[ -d "$SRC_DIR/logs" ] && echo "  [+] logs/  (radiosonde)"

[ -f "$ZIPFILE" ] && rm -f "$ZIPFILE"

cd "$SRC_DIR" || exit 1
zip -q -r "$ZIPFILE" . 2>/dev/null
ZIP_OK=$?
cd - > /dev/null

if [ $ZIP_OK -ne 0 ]; then
  echo ""
  echo "ERROR: No se pudo crear fabrica.zip"
  exit 1
fi

echo ""
echo "=== fabrica.zip creado correctamente ==="
echo "    Ubicación : $ZIPFILE"
echo "    Incluidos : ${#INCLUDED[@]} ficheros"
[ ${#SKIPPED[@]} -gt 0 ] && echo "    Omitidos  : $(IFS=', '; echo "${SKIPPED[*]}")"