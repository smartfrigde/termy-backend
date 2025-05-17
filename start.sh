
docker start termy-laravel
docker start termy-mysql
docker start termy-go

echo "Servers are running"

echo "Do you want run composer install?"
read composer

if [ "$composer" = "y" ]; then
    docker exec -it termy-laravel composer install
fi

echo "Do you want to run migrations? (y/n)"
read migrate

if [ "$migrate" = "y" ]; then
    echo "Running migrations in docker..."
    docker exec -it termy-laravel php artisan migrate
fi

echo "Do you want to enable Laravel websocket for data synchronization (y/n)"
read ws

if [ "$ws" != "n" ]; then
    docker exec -it termy-laravel php artisan reverb:start
fi

echo "Do you want enter to laravel container console? (y/n)"
read console
if [ "$console" = "y" ]; then
    echo "Running docker container..."
    docker exec -it termy-laravel bash
fi