web: php -d variables_order=EGPCS -d display_errors=stderr -d error_reporting=E_ALL -S 0.0.0.0:${PORT:-8080} -t public/
release: php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache
