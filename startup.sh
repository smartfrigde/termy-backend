echo "Do you use docker? (y/n)"

read docker

if [ "$docker" = "y" ]; then
    echo "Starting docker container..."
    sudo docker start backend-laravel-1
    sudo docker start backend-mysql-1
else
    echo "Starting local server..."
    python3 -m http.server 8080
fi

echo "Do you want to run migrations? (y/n)"
read migrate

if [ "$migrate" = "y" ]; then
    if [ "$docker" = "y" ]; then
        echo "Running migrations in docker..."
        sudo docker exec -it backend-laravel-1 php artisan migrate
    else
        echo "Running migrations locally..."
        php artisan migrate
    fi
fi

if [ "$docker" = "y" ]; then
    echo "Do you want enter to project console? (y/n)"
    read console
else
    console="n"
fi

if [ "$console" = "y" ]; then
    echo "Running docker container..."
    sudo docker exec -it backend-laravel-1 bash
else
    echo "Running local server..."
    php artisan serve
fi
