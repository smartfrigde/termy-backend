@echo off
echo Checking configuration

REM
IF NOT EXIST .env (
    echo The example configuration was used as configuration
    copy /Y .env.example .env
)

REM 
IF NOT EXIST laravel\.env (
    echo The example laravel configuration was used as configuration
    copy /Y laravel\.env.example laravel\.env
)

echo Initializing docker containers...

REM 
where docker-compose >nul 2>nul
IF %ERRORLEVEL% EQU 0 (
    echo Using docker-compose
    docker-compose up --build
) ELSE (
    REM 
    docker compose version >nul 2>nul
    IF %ERRORLEVEL% EQU 0 (
        echo Using docker compose up --build
        docker compose up --build
    ) ELSE (
        echo Error: Neither docker-compose nor docker compose was found. Please install Docker.
        exit /b 1
    )
)