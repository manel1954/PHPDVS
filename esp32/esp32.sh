#!/bin/bash

cd ~/esp32

if [ ! -d "esp32_env" ]; then
  python3 -m venv esp32_env
fi

source esp32_env/bin/activate

python3 esp32_littlefs.py
