# Termy Backend

# Termy Laravel - Running the project with Docker (Windows)

This project uses Docker to run the Laravel + MySQL environment. The `.bat` scripts automate the entire process, but you can also perform everything manually.

---

## 📁 Contents

- `setup.bat` – runs `docker-compose` or `docker compose`, depending on your Docker version.
- `start.bat` – starts the containers, asks about migrations and entering the project console.

---

## ✅ Requirements

- **Composer** →  [Download here](https://getcomposer.org/download/)
- **Docker Desktop for Windows** → [Download here](https://www.docker.com/products/docker-desktop)
- **PHP 8+** (if you want to run `php artisan serve` locally)
- **bash** (if you are using any additional `.sh` scripts)
- **Optional:** `docker-compose`, if you are not using `docker compose`

---

## 🚀 Running the project with the scripts

### 1. Build the containers

```cmd
setup.bat
```

### 2. Launching the project
the script will ask whether to migrate and go to the project console, its actions will depend on the user's response
```cmd
start.sh
```

## ⚙️ Manual start
### 1. Creating containers
```
composer install
docker-compose up --build
```
or
```
composer install
docker compose up --build
```
depending on the docker version

### 2. Launching the project
launching containers
```
docker start termy-laravel
docker start termy-mysql
````
migrate the database (optional, recommended for first run)
```
docker exec -it termy-laravel php artisan migrate
```
go to the project console (optional)
```
docker exec -it termy-laravel bash
```

----


# Termy Laravel - Running the project with Docker (Mac)

This project uses Docker to run the Laravel + MySQL environment. The `.sh` scripts automate the entire process, but you can also perform everything manually.

---

## 📁 Contents

- `setup.sh` – runs `docker-compose` or `docker compose`, depending on your Docker version.
- `start.sh` – starts the containers, asks about migrations and entering the project console.

---

## ✅ Requirements

- **Composer** →  [Download here](https://getcomposer.org/download/)
- **Docker Desktop for Mac** → [Download here](https://www.docker.com/products/docker-desktop/)
- **PHP 8+** (if you want to run `php artisan serve` locally)
- **bash** (if you are using any additional `.sh` scripts)
- **Optional:** `docker-compose`, if you are not using `docker compose`

---

## 🚀 Running the project with the scripts

### 1. Build the containers

```cmd
setup.sh
```

### 2. Launching the project
the script will ask whether to migrate and go to the project console, its actions will depend on the user's response
```cmd
start.sh
```

## ⚙️ Manual start
### 1. Creating containers
```
composer install
docker-compose up --build
```
or
```
composer install
docker compose up --build
```
depending on the docker version

### 2. Launching the project
launching containers
```
docker start termy-laravel
docker start termy-mysql
````
migrate the database (optional, recommended for first run)
```
docker exec -it termy-laravel php artisan migrate
```
go to the project console (optional)
```
docker exec -it termy-laravel bash
```

# Termy Laravel - Running the project with Docker (Linux)

This project uses Docker to run the Laravel + MySQL environment. The `.sh` scripts automate the entire process, but you can also perform everything manually.

---

## 📁 Contents

- `setup.sh` – runs `docker-compose` or `docker compose`, depending on your Docker version.
- `start.sh` – starts the containers, asks about migrations and entering the project console.

---

## ✅ Requirements

- **Composer** →  [Download here](https://getcomposer.org/download/)
- **Docker-cli for Linux** → [Download here](https://docs.docker.com/desktop/setup/install/linux/)
- **PHP 8+** (if you want to run `php artisan serve` locally)
- **bash** (if you are using any additional `.sh` scripts)
- **Optional:** `docker-compose`, if you are not using `docker compose`

---

## 🚀 Running the project with the scripts

### 1. Build the containers

```cmd
setup.sh
```

### 2. Launching the project
the script will ask whether to migrate and go to the project console, its actions will depend on the user's response
```cmd
start.sh
```

## ⚙️ Manual start
### 1. Creating containers
```
composer install
docker-compose up --build
```
or
```
composer install
docker compose up --build
```
depending on the docker version

### 2. Launching the project
launching containers
```
docker start termy-laravel
docker start termy-mysql
````
migrate the database (optional, recommended for first run)
```
docker exec -it termy-laravel php artisan migrate
```
go to the project console (optional)
```
docker exec -it termy-laravel bash
```

