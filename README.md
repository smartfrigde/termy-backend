<div align="center">
  <h1>Termy Backend</h1>
</div>

<p>
  Termy Frontend can be find
  <a href="https://github.com/smartfrigde/termy">
    here
  </a>
</p>

<p>
  Documentation can be find
  <a href="https://deepwiki.com/smartfrigde/termy-backend">
    here
  </a>
</p>

##
<p>
  If you want start project with docker read
  <a href="#-running-with-docker">
    Running with Docker
  </a>
</p>


---

## ✅ Requirements

- **Docker** → [Download here](https://www.docker.com)
- **PHP 8+** (if you want to run `php artisan serve` locally)
- **bash** (if you are using any additional `.sh` scripts)
- **Optional:** `docker-compose`, if you are not using `docker compose`

---

## 🐳 Running with Docker
### Build containers
#### method 1 - automatic

```
./setup.sh
```

or

```
./setup.bat
```

#### method 2 - manual

1. Copy .env file from .env.example (if file .env not exist)
```
cp .env.example .env
```

2. Copy .env file in the Laravel folder from .env.example in the Laravel folder (if file laravel/.env not exist)
```
laravel/.env.example laravel/.env
```

3. Create containers
```
docker-compose up --build
```
or
```
doker compose up --build
```


### Run containers
#### method 1 - automatic

```
./start.sh
```

or

```
./start.bat
```

#### method 2 - manual

1. Run all docker containers
```
docker start termy-go
docker start termy-mysql
docker start termy-laravel
```
2. Install composer requements (optional if is not installed)
```
docker exec -it termy-laravel composer install
```
3. Migrate database (optional if is not migrated)
```
docker exec -it termy-laravel php artisan migrate
```
4. Run laravel ws
```
docker exec -it termy-laravel php artisan reverb:start
```
5. Enter to laravel conlose (optional)
```
docker exec -it termy-laravel bash
```