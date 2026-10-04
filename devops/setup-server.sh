#!/usr/bin/env bash
#
# Render the tracked devops/ configs onto a server, validate, and reload.
#
# Scope: CONFIG ONLY. This does not install packages — php8.4, nginx, haproxy,
# varnish and supervisor must already be present. Package install is a rare
# one-time step done by hand; the recurring pain this solves is config drift.
#
# Role: production app server. Per host differences (program names, Octane
# sizing) come from APP_HOST/OCTANE_* in server.env, so the same tree renders
# boro or helio. Staging still differs in backend IPs — review before running.
#
# Usage (as root, on the target box, from a repo checkout):
#     sudo ./devops/setup-server.sh              # apply
#     sudo DRY_RUN=1 ./devops/setup-server.sh    # preview, touch nothing
#
# Secrets come from devops/server.env (gitignored) — copy server.env.example.
#
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEVOPS="$REPO_DIR/devops"
ENV_FILE="${ENV_FILE:-$DEVOPS/server.env}"
DRY_RUN="${DRY_RUN:-0}"

[[ $EUID -eq 0 ]] || { echo "must run as root (sudo)"; exit 1; }
[[ -f "$ENV_FILE" ]] || { echo "missing $ENV_FILE — copy server.env.example and fill it in"; exit 1; }
# shellcheck disable=SC1090
set -a; source "$ENV_FILE"; set +a

# required values — fail early if unset
: "${HAPROXY_STATS_USER:?set it in $ENV_FILE}"
: "${HAPROXY_STATS_PASSWORD:?set it in $ENV_FILE}"
: "${VARNISH_HOST_BORO:?set it in $ENV_FILE}"
: "${VARNISH_HOST_LITIO:?set it in $ENV_FILE}"
: "${VARNISH_PORT_LITIO:?set it in $ENV_FILE}"
: "${NGINX_PORT:?set it in $ENV_FILE}"
: "${APP_HOST:?set it in $ENV_FILE — boro, litio or helio}"
: "${OCTANE_WORKERS:?set it in $ENV_FILE}"
: "${OCTANE_MAX_REQUESTS:?set it in $ENV_FILE}"
INSTALL_SCHEDULER="${INSTALL_SCHEDULER:-0}"

# place <src> <dst> [mode]
# Substitutes every {{VAR}} from the environment (dies on an unset placeholder,
# which catches a missed secret), then installs — or previews under DRY_RUN.
# Files without any {{VAR}} pass through untouched, so this handles both.
place() {
  local src=$1 dst=$2 mode=${3:-644} tmp
  tmp=$(mktemp)
  perl -0pe 's/\{\{(\w+)\}\}/ exists $ENV{$1} ? $ENV{$1} : die "unset placeholder {{$1}} in '"$src"'\n" /ge' "$src" > "$tmp"
  if [[ $DRY_RUN == 1 ]]; then
    echo "  [dry-run] would write $dst (mode $mode)"
  else
    install -D -m "$mode" "$tmp" "$dst"
    echo "  -> $dst"
  fi
  rm -f "$tmp"
}

echo "haproxy:"
place "$DEVOPS/haproxy/haproxy.cfg"        /etc/haproxy/haproxy.cfg
place "$DEVOPS/haproxy/CF_ips.lst"         /etc/haproxy/CF_ips.lst
place "$DEVOPS/haproxy/facebook-bots.lst"  /etc/haproxy/facebook-bots.lst
# haproxy.cfg reads the stats credentials from the environment, so the tracked file is byte-identical to the live one;
# the systemd unit loads /etc/default/haproxy, which keeps the values on the box only
if [[ $DRY_RUN == 1 ]]; then
  echo "  [dry-run] would set HAPROXY_STATS_USER/PASSWORD in /etc/default/haproxy (mode 600)"
else
  touch /etc/default/haproxy
  for var in HAPROXY_STATS_USER HAPROXY_STATS_PASSWORD; do
    sed -i "/^$var=/d" /etc/default/haproxy
    printf '%s=%s\n' "$var" "${!var}" >> /etc/default/haproxy
  done
  chmod 600 /etc/default/haproxy
  echo "  -> /etc/default/haproxy (stats credentials)"
fi
# Banned IPs are kept on the box only; haproxy refuses to start if the list is missing, so create it empty once and never overwrite it
if [[ ! -f /etc/haproxy/banned-ips.lst ]]; then
  if [[ $DRY_RUN == 1 ]]; then
    echo "  [dry-run] would create empty /etc/haproxy/banned-ips.lst"
  else
    install -D -m 644 /dev/null /etc/haproxy/banned-ips.lst
    echo "  -> /etc/haproxy/banned-ips.lst (created empty)"
  fi
fi

echo "nginx:"
place "$DEVOPS/nginx/aiku-octane-production.conf" /etc/nginx/sites-available/aiku-octane-production.conf
if [[ $DRY_RUN != 1 ]]; then
  ln -sfn /etc/nginx/sites-available/aiku-octane-production.conf \
          /etc/nginx/sites-enabled/aiku-octane-production.conf
fi

echo "supervisor:"
# Staging configs live in the same directory but name the staging user and its
# /home/staging paths, and aiku-staging-inertia-ssr.conf declares the same
# program name as the production one — installing them here would give a
# production box a duplicate program pointing at a home that does not exist.
for f in "$DEVOPS"/supervisor/aiku-*.conf; do
  case "$(basename "$f")" in
    aiku-staging-*) continue ;;
  esac
  place "$f" "/etc/supervisor/conf.d/$(basename "$f")"
done
# Debian ships supervisor.service with KillMode=process, so a stop that outruns
# systemd's timeout SIGKILLs supervisord alone and reparents the horizon master
# to init; the next supervisord then starts a second one and every queue runs
# twice. TimeoutStopSec is deliberately above the programs' stopwaitsecs so
# workers still get their full drain window and systemd never cuts in first.
if [[ -f /lib/systemd/system/supervisor.service || -f /etc/systemd/system/supervisor.service ]]; then
  place "$DEVOPS/systemd/supervisor.conf" /etc/systemd/system/supervisor.service.d/aiku.conf
else
  echo "  skipped drop-in (no supervisor unit on this host)"
fi

echo "varnish:"
place "$DEVOPS/varnish/default.vcl" /etc/varnish/default.vcl
# Installing the varnish package starts it with the package's example VCL and 256m;
# restart varnish after the first install or it keeps serving that example.
place "$DEVOPS/systemd/varnish.service" /etc/systemd/system/varnish.service

# The edge host resizes product images (media.aiku.io). imgproxy runs from docker
# (docker.io + docker-compose-v2), published on 127.0.0.1 only because docker-published
# ports bypass ufw. Its signing key/salt must equal every earlier host's, or every
# image URL already handed out (shops, exports, feeds) stops resolving.
echo "imgproxy:"
if [[ ${INSTALL_IMGPROXY:-0} == 1 ]]; then
  for var in IMGPROXY_KEY IMGPROXY_SALT IMGPROXY_SECRET IMGPROXY_SOURCE_URL_ENCRYPTION_KEY IMGPROXY_PUBLISH IMGPROXY_NGINX_PORT; do
    : "${!var:?set $var in $ENV_FILE}"
  done
  place "$DEVOPS/imgproxy/docker-compose.yml" /home/inikoo/docker/imgproxy/docker-compose.yml 600
  place "$DEVOPS/nginx/imgproxy-aiku-production.conf" /etc/nginx/sites-available/imgproxy_aiku_production.conf
  if [[ $DRY_RUN != 1 ]]; then
    install -d -o www-data -g www-data /var/cache/nginx/imgproxy/production
    ln -sfn /etc/nginx/sites-available/imgproxy_aiku_production.conf \
            /etc/nginx/sites-enabled/imgproxy_aiku_production.conf
    docker compose -p imgproxy -f /home/inikoo/docker/imgproxy/docker-compose.yml up -d
  fi
else
  echo "  skipped (INSTALL_IMGPROXY=0 — imgproxy runs on the edge host only)"
fi

