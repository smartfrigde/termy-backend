#!/bin/bash
mkdir "app-logs"

ERROR_MESSAGE='\033[0;31m'
INFO_MESSAGE='\033[0;34m'
SUCCESS_MESSAGE='\033[0;32m'
NC='\033[0m'

echo -e "${INFO_MESSAGE}INFO:${NC} Starting Docker containers..."
docker start termy-laravel
docker start termy-mysql
docker start termy-go
echo -e "${SUCCESS_MESSAGE}SUCCESS:${NC} Servers are running."


read -r -p "$(echo -e "${INFO_MESSAGE}INFO:${NC} Do you want to run 'composer install'? (y/n) ")" composer
if [ "$composer" = "y" ]; then
    echo -e "${INFO_MESSAGE}INFO:${NC} Running composer install..."
    docker exec -it termy-laravel composer install > app-logs/composer.log
    echo -e "${SUCCESS_MESSAGE}SUCCESS:${NC} Composer install completed."
fi


read -r -p "$(echo -e "${INFO_MESSAGE}INFO:${NC} Do you want to run migrations? (y/n) ")" migrate
if [ "$migrate" = "y" ]; then
    echo -e "${INFO_MESSAGE}INFO:${NC} Running migrations in Docker..."
    docker exec -it termy-laravel php artisan migrate > app-logs/migrate.log
    echo -e "${SUCCESS_MESSAGE}SUCCESS:${NC} Migrations completed."
fi


read -r -p "$(echo -e "${INFO_MESSAGE}INFO:${NC} Enable Laravel websocket + queue manager? (y/n) ")" ws
if [ "$ws" != "n" ]; then
    echo -e "${INFO_MESSAGE}INFO:${NC} Starting queue worker and Reverb..."
    nohup docker exec termy-laravel php artisan queue:work > app-logs/queue.log 2>&1 &
    nohup docker exec termy-laravel php artisan reverb:start > app-logs/reverb.log 2>&1 &
    echo -e "${SUCCESS_MESSAGE}SUCCESS:${NC} WebSocket and queue worker running in background."
fi


read -r -p "$(echo -e "${INFO_MESSAGE}INFO:${NC} Enter Laravel container shell? (y/n) ")" console
if [ "$console" = "y" ]; then
    echo -e "${INFO_MESSAGE}INFO:${NC} Entering Laravel container..."
    docker exec -it termy-laravel bash
fi
