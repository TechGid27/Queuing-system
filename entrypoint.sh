#!/bin/sh
set -u

log() { printf '[boot] %s\n' "$*"; }

PORT="${PORT:-10000}"

mkdir -p \
    storage/logs \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/public \
    bootstrap/cache 2>/dev/null || true

php artisan storage:link >/dev/null 2>&1 || true

if [ -z "${APP_KEY:-}" ] && ! grep -qs '^APP_KEY=.' .env 2>/dev/null; then
    log "WARN: APP_KEY is not set - encrypted cookies/sessions will fail"
fi

log "running migrations..."
if ! php artisan migrate --force; then
    log "WARN: migrate failed - check DATABASE_URL / DB_* variables"
fi

log "caching config, routes and views..."
php artisan config:cache || log "WARN: config:cache failed"
php artisan route:cache || log "WARN: route:cache failed"
php artisan view:cache || log "WARN: view:cache failed"

php artisan db:seed --force || log "WARN: db:seed failed (skipped)"

(php artisan schedule:work >> storage/logs/scheduler.log 2>&1 &) || true

log "serving on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