# Realtime websockets (soketi.aiku.io). Needs node 18 in /opt/node-v18.20.8-linux-x64 and
# soketi 1.6.0 in /opt/soketi (npm install -g --prefix /opt/soketi @soketi/soketi@1.6.0);
# its app keys must match PUSHER_APP_KEY/SECRET in every host's .env.
echo "soketi:"
if [[ ${INSTALL_SOKETI:-0} == 1 ]]; then
  for var in SOKETI_AIKU_KEY SOKETI_AIKU_SECRET SOKETI_AIKU_STAGING_KEY SOKETI_AIKU_STAGING_SECRET SOKETI_AIKU_DEVEL_KEY SOKETI_AIKU_DEVEL_SECRET; do
    : "${!var:?set $var in $ENV_FILE}"
  done
  place "$DEVOPS/soketi/soketi-conf.json" /home/aiku/soketi/soketi-conf.json 640
  place "$DEVOPS/supervisor/soketi.conf" /etc/supervisor/conf.d/soketi.conf
  if [[ $DRY_RUN != 1 ]]; then
    chown aiku:aiku /home/aiku/soketi/soketi-conf.json
  fi
else
  echo "  skipped (INSTALL_SOKETI=0 — soketi runs on the edge host only)"
fi

echo "sysctl:"
place "$DEVOPS/sysctl/99-aiku.conf" /etc/sysctl.d/99-aiku.conf
if [[ $DRY_RUN != 1 ]]; then
  sysctl -q --load /etc/sysctl.d/99-aiku.conf
fi

# The GitHub runner (CI host only) is set up by devops/setup-ci-runner.sh.

echo "cron:"
if [[ $INSTALL_SCHEDULER == 1 ]]; then
  if [[ $DRY_RUN == 1 ]]; then
    echo "  [dry-run] would install scheduler crontab for user aiku"
  else
    crontab -u aiku "$DEVOPS/cron/crontab"
    echo "  -> scheduler crontab installed for aiku"
  fi
else
  echo "  skipped (INSTALL_SCHEDULER=0 — scheduler runs on one host only)"
fi

if [[ $APP_HOST == boro ]]; then
  if [[ $DRY_RUN == 1 ]]; then
    echo "  [dry-run] would install /etc/cron.d/pg-freeze-sweep"
  else
    install -m 644 "$DEVOPS/cron/pg-freeze-sweep" /etc/cron.d/pg-freeze-sweep
    echo "  -> pg-freeze-sweep installed (primary DB host)"
  fi
else
  echo "  pg-freeze-sweep skipped (primary DB host only)"
fi

echo "redis queue:"
if [[ $APP_HOST == boro ]]; then
  place "$DEVOPS/redis/redis-queue.conf" /etc/redis/redis-queue.conf 640
  if [[ $DRY_RUN == 1 ]]; then
    echo "  [dry-run] would chown redis:redis /etc/redis/redis-queue.conf, create /var/lib/redis/queue and enable redis-server@queue"
  else
    chown redis:redis /etc/redis/redis-queue.conf
    install -d -o redis -g redis -m 750 /var/lib/redis/queue
    systemctl enable --now redis-server@queue
    echo "  -> redis-server@queue enabled (port 6380, queues + default connection)"
  fi
else
  echo "  skipped (the queue Redis lives on boro)"
fi

echo "postgres:"
case $APP_HOST in
  boro)  pg_conf="boro-production.conf" ;;
  litio) pg_conf="litio-replica.conf" ;;
  helio) pg_conf="helio-replica.conf" ;;
  *)     pg_conf="" ;;
esac
if [[ -n $pg_conf ]]; then
  place "$DEVOPS/postgres/$pg_conf" "/etc/postgresql/18/main/conf.d/99-$APP_HOST.conf"
  echo "  NOTE: postgresql.auto.conf (ALTER SYSTEM) overrides conf.d — on an"
  echo "  existing cluster verify with: psql -c 'select name,setting,source from pg_settings'"
else
  echo "  skipped (no postgres conf for $APP_HOST)"
fi

if [[ $DRY_RUN == 1 ]]; then
  echo "dry-run complete — nothing written."
  exit 0
fi

echo "validating + reloading:"
nginx -t && systemctl reload nginx
(set -a; . /etc/default/haproxy; set +a; haproxy -c -f /etc/haproxy/haproxy.cfg) && systemctl reload haproxy
supervisorctl reread && supervisorctl update
systemctl daemon-reload
# Varnish reload semantics vary by install (varnishreload vs restart); leave it
# to the operator so a bad VCL can't take the cache down unattended.
echo "varnish: default.vcl placed — reload it manually (varnishreload or restart)."

echo "done."
