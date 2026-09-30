#!/bin/sh
set -eu

echo "Applying mCMS core migrations..."
php spark migrate

echo "Applying CodeIgniter Settings migrations..."
php spark migrate -n CodeIgniter\\Settings

echo "Applying CodeIgniter Shield migrations..."
php spark migrate -n CodeIgniter\\Shield

exec php-fpm
