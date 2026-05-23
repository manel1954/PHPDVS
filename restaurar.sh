#!/bin/bash
# Uso: restaurar.sh /ruta/al/fichero.zip
ZIPFILE="$1"

if [ -z "$ZIPFILE" ] || [ ! -f "$ZIPFILE" ]; then
  echo "ERROR:Fichero ZIP no encontrado"
  exit 1
fi

TMPDIR=$(mktemp -d)
unzip -q "$ZIPFILE" -d "$TMPDIR" 2>/dev/null

if [ $? -ne 0 ]; then
  rm -rf "$TMPDIR"
  echo "ERROR:No se pudo abrir el ZIP"
  exit 1
fi

declare -A DESTMAP=(
  ["MMDVMHost.ini"]="/home/pi/MMDVMHost/MMDVMHost.ini"
  ["MMDVMYSF.ini"]="/home/pi/MMDVMHost/MMDVMYSF.ini"
  ["MMDVMDSTAR.ini"]="/home/pi/MMDVMHost/MMDVMDSTAR.ini"
  ["MMDVMNXDN.ini"]="/home/pi/MMDVMHost/MMDVMNXDN.ini"
  ["DisplayDriver.ini"]="/home/pi/Display-Driver/DisplayDriver.ini"
  ["YSFGateway.ini"]="/home/pi/YSFClients/YSFGateway/YSFGateway.ini"
  ["DMRGateway.ini"]="/home/pi/DMRGateway/DMRGateway.ini"
  ["DStarGateway.ini"]="/home/pi/DStarGateway/DStarGateway.ini"
  ["NXDNGateway.ini"]="/home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini"
  ["station.cfg"]="/home/pi/radiosonde_auto_rx/auto_rx/station.cfg"
  ["rbfeeder.ini"]="/etc/rbfeeder.ini"
  ["fr24feed.ini"]="/etc/fr24feed.ini"
  ["ModuleEchoLink.conf"]="/usr/local/etc/svxlink/svxlink.d/ModuleEchoLink.conf"
  ["svxlink.conf"]="/usr/local/etc/svxlink/svxlink.conf"
  ["enlaces.json"]="/home/pi/.local/enlaces.json"
  ["AMBEserver.ini"]="/home/pi/AMBE_SERVER/AMBEserver.ini"
  ["dump1090.args"]="/home/pi/dump1090-fa/dump1090.args"
  ["bluetooth.sh"]="/home/pi/.local/bluetooth.sh"
)

RESTORED=()
ERRORS=()

for name in "${!DESTMAP[@]}"; do
  src="$TMPDIR/$name"
  dst="${DESTMAP[$name]}"
  if [ -f "$src" ]; then
    cp "$src" "$dst" 2>/dev/null
    if [ $? -eq 0 ]; then
      chmod 777 "$dst" 2>/dev/null
      RESTORED+=("$name")
    else
      ERRORS+=("$name")
    fi
  fi
done

# Restaurar carpeta logs/ con estructura completa
LOGSBASE="/home/pi/radiosonde_auto_rx/auto_rx/"
if [ -d "$TMPDIR/logs" ]; then
  find "$TMPDIR/logs" -type f | while read -r logfile; do
    relpath="${logfile#$TMPDIR/}"
    destfile="$LOGSBASE$relpath"
    destdir=$(dirname "$destfile")
    mkdir -p "$destdir" 2>/dev/null
    cp "$logfile" "$destfile" 2>/dev/null
    chmod 777 "$destfile" 2>/dev/null
  done
fi

rm -rf "$TMPDIR"

if [ ${#RESTORED[@]} -eq 0 ]; then
  echo "ERROR:No se encontraron ficheros compatibles"
  exit 1
fi

RESTORED_STR=$(IFS=', '; echo "${RESTORED[*]}")
if [ ${#ERRORS[@]} -gt 0 ]; then
  ERRORS_STR=$(IFS=', '; echo "${ERRORS[*]}")
  echo "OK:Restaurados: $RESTORED_STR | Errores: $ERRORS_STR"
else
  echo "OK:Restaurados: $RESTORED_STR"
fi