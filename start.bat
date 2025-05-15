@echo off
docker start termy-laravel
docker start termy-mysql
docker start termy-go

echo Servers are running

set /p composer="Do you want to run composer install? (y/n): "
if /i "%composer%"=="y" (
    docker exec -it termy-laravel composer install
)

set /p migrate="Do you want to run migrations? (y/n): "
if /i "%migrate%"=="y" (
    echo Running migrations in docker...
    docker exec -it termy-laravel php artisan migrate
)

set /p console="Do you want to enter the Laravel container console? (y/n): "
if /i "%console%"=="y" (
    echo Running docker container...
    docker exec -it termy-laravel bash
)
