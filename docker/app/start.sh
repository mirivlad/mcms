#!/bin/sh
set -eu

php spark mcms:update

exec php-fpm
