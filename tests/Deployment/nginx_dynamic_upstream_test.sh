#!/usr/bin/env bash

set -euo pipefail

upstream_name="sierra_auth_app"
suffix="${GITHUB_RUN_ID:-local}-$$"
network="nginx-dns-auth-${suffix}"
nginx_container="nginx-dns-auth-${suffix}"
address_holder="nginx-dns-auth-holder-${suffix}"
fixture_dir="$(mktemp -d /tmp/nginx-dns-auth.XXXXXX)"

cleanup() {
  docker rm -f "$nginx_container" "$upstream_name" "$address_holder" >/dev/null 2>&1 || true
  docker network rm "$network" >/dev/null 2>&1 || true
  case "$fixture_dir" in
    /tmp/nginx-dns-auth.*) rm -rf -- "$fixture_dir" ;;
  esac
}
trap cleanup EXIT

mkdir -p "$fixture_dir/public"
printf '%s\n' '<?php header("Content-Type: application/json"); echo json_encode(["status" => "ok"]);' > "$fixture_dir/public/index.php"

docker network create "$network" >/dev/null
docker run -d --name "$upstream_name" --network "$network" \
  -v "$fixture_dir/public:/var/www/html/public:ro" php:8.2-fpm-alpine >/dev/null
docker run -d --name "$nginx_container" --network "$network" \
  -v "$PWD/docker/prod/nginx.conf:/etc/nginx/conf.d/default.conf:ro" \
  -v "$fixture_dir/public:/var/www/html/public:ro" nginx:stable-alpine >/dev/null

nginx_id="$(docker inspect -f '{{.Id}}' "$nginx_container")"

request() {
  docker exec "$nginx_container" wget -qO- "http://127.0.0.1$1"
}

for attempt in {1..20}; do
  if request /api/v1/health | grep -q '"status":"ok"'; then
    break
  fi
  if [ "$attempt" -eq 20 ]; then
    echo "Nginx did not reach the initial PHP-FPM container."
    exit 1
  fi
  sleep 1
done

old_ip="$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$upstream_name")"
docker rm -f "$upstream_name" >/dev/null
docker run -d --name "$address_holder" --network "$network" alpine:3.20 sleep 60 >/dev/null
docker run -d --name "$upstream_name" --network "$network" \
  -v "$fixture_dir/public:/var/www/html/public:ro" php:8.2-fpm-alpine >/dev/null
new_ip="$(docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$upstream_name")"

test "$old_ip" != "$new_ip"

for attempt in {1..20}; do
  if request /api/v1/health | grep -q '"status":"ok"'; then
    break
  fi
  if [ "$attempt" -eq 20 ]; then
    echo "Nginx did not resolve the recreated PHP-FPM container."
    docker exec "$nginx_container" getent hosts "$upstream_name" || true
    docker logs "$nginx_container" || true
    exit 1
  fi
  sleep 1
done

test "$(docker inspect -f '{{.Id}}' "$nginx_container")" = "$nginx_id"

for malicious_path in '/.env' '/credentials.json' '/phpinfo.php.bak' '/_profiler/phpinfo' '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php'; do
  status="$(docker exec "$nginx_container" wget -S -O /dev/null "http://127.0.0.1${malicious_path}" 2>&1 | sed -n 's/.*HTTP\/1.1 \([0-9][0-9][0-9]\).*/\1/p' | tail -n 1 || true)"
  test "$status" = "404"
done

request /api/v1/auth/login >/dev/null
echo "Dynamic upstream and scanner-blocking checks passed."
