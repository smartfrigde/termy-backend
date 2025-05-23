@echo off
mkdir app-logs 2>nul

echo INFO: Starting Docker containers...
docker start termy-laravel
docker start termy-mysql
docker start termy-go
echo SUCCESS: Servers are running.

set /p composer=INFO: Do you want to run 'composer install'? (y/n):
if /i "%composer%"=="y" (
    echo INFO: Running composer install...
    docker exec -it termy-laravel composer install > app-logs\composer.log
    echo SUCCESS: Composer install completed.
)

set /p migrate=INFO: Do you want to run migrations? (y/n):
if /i "%migrate%"=="y" (
    echo INFO: Running migrations in Docker...
    docker exec -it termy-laravel php artisan migrate > app-logs\migrate.log
    echo SUCCESS: Migrations completed.
)

set /p ws=INFO: Enable Laravel websocket + queue manager? (y/n):
if /i NOT "%ws%"=="n" (
    echo INFO: Starting queue worker and Reverb...
    start /B docker exec termy-laravel php artisan queue:work > app-logs\queue.log 2>&1
    start /B docker exec termy-laravel php artisan reverb:start > app-logs\reverb.log 2>&1
    echo SUCCESS: WebSocket and queue worker running in background.
)

set /p console=INFO: Enter Laravel container shell? (y/n):
if /i "%console%"=="y" (
    echo INFO: Entering Laravel container...
    docker exec -it termy-laravel bash
)