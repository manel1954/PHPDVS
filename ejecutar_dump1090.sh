#!/bin/bash

#stick=$(awk "NR==10" /home/pi/dump1090/dump1090.ini)
#raw=$(awk "NR==12" /home/pi/dump1090/dump1090.ini)
#ppm=$(awk "NR==14" /home/pi/dump1090/dump1090.ini)
#http=$(awk "NR==16" /home/pi/dump1090/dump1090.ini)
#gain=$(awk "NR==18" /home/pi/dump1090/dump1090.ini)
#beast=$(awk "NR==20" /home/pi/dump1090/dump1090.ini)
#index=$(awk "NR==22" /home/pi/dump1090/dump1090.ini)
#
#LOG=/tmp/dump1090.log
#PID_FILE=/tmp/dump1090.pid
#
## Matar instancia previa si existe
#if [ -f "$PID_FILE" ]; then
#    OLD_PID=$(cat "$PID_FILE")
#    kill "$OLD_PID" 2>/dev/null
#    rm -f "$PID_FILE"
#fi
#
#if [ "$stick" = 'RSP1' ]; then
#    /home/pi/dump1090_sdrplay/dump1090 --net --interactive --gain $gain --dev-sdrplay \
#        >> "$LOG" 2>&1 &
#
#elif [ "$gain" = '-10' ]; then
#    /home/pi/dump1090/dump1090 --device $index --net --interactive \
#        --net-ro-port $raw --net-bo-port $beast --gain $gain --ppm $ppm \
#        --net-http-port $http >> "$LOG" 2>&1 &
#else
#    /home/pi/dump1090/dump1090 --device $index --net --interactive \
#        --net-ro-port $raw --net-bo-port $beast --ppm $ppm \
#        --net-http-port $http >> "$LOG" 2>&1 &
#fi
#
#echo $! > "$PID_FILE"
#echo "dump1090 iniciado con PID $(cat $PID_FILE)"