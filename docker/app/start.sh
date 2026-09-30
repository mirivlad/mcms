#!/bin/sh
set -eu

echo "Applying mCMS database migrations..."
php spark migrate --all

exec php-fpm
