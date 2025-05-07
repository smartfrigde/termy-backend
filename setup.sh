#!/bin/bash

# Sprawdź, czy docker-compose jest dostępne
if command -v docker-compose &> /dev/null; then
    echo "Using docker-compose"
    docker-compose up --build
elif command -v docker &> /dev/null && docker compose version &> /dev/null; then
    echo "Using docker compose up --build"
    docker compose up --build
else
    echo "Error: Neither docker-compose nor docker compose was found. Please install Docker."
    exit 1
fi

./startup.sh
echo "Starting the application..."

