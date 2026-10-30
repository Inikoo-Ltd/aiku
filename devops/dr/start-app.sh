#!/usr/bin/env bash
#
# Start aiku on the DR server (INI-133), once the code (deploy-code.sh) and the database
# (restore-db.sh) are in place, then check every part and print one OK or FAIL line each.
# Safe to run again.
#
#     start-app.sh                # real disaster: the box becomes production, scheduler on
#     start-app.sh --rehearsal    # test: cut off from the internet first, secrets wiped from
#                                 # the database, mail to the log, no scheduler
#
set -uo pipefail
[[ $EUID -eq 0 ]] || { echo "FAIL: run it with sudo"; exit 1; }

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
devops="$(dirname "$here")"
app=/home/aiku/aiku/current
env_file=/home/aiku/aiku/shared/.env
rehearsal=0
[[ ${1:-} == --rehearsal ]] && rehearsal=1
failed=0
ok() { echo "OK: $*"; }
fail() { echo "FAIL: $*"; failed=1; }
artisan() { runuser -u aiku -- php8.4 "$app/artisan" "$@"; }

[[ -f $env_file ]] || { echo "FAIL: no .env in /home/aiku/aiku/shared (README step 3, install-secrets.sh)"; exit 1; }
[[ -d $app ]] || { echo "FAIL: no code in $app (README steps 2 and 4)"; exit 1; }
[[ $(runuser -u postgres -- psql -tAc 'select not pg_is_in_recovery()' 2>/dev/null) == t ]] ||
  { echo "FAIL: the database is not ready (README step 5, restore-db.sh)"; exit 1; }

if [[ $rehearsal == 1 ]]; then
  "$here/egress-lock.sh" on >/dev/null && ok "cut off from the internet (egress lock)"
  # root opens the file: postgres cannot enter /home/aiku
  if runuser -u postgres -- psql -q -v ON_ERROR_STOP=1 -d aiku < "$devops/staging-sanitize.sql" >/dev/null; then
    ok "payment keys, tokens and failed jobs wiped from this copy"
  else
    echo "FAIL: could not wipe the secrets from this copy; nothing was started"; exit 1
  fi
  sed -i 's/^MAIL_MAILER=.*/MAIL_MAILER=log/' "$env_file"
  export INSTALL_SCHEDULER=0
else
  export INSTALL_SCHEDULER=1
fi

# Fixes that images built before 30 Oct 2026 04:20 UTC lack (build-image.sh has them now)
usermod -aG aiku www-data
rm -f /etc/nginx/sites-enabled/default
install -m 755 "$devops/varnish/vmods/libvmod_querystring.so" /usr/lib/varnish/vmods/

"$here/make-server-env.sh" >/dev/null
# the production .env has Windows line endings
typesense_key=$(grep -m1 '^TYPESENSE_API_KEY=' "$env_file" | cut -d= -f2- | tr -d '\r"')
sed -i "s|^api-key = .*|api-key = $typesense_key|" /etc/typesense/typesense-server.ini
unset typesense_key

systemctl enable --now redis-server typesense-server nginx haproxy supervisor docker >/dev/null 2>&1
systemctl restart typesense-server
ENV_FILE="$devops/server.env" "$devops/setup-server.sh" >/tmp/setup-server.log 2>&1 ||
  fail "setup-server.sh (see /tmp/setup-server.log)"
# the config cache is built per copy of the code: the release and Octane's anchor
for dir in "$app" /home/aiku/aiku/anchor/octane; do
  runuser -u aiku -- php8.4 "$dir/artisan" config:cache >/dev/null
done
supervisorctl restart all >/dev/null 2>&1
# NightOwl's database lived on helio
supervisorctl stop aiku-owl >/dev/null 2>&1

# HTTPS on the box: two zones use Cloudflare's "Full" mode, which talks to the server over 443,
# and a rehearsal browser needs it. A self-signed certificate is enough behind Cloudflare.
pem=/etc/haproxy/dr-selfsigned.pem
if [[ ! -f $pem ]]; then
  openssl req -x509 -newkey rsa:2048 -nodes -days 365 -subj /CN=aiku-dr \
    -addext 'subjectAltName=DNS:*.aiku.io,DNS:aiku.io' -keyout /tmp/dr.key -out /tmp/dr.crt 2>/dev/null
  cat /tmp/dr.crt /tmp/dr.key > "$pem"
  rm -f /tmp/dr.key /tmp/dr.crt
  chmod 600 "$pem"
fi
grep -q 'bind \*:443' /etc/haproxy/haproxy.cfg ||
  sed -i "/^frontend www/,/bind \*:80/ s|^\(\s*\)bind \*:80$|&\n\1bind *:443 ssl crt $pem\n\1http-request set-header X-Forwarded-Proto https if { ssl_fc }|" /etc/haproxy/haproxy.cfg
systemctl reload haproxy
systemctl restart varnish
systemctl enable varnish >/dev/null 2>&1
sleep 15

# Typesense starts empty: rebuild the search index in the background (logged)
pgrep -f 'artisan search' >/dev/null || (artisan search -r >/home/aiku/logs/search-reindex.log 2>&1 &)

# --- checks -----------------------------------------------------------------
orders=$(runuser -u postgres -- psql -d aiku -tAc 'select count(*) from orders' 2>/dev/null)
[[ -n $orders ]] && ok "database: $orders orders" || fail "database does not answer"

code=$(curl -sk -o /dev/null -m 30 -w '%{http_code}' --resolve app.aiku.io:443:127.0.0.1 https://app.aiku.io/login)
[[ $code == 200 ]] && ok "staff app (app.aiku.io) login page" || fail "staff app login page answered $code"

site=$(runuser -u postgres -- psql -d aiku -tAc "select domain from websites where state='live' and type='b2b' order by id limit 1" 2>/dev/null)
headers=$(curl -sk -o /dev/null -D - -m 60 --resolve "www.$site:443:127.0.0.1" "https://www.$site/" 2>/dev/null | tr -d '\r')
if grep -qi '^x-varnish' <<<"$headers" && ! grep -q '^HTTP/[0-9.]* 5' <<<"$headers"; then
  ok "shop website www.$site through Varnish"
else
  fail "shop website www.$site: $(head -1 <<<"$headers")"
fi

[[ $(curl -s -m 10 http://127.0.0.1:8082/health) == *running* ]] && ok "images (imgproxy)" || fail "imgproxy"
[[ $(curl -s -m 10 http://127.0.0.1:6002/) == OK ]] && ok "live updates (soketi)" || fail "soketi"
[[ $(curl -s -m 10 http://127.0.0.1:13714/health) == *OK* ]] && ok "page rendering (SSR)" || fail "SSR"
artisan horizon:status 2>/dev/null | grep -q running && ok "background jobs (Horizon)" || fail "Horizon"
if [[ $rehearsal == 1 ]]; then
  curl -s -m 5 -o /dev/null https://www.google.com && fail "the internet is reachable during a rehearsal" ||
    ok "internet blocked, nothing can reach customers or marketplaces"
fi

if [[ $failed == 0 ]]; then
  echo "OK: aiku is running on this server"
else
  echo "FAIL: some parts are not working; see the FAIL lines above"
  exit 1
fi
