#!/bin/bash

# Ensure config mount directory exists
mkdir -p ./opt/mtg

# If the config file doesn't exist on host, copy from template
if [ ! -f ./opt/mtg/mtg_new.ini ]; then
    echo "Creating editable config file from template..."
    cp ./setup/mtg_new.ini ./opt/mtg/mtg_new.ini
fi

# Now start Docker
docker-compose up --build -d
