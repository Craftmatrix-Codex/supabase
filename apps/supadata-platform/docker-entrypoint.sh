#!/bin/sh
set -eu

mkdir -p "${SUPADATA_DATA_DIR:-/var/lib/supadata}"

/usr/local/bin/supadata-platform &
go_pid=$!

php-fpm -F &
php_pid=$!

cleanup() {
  kill "$go_pid" "$php_pid" "${nginx_pid:-}" 2>/dev/null || true
  wait "$go_pid" 2>/dev/null || true
  wait "$php_pid" 2>/dev/null || true
  if [ -n "${nginx_pid:-}" ]; then wait "$nginx_pid" 2>/dev/null || true; fi
}
trap cleanup INT TERM EXIT

nginx -g 'daemon off;' &
nginx_pid=$!

while kill -0 "$go_pid" 2>/dev/null && kill -0 "$php_pid" 2>/dev/null && kill -0 "$nginx_pid" 2>/dev/null; do
  sleep 1
done

exit 1
