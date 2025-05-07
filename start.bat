@echo off

REM
docker start termy-laravel
docker start termy-mysql

echo Servers are running

REM
set /p migrate=Do you want to run migrations? (y/n):
if /i "%migrate%"=="y" (
    echo Running migrations in docker...
    docker exec -it termy-laravel php artisan migrate
)

REM
set /p console=Do you want to enter the project console? (y/n):
if /i "%console%"=="y" (
    echo Running docker container...
    docker exec -it termy-laravel bash
)
