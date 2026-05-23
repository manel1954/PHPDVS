#!/bin/bash
ZIPPATH="/tmp/Copia_PHPPLUS.zip"
rm -f "$ZIPPATH"

zip -j "$ZIPPATH" \
  /home/pi/MMDVMHost/MMDVMHost.ini \
  /home/pi/MMDVMHost/MMDVMYSF.ini \
  /home/pi/MMDVMHost/MMDVMDSTAR.ini \
  /home/pi/MMDVMHost/MMDVMNXDN.ini \
  /home/pi/Display-Driver/DisplayDriver.ini \
  /home/pi/YSFClients/YSFGateway/YSFGateway.ini \
  /home/pi/DMRGateway/DMRGateway.ini \
  /home/pi/DStarGateway/DStarGateway.ini \
  /home/pi/NXDNClients/NXDNGateway/NXDNGateway.ini \
  /home/pi/radiosonde_auto_rx/auto_rx/station.cfg \
  /etc/rbfeeder.ini \
  /etc/fr24feed.ini \
  /usr/local/etc/svxlink/svxlink.d/ModuleEchoLink.conf \
  /usr/local/etc/svxlink/svxlink.conf \
  /home/pi/.local/enlaces.json \
  /home/pi/AMBE_SERVER/AMBEserver.ini \
  /home/pi/dump1090-fa/dump1090.args \
  /home/pi/.local/bluetooth.sh \
  2>/dev/null

cd /home/pi/radiosonde_auto_rx/auto_rx && zip -r "$ZIPPATH" logs/ 2>/dev/null

if [ -f "$ZIPPATH" ]; then
  echo "OK:$ZIPPATH"
else
  echo "ERROR:No se pudo crear el ZIP"
  exit 1
fi
