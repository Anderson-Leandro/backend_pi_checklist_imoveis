#!/bin/sh
set -e

echo "[entrypoint] Rodando migrations..."
php /var/www/html/database/migrar.php

echo "[entrypoint] Rodando seed inicial..."
php /var/www/html/database/seeds/001_seed_inicial.php

echo "[entrypoint] Ajustando permissões do storage..."
chown -R www-data:www-data /var/www/html/storage

echo "[entrypoint] Iniciando PHP-FPM..."
exec "$@"
