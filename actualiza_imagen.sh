#!/bin/bash

git config --global --add safe.directory /home/pi/PHPDVS
cd /home/pi/PHPDVS                                             
git pull --force

# Guardar password.json ANTES de tocar nada
cp /var/www/html/password.json /tmp/password_backup.json

sudo rm -R /home/pi/A108
mkdir /home/pi/A108                                                
cp -R /home/pi/PHPDVS/* /home/pi/A108
sudo rm -R /home/pi/A108/html
cp -R /home/pi/PHPDVS/html/ /var/www/

# Restaurar password.json real (sobreescribe el del github)
cp /tmp/password_backup.json /var/www/html/password.json

sleep 6                                             
sudo chmod 777 -R /home/pi/A108   
sudo chmod 777 -R /var/www/html