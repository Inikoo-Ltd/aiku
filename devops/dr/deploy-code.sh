#!/usr/bin/env bash
#
# First deploy of the code on a DR box (INI-133), in Deployer's layout (deploy.yaml,
# deploy/deploy.php: releases/N, shared/, current, anchor/octane) so `dep deploy` can take
# over afterwards. Build steps as Deployer's; nothing that talks to other hosts (crawls,
# Discord, Varnish on boro/litio). Migrations are not run: the restored database already
# matches the production branch.
#
# Usage, on the DR server (the repository is public, no key needed):
#     sudo git clone --depth 1 -b production https://github.com/Inikoo-Ltd/aiku.git /home/aiku/aiku/releases/1
#     sudo /home/aiku/aiku/releases/1/devops/dr/deploy-code.sh 1
#
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run as root"; exit 1; }

release=${1:?release number}
base=/home/aiku/aiku
path=$base/releases/$release
revision=${2:-$(git -C "$path" rev-parse HEAD)}
started=$(date +%s)
log() { echo "[$(date -u +%T) UTC, +$(( ($(date +%s) - started) / 60 )) min] $*"; }

[[ -f $base/shared/.env ]] || { echo "FAIL: put the secrets in place first (README step 3, install-secrets.sh)"; exit 1; }

chown -R aiku:aiku "$path"
as_aiku() { runuser -u aiku -- bash -c "cd $path && $1"; }

echo "$revision" > "$path/REVISION"
for dir in storage private local_storage; do
  # as Deployer's deploy:shared: shared gets the release's folders (framework/cache, logs...) it lacks
  runuser -u aiku -- cp -rn "$path/$dir/." "$base/shared/$dir/" 2>/dev/null || true
  rm -rf "${path:?}/$dir"
  ln -sfn "$base/shared/$dir" "$path/$dir"
done
for file in .env isdoc-pdf rgb.icc .rr.yaml .user.ini; do
  [[ -e $base/shared/$file ]] && ln -sfn "$base/shared/$file" "$path/$file"
done
cp "$path/devops/varnish/restart_varnish.sh" "$base/shared/restart_varnish.sh"
ln -sfn "$base/shared/restart_varnish.sh" "$path/restart_varnish.sh"

log "composer"
as_aiku "composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress"
if [[ ! -x $base/shared/rr ]]; then
  as_aiku "vendor/bin/rr get-binary --no-interaction --location=$base/shared"
fi
ln -sfn "$base/shared/rr" "$path/rr"

log "npm ci + build"
as_aiku "npm ci --no-audit --no-fund && NODE_OPTIONS=--max-old-space-size=8192 npm run build.parallel"

log "caches"
as_aiku "php8.4 artisan storage:link && php8.4 artisan config:cache && php8.4 artisan route:cache && php8.4 artisan view:cache && php8.4 artisan event:cache"

ln -sfn "$path" "$base/current"
# Octane and SSR run from a real copy: RoadRunner does not follow the release symlinks
runuser -u aiku -- rsync -ahHq --delete "$path/" "$base/anchor/octane"
log "code in place: $(cat "$path/REVISION")"
echo "OK: code built and in place"
