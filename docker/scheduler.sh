#!/bin/sh
set -eu
trap 'exit 0' TERM INT
while :; do
  php artisan schedule:run --no-interaction || true
  sleep 60 &
  wait "$!" || true
done
