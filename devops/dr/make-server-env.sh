#!/usr/bin/env bash
#
# Write devops/server.env for the DR box (INI-133) from the production .env already on it,
# so nobody types or sees a secret. The imgproxy key/salt and the production soketi key/secret
# are the .env's own; HAProxy stats and the staging/devel soketi apps get fresh random values.
#
# Usage (as root):  make-server-env.sh [/home/aiku/aiku/shared/.env]
#
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run as root"; exit 1; }

env_file=${1:-/home/aiku/aiku/shared/.env}
out="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/server.env"
# the production .env has Windows line endings: drop the \r, then any quotes
value() { grep -m1 "^$1=" "$env_file" | cut -d= -f2- | tr -d '\r' | sed -e 's/^"//' -e 's/"$//'; }
random() { openssl rand -hex 16; }

for var in IMGPROXY_KEY IMGPROXY_SALT PUSHER_APP_KEY PUSHER_APP_SECRET; do
  [[ -n $(value $var) ]] || { echo "$var missing in $env_file"; exit 1; }
done

umask 077
cat > "$out" <<ENV
APP_HOST=dr
NGINX_PORT=8080
VARNISH_HOST_BORO=10.0.0.3
VARNISH_HOST_LITIO=10.0.0.1
VARNISH_PORT_LITIO=8080
VARNISH_PORT_LITIO_REPLICA=8080
OCTANE_WORKERS=48
OCTANE_MAX_REQUESTS=400
OCTANE_PORT=8000
HORIZON_DB_READ_HOSTS=
INSTALL_SCHEDULER=${INSTALL_SCHEDULER:-0}
INSTALL_OCTANE_REPLICA=0
HAPROXY_STATS_USER=inikoo
HAPROXY_STATS_PASSWORD=$(random)
INSTALL_IMGPROXY=1
IMGPROXY_KEY=$(value IMGPROXY_KEY)
IMGPROXY_SALT=$(value IMGPROXY_SALT)
IMGPROXY_SECRET=
IMGPROXY_SOURCE_URL_ENCRYPTION_KEY=
IMGPROXY_PUBLISH=127.0.0.1:8082
IMGPROXY_NGINX_PORT=8094
IMGPROXY_ACCEL_UPSTREAM=127.0.0.1:8094
INSTALL_SOKETI=1
SOKETI_AIKU_KEY=$(value PUSHER_APP_KEY)
SOKETI_AIKU_SECRET=$(value PUSHER_APP_SECRET)
SOKETI_AIKU_STAGING_KEY=$(random)
SOKETI_AIKU_STAGING_SECRET=$(random)
SOKETI_AIKU_DEVEL_KEY=$(random)
SOKETI_AIKU_DEVEL_SECRET=$(random)
ENV
echo "wrote $out"
