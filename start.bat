@echo off

docker start termy-laravel
docker start termy-mysql
docker start termy-go

echo Servers are running

set /p composer=Do you want run composer install? (y/n):

if /I "%composer%"=="y" (
    docker exec -it termy-laravel composer install
)

set /p migrate=Do you want to run migrations? (y/n):

if /I "%migrate%"=="y" (
    echo Running migrations in docker...
    docker exec -it termy-laravel php artisan migrate
)

set /p ws=Do you want to enable Laravel websocket for data synchronization (y/n):

if /I not "%ws%"=="n" (
    docker exec -it termy-laravel php artisan queue:work & php reverb:start
)

set /p console=Do you want to enter laravel container console? (y/n):

if /I "%console%"=="y" (
    echo Running docker container...
    docker exec -it termy-laravel bash
)