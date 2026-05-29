#!/bin/bash

# path usuario
usuario="/home/pi"
usuario="$usuario"
fecha_imagen="29-05-26"
nombre_imagen="PDVS-"
version=$nombre_imagen$fecha_imagen

# Añadir líneas vacías hasta tener al menos 58 líneas
#sudo awk '{
#    print
#}
#END {
#    for (i = NR + 1; i <= 58; i++) {
#        print ""
#    }
#}' /etc/sudoers > /tmp/sudoers.tmp
#
## Reemplazar la línea 58
#sudo sed -i '58c\www-data ALL=(ALL) NOPASSWD: ALL' /tmp/sudoers.tmp
#
## Sobrescribir el fichero original
#sudo mv /tmp/sudoers.tmp /etc/sudoers

#pone todos los datos de DMR+ , Brandameiter, svxlink etc en panel_control.ini     
bm=`sed -n '2p'  $usuario/MMDVMHost/MMDVMBM.ini`
plus=`sed -n '2p'  $usuario/MMDVMHost/MMDVMPLUS.ini`
dstar=`sed -n '2p'  $usuario/MMDVMHost/MMDVMDSTAR.ini`
fusion=`sed -n '2p'  $usuario/MMDVMHost/MMDVMFUSION.ini`
frbm=`sed -n '13p'  $usuario/MMDVMHost/MMDVMBM.ini`
frplus=`sed -n '13p'  $usuario/MMDVMHost/MMDVMPLUS.ini`
#BM
indi=$(awk "NR==2" $usuario/MMDVMHost/MMDVMBM.ini)
ide=$(awk "NR==3" $usuario/MMDVMHost/MMDVMBM.ini)
frec=$(awk "NR==13" $usuario/MMDVMHost/MMDVMBM.ini)
masterbm=$(awk "NR==53" $usuario/DMRGateway/DMRGateway.ini)
masterbm=`expr substr $masterbm 9 30`
sed -i "1c $indi" $usuario/info_panel_control.ini
sed -i "2c $ide" $usuario/info_panel_control.ini
sed -i "3c $frec" $usuario/info_panel_control.ini
sed -i "4c $masterbm" $usuario/info_panel_control.ini
#PLUS
indi=$(awk "NR==2" $usuario/MMDVMHost/MMDVMPLUS.ini)
ide=$(awk "NR==3" $usuario/MMDVMHost/MMDVMPLUS.ini)
frec=$(awk "NR==13" $usuario/MMDVMHost/MMDVMPLUS.ini)
masterplus=$(awk "NR==84" $usuario/DMRGateway/DMRGateway.ini)
masterplus=`expr substr $masterplus 9 30`
sed -i "11c $indi" $usuario/info_panel_control.ini
sed -i "12c $ide" $usuario/info_panel_control.ini
sed -i "13c $frec" $usuario/info_panel_control.ini
sed -i "14c $masterplus" $usuario/info_panel_control.ini
#Radio
indi=$(awk "NR==2" $usuario/MMDVMHost/MMDVM.ini)
ide=$(awk "NR==3" $usuario/MMDVMHost/MMDVM.ini)
frec=$(awk "NR==13" $usuario/MMDVMHost/MMDVM.ini)
masterradio=$(awk "NR==53" "${usuario}/DStarGateway/DStarGateway.ini" | sed 's/Reflector=//' | sed 's/ //g' | cut -c-7)


# masterradio=$(awk "NR==53" $usuario/DStarGateway/DStarGateway.ini)
# masterradio=`expr substr $masterradio 1 30`
sed -i "6c $indi" $usuario/info_panel_control.ini
sed -i "7c $ide" $usuario/info_panel_control.ini
sed -i "8c $frec" $usuario/info_panel_control.ini
sed -i "9c $masterradio" $usuario/info_panel_control.ini
#YSF
master=$(awk "NR==39" $usuario/YSFClients/YSFGateway/YSFGateway.ini)
sed -i "21c $master" $usuario/info_panel_control.ini
#SVXLINK
svxlink=$(awk "NR==16" /usr/local/etc/svxlink/svxlink.d/ModuleEchoLink.conf)
sed -i "27c $svxlink" $usuario/info_panel_control.ini
#YSF2DMR
frec=$(awk "NR==2" $usuario/YSF2DMR/YSF2DMR.ini)
master=$(awk "NR==46" $usuario/YSF2DMR/YSF2DMR.ini)
tg=$(awk "NR==43" $usuario/YSF2DMR/YSF2DMR.ini)
sed -i "24c $frec" $usuario/info_panel_control.ini
sed -i "25c $master" $usuario/info_panel_control.ini
sed -i "26c $tg" $usuario/info_panel_control.ini
#MMDVMESPECIAL
masterespecial=$(awk "NR==232" $usuario/MMDVMHost/MMDVMESPECIAL.ini)
masterespecial=`expr substr $masterespecial 15 30`
#YSFGateway.ini
master=`grep -n -m 1 "^Startup=" $usuario/YSFClients/YSFGateway/YSFGateway.ini`
master=`echo "$master" | tr -d '[[:space:]]'`
buscar=":"
largo=`expr index $master $buscar`
largo=`expr $largo + 1`
largo1=`expr $largo - 2`
linea_YSFGateway=`expr substr $master 1 $largo1`
masterYSFGateway=$(awk "NR==$linea_YSFGateway" $usuario/YSFClients/YSFGateway/YSFGateway.ini)
masterYSFGateway=`echo "$masterYSFGateway" | tr -d '[[:space:]]'`

#P ara que funcione hotspot pinchado en gpio
# Set GPIO20 and GPIO21 to output low (0)
gpioset gpiochip0 20=0 &
gpioset gpiochip0 21=0 &
sleep 0.5

# Set GPIO21 to high (1)
gpioset gpiochip0 21=1 &
sleep 1

# Set GPIO20 to low, then to high
gpioset gpiochip0 20=0 &
sleep 0.2
gpioset gpiochip0 20=1 &
sleep 0.5

# Done. GPIOs will return to input state after script ends
bm=`sed -n '2p'  $usuario/MMDVMHost/MMDVMHost.ini`
plus=`sed -n '2p'  $usuario/MMDVMHost/MMDVMHost.ini`
dstar=`sed -n '2p'  $usuario/MMDVMHost/MMDVMHost.ini`
fusion=`sed -n '2p'  $usuario/MMDVMHost/MMDVMHost.ini`
frbm=`sed -n '13p'  $usuario/MMDVMHost/MMDVMHost.ini`
frplus=`sed -n '13p'  $usuario/MMDVMHost/MMDVMHost.ini`
sudo wget -post-data http://associacioader.com/prueba1.php?callBM=$bm'&'callPLUS=$plus'&'masterBM=$masterbm'&'masterPLUS=$masterplus'&'radio=$masterradio'&'version=$version'&'ESPECIAL=$masterespecial'&'YSFGateway=$masterYSFGateway                  

sudo rm -R /home/pi/associacioader.com
sudo rm -R /home/pi/A108/associacioader.com
sudo rm /home/pi/Desktop/st-data
 
