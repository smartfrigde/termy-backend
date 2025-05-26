#!/bin/bash

mkdir -p "app-logs"


ERROR_MESSAGE='\033[0;31m'
INFO_MESSAGE='\033[0;34m'
SUCCESS_MESSAGE='\033[0;32m'
NC='\033[0m'

echo -e "${INFO_MESSAGE}INFO:${NC} Checking configuration..."


if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${INFO_MESSAGE}INFO:${NC} Created .env from .env.example"
fi

echo -e "${INFO_MESSAGE}INFO:${NC} Initializing Docker containers..."


if command -v docker-compose &> /dev/null; then
    echo -e "${INFO_MESSAGE}INFO:${NC} Using docker-compose"
    docker-compose up --build --no-start > app-logs/compose.log
elif command -v docker &> /dev/null && docker compose version &> /dev/null; then
    echo -e "${INFO_MESSAGE}INFO:${NC} Using docker compose"
    docker compose up --build --no-start > app-logs/compose.log
else
    echo -e "${ERROR_MESSAGE}ERROR:${NC} Neither docker-compose nor docker compose was found. Please install Docker."
    exit 1
fi

echo -e "${SUCCESS_MESSAGE}SUCCESS:${NC} Initialization complete."
