#!/usr/bin/env bash
#
# Render the tracked devops/ configs onto a server, validate, and reload.
#
# Scope: CONFIG ONLY. This does not install packages — php8.4, nginx, haproxy,
# varnish and supervisor must already be present. Package install is a rare
# one-time step done by hand; the recurring pain this solves is config drift.
#
# Role: production app server (boro-shaped). Staging/helio differ in backend
# IPs and supervisor program names — review before running elsewhere.
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

# required secrets — fail early if unset
: "${HAPROXY_STATS_USER:?set it in $ENV_FILE}"
: "${HAPROXY_STATS_PASSWORD:?set it in $ENV_FILE}"

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

echo "nginx:"
place "$DEVOPS/nginx/aiku-octane-production.conf" /etc/nginx/sites-available/aiku-octane-production.conf
if [[ $DRY_RUN != 1 ]]; then
  ln -sfn /etc/nginx/sites-available/aiku-octane-production.conf \
          /etc/nginx/sites-enabled/aiku-octane-production.conf
fi

echo "supervisor:"
for f in "$DEVOPS"/supervisor/aiku-*.conf; do
  place "$f" "/etc/supervisor/conf.d/$(basename "$f")"
done

echo "varnish:"
place "$DEVOPS/varnish/default.vcl" /etc/varnish/default.vcl

if [[ $DRY_RUN == 1 ]]; then
  echo "dry-run complete — nothing written."
  exit 0
fi

echo "validating + reloading:"
nginx -t && systemctl reload nginx
haproxy -c -f /etc/haproxy/haproxy.cfg && systemctl reload haproxy
supervisorctl reread && supervisorctl update
# Varnish reload semantics vary by install (varnishreload vs restart); leave it
# to the operator so a bad VCL can't take the cache down unattended.
echo "varnish: default.vcl placed — reload it manually (varnishreload or restart)."

echo "done."
