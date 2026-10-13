web: composer install --optimize-autoloader --no-dev && npm install && npm run build && php artisan config:cache && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT
