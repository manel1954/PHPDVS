#!/bin/bash
                      
                        # Cambio realizado el 14-04-2025 para actualizar los IDS en MMDVMHost 
                        cd /home/pi/MMDVMHost
                        curl --fail -o DMRIds.dat -s http://www.pistar.uk/downloads/DMRIds.dat
                        cp /home/pi/MMDVMHost/DMRIds.dat /home/pi/MMDVM_CM/DMR2YSF/DMRIds.dat
                        sudo chmod 777 /home/pi/MMDVMHost/DMRIds.dat
                        echo "*********************************************"
                        echo "         IDS actualizados correctamente"
                        echo "*********************************************"
                        sleep 3