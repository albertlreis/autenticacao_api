#!/usr/bin/env sh
set -eu

mkdir -p \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/testing \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/app/public \
  /var/www/html/storage/logs \
  /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R ug+rwX /var/www/html/storage /var/www/html/bootstrap/cache

configured_env="${APP_ENV:-$(php -r '$values = @parse_ini_file("/var/www/html/.env", false, INI_SCANNER_RAW) ?: []; echo $values["APP_ENV"] ?? "";')}"
configured_debug="${APP_DEBUG:-$(php -r '$values = @parse_ini_file("/var/www/html/.env", false, INI_SCANNER_RAW) ?: []; echo $values["APP_DEBUG"] ?? "";')}"

case "$(printf '%s' "$configured_debug" | tr '[:upper:]' '[:lower:]')" in
  false|0|off|no) ;;
  *) echo "Refusing to start: APP_DEBUG must be false." >&2; exit 1 ;;
esac
[ "$configured_env" = "production" ] || {
  echo "Refusing to start: APP_ENV must be production." >&2
  exit 1
}

link_path=/var/www/html/public/storage
expected_target=/var/www/html/storage/app/public

if [ -L "$link_path" ]; then
  actual_target="$(readlink -f "$link_path")"
  [ "$actual_target" = "$expected_target" ] || {
    echo "Refusing to start: public/storage points to $actual_target, expected $expected_target." >&2
    exit 1
  }
elif [ -e "$link_path" ]; then
  echo "Refusing to start: public/storage exists and is not a symbolic link." >&2
  exit 1
else
  php artisan storage:link
fi

[ -L "$link_path" ] && [ "$(readlink -f "$link_path")" = "$expected_target" ] || {
  echo "Refusing to start: public/storage link is unavailable or invalid." >&2
  exit 1
}

php artisan config:cache
php artisan route:cache
php artisan view:cache
php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); if (! $app->environment("production") || (bool) config("app.debug")) { fwrite(STDERR, "Unsafe effective Laravel configuration.\n"); exit(1); }'

exec "$@"
