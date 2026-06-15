#!/bin/bash

# ==========================================================
# Actualización de DMR IDs para MMDVM y NXDN.csv para NXDN
# ==========================================================

printf "🔄 Iniciando actualización de DMR IDs y NXDN IDs...\n"

# Ajuste de permisos
printf "🔧 Ajustando permisos en directorios MMDVM...\n"
sudo chmod 777 -R /home/pi/MMDVMHost > /dev/null 2>&1
sudo chmod 777 -R /home/pi/MMDVM_CM > /dev/null 2>&1
sudo chmod 777 -R /home/pi/NXDNClients/NXDNGateway > /dev/null 2>&1
sudo chmod 777 -R /home/pi/MMDVM_CM/DMR2NXDN > /dev/null 2>&1

printf "✅ Permisos configurados correctamente.\n"

# Descarga del archivo
cd /home/pi/MMDVMHost || { printf "❌ Error: No se puede acceder a MMDVMHost\n"; exit 1; }

printf "📥 Descargando DMRIds.dat desde pi-star.uk...\n"
if curl --fail -o DMRIds.dat -s http://www.pistar.uk/downloads/DMRIds.dat; then
    printf "✅ Descarga completada con éxito.\n"
else
    printf "❌ Error al descargar el archivo.\n"
    exit 1
fi

cd /home/pi/NXDNClients/NXDNGateway || { printf "❌ Error: No se puede acceder a NXDNClients/NXDNGateway\n"; exit 1; }

printf "📥 Descargando NXDN.csv desde pi-star.uk...\n"
if curl --fail -o NXDN.csv -s http://www.pistar.uk/downloads/NXDN.csv; then
    printf "✅ Descarga completada con éxito.\n"
else
    printf "❌ Error al descargar el archivo.\n"
    exit 1
fi

# Distribución a los puentes
printf "📤 Distribuyendo DMRIds.dat a MMDVMHost y puentes...\n"
cp /home/pi/MMDVMHost/DMRIds.dat /home/pi/MMDVM_CM/DMR2YSF/DMRIds.dat && \
cp /home/pi/MMDVMHost/DMRIds.dat /home/pi/MMDVM_CM/YSF2DMR/DMRIds.dat && \
cp /home/pi/MMDVMHost/DMRIds.dat /home/pi/MMDVM_CM/DMR2NXDN/DMRIds.dat && \
cp /home/pi/NXDNClients/NXDNGateway/NXDN.csv /home/pi/MMDVM_CM/DMR2NXDN/NXDN.csv


if [ $? -eq 0 ]; then
    printf "✅ Archivo copiado en MMDVMHost, DMR2YSF, YSF2DMR, DMR2NXDN, NXDNGateway.\n"
else
    printf "❌ Error al copiar los archivos.\n"
    exit 1
fi

# Finalización
printf "🎉 DMR IDs actualizadas correctamente.\n"
printf "🎉 NXDN IDs actualizadas correctamente.\n"
sleep 3
