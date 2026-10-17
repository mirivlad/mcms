#!/bin/sh
set -u

# Unprivileged CLI worker; neither Docker socket nor host cron is required.
while :; do
    php /var/www/html/spark mcms:updates:run || echo "mCMS: updater run failed" >&2
    sleep "${MCMS_UPDATE_INTERVAL_SECONDS:-21600}"
done
