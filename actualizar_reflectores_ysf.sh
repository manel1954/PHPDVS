#!/bin/bash
#Colores
ROJO="\033[1;31m"
VERDE="\033[1;32m"
BLANCO="\033[1;37m"
AMARILLO="\033[1;33m"
CIAN="\033[1;36m"
GRIS="\033[0m"
MARRON="\33[38;5;138m"

			cd /home/pi/
            wget --user-agent="EA3EIZ" https://hostfiles.refcheck.radio/YSFHosts.txt
            
            #wget --user-agent="MMDVM-Host/1.0 (Amateur Radio; EA4GAX; Spain)" https://hostfiles.refcheck.radio/YSFHosts.txt
            
            
            sudo mv /home/pi/YSFHosts.txt /home/pi/YSFClients/YSFGateway/
            
            cp /home/pi/YSFClients/YSFGateway/YSFHosts.txt /opt/fusion2x/data/
			sleep 3	