
docker start termy-laravel
docker start termy-mysql

echo "Servers are running"

echo "Do you want to run migrations? (y/n)"
read migrate

if [ "$migrate" = "y" ]; then
    echo "Running migrations in docker..."
    docker exec -it termy-laravel php artisan migrate
fi

echo "Do you want enter to project console? (y/n)"
read console

if [ "$console" = "y" ]; then
    echo "Running docker container..."
    docker exec -it termy-laravel bash
fi
