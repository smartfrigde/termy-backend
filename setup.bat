@echo off

REM Install composer dependencies
composer install

REM Check if docker-compose is available
where docker-compose >nul 2>nul
if %ERRORLEVEL%==0 (
    echo Using docker-compose
    docker-compose up --build
    goto run_startup
)

REM Check if docker compose is available (Docker version 2.0+)
where docker >nul 2>nul
if %ERRORLEVEL%==0 (
    docker compose version >nul 2>nul
    if %ERRORLEVEL%==0 (
        echo Using docker compose up --build
        docker compose up --build
        goto run_startup
    )
)

REM Error handling if neither docker-compose nor docker compose was found
echo Error: Neither docker-compose nor docker compose was found. Please install Docker.
exit /b 1

