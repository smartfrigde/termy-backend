@echo off
setlocal enabledelayedexpansion

REM
if not exist "app-logs" (
    mkdir "app-logs"
)

echo INFO: Checking configuration...

REM
if not exist ".env" (
    copy .env.example .env >nul
    echo INFO: Created .env from .env.example
)

REM

echo INFO: Initializing Docker containers...

REM
where docker-compose >nul 2>&1
if %errorlevel%==0 (
    echo INFO: Using docker-compose
    docker-compose up --build --no-start > app-logs\compose.log
) else (
    docker --version >nul 2>&1
    if %errorlevel%==0 (
        docker compose version >nul 2>&1
        if %errorlevel%==0 (
            echo INFO: Using docker compose
            docker compose up --build --no-start > app-logs\compose.log
        ) else (
            echo ERROR: Docker Compose v2 not found. Please install it.
            exit /b 1
        )
    ) else (
        echo ERROR: Docker is not installed. Please install Docker.
        exit /b 1
    )
)

echo SUCCESS: Initialization complete.