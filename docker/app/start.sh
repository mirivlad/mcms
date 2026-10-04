#!/bin/sh
set -eu

php spark mcms:update

if [ "${MCMS_DEMO_SEED:-0}" = "1" ]; then
    php spark mcms:demo:seed
fi

exec php-fpm
