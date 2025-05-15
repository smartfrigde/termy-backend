#!/bin/bash

echo "Checking configuration"

if [ ! -f .env ]; then
    echo "The example configuration was used as configuration"
    cp .env.example .env
fi

if [ ! -f laravel/.env ]; then
    echo "The example laravel configuration was used as configuration"
    cp laravel/.env.example laravel/.env
fi

echo "Initializing docker containers..."

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