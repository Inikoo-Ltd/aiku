#!/usr/bin/env bash
#
# Build the aiku disaster-recovery server image (INI-133) on a FRESH Ubuntu 24.04 EC2
# instance in eu-central-1. Packages, users and folders only, at boro's/litio's versions:
# no code, no data, no secrets ever reach this instance, so the AMI taken from it right
# after this script is clean by construction. Everything else arrives at launch
# (devops/dr/README.md).
#
# Usage (as root on the fresh instance, from a copy of devops/):
#     ./devops/dr/build-image.sh
#
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "run as root"; exit 1; }
export DEBIAN_FRONTEND=noninteractive

NODE_VERSION=v23.11.0         # /usr/local/bin/node on boro (build + SSR tooling)
NODE_SOKETI_VERSION=v18.20.8  # soketi 1.6.0 needs node 18 (devops/supervisor/soketi.conf)
BUN_VERSION=bun-v1.2.23       # Inertia SSR runs on bun (devops/supervisor/aiku-inertia-ssr.conf)
TYPESENSE_VERSION=30.1
IMGPROXY_IMAGE=darthsim/imgproxy@sha256:c1250eeb8efb8b3946e9393f4b1e2871cf15e74e3a546e4110568c147b1a8628

apt-get update
apt-get install -y ca-certificates curl gnupg software-properties-common git rsync unzip acl jq nftables xfsprogs

# The same third-party repositories as boro
install -d -m 755 /etc/apt/keyrings
curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor --yes -o /etc/apt/keyrings/pgdg.gpg
echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] https://apt.postgresql.org/pub/repos/apt noble-pgdg main" > /etc/apt/sources.list.d/pgdg.list
curl -fsSL https://packagecloud.io/varnishcache/varnish80/gpgkey | gpg --dearmor --yes -o /etc/apt/keyrings/varnish.gpg
echo "deb [signed-by=/etc/apt/keyrings/varnish.gpg] https://packagecloud.io/varnishcache/varnish80/ubuntu/ noble main" > /etc/apt/sources.list.d/varnish.list
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
echo "deb [signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu noble stable" > /etc/apt/sources.list.d/docker.list
add-apt-repository -y ppa:ondrej/php
add-apt-repository -y ppa:vbernat/haproxy-3.0
apt-get update

# postgresql-common creates an empty 18/main cluster; restore-db.sh replaces its files
apt-get install -y \
  postgresql-18 postgresql-18-pgvector postgresql-18-repack pgbackrest \
  php8.4-cli php8.4-bcmath php8.4-curl php8.4-gd php8.4-igbinary php8.4-intl php8.4-mbstring \
  php8.4-mysql php8.4-opcache php8.4-pgsql php8.4-readline php8.4-redis php8.4-soap \
  php8.4-sqlite3 php8.4-xml php8.4-zip composer \
  nginx haproxy varnish redis-server supervisor ghostscript rclone \
  docker-ce docker-ce-cli docker-compose-plugin

curl -fsSL "https://dl.typesense.org/releases/$TYPESENSE_VERSION/typesense-server-$TYPESENSE_VERSION-amd64.deb" -o /tmp/typesense.deb
apt-get install -y /tmp/typesense.deb
rm /tmp/typesense.deb

curl -fsSL "https://nodejs.org/dist/$NODE_VERSION/node-$NODE_VERSION-linux-x64.tar.xz" | tar -xJ -C /opt
for bin in node npm npx; do ln -sfn "/opt/node-$NODE_VERSION-linux-x64/bin/$bin" "/usr/local/bin/$bin"; done
curl -fsSL "https://nodejs.org/dist/$NODE_SOKETI_VERSION/node-$NODE_SOKETI_VERSION-linux-x64.tar.xz" | tar -xJ -C /opt
PATH="/opt/node-$NODE_SOKETI_VERSION-linux-x64/bin:$PATH" npm install -g --prefix /opt/soketi @soketi/soketi@1.6.0
curl -fsSL https://bun.sh/install | BUN_INSTALL=/usr/local bash -s "$BUN_VERSION"

docker pull "$IMGPROXY_IMAGE"

# default.vcl imports querystring, which no package ships: boro's copy, built by hand in Oct 2025
# for Varnish 8.0, is kept in the repo
install -m 755 "$(dirname "${BASH_SOURCE[0]}")/../varnish/vmods/libvmod_querystring.so" /usr/lib/varnish/vmods/
# HAProxy owns port 80
rm -f /etc/nginx/sites-enabled/default

# The app user and Deployer's layout (deploy.yaml: deploy_path ~/aiku)
id aiku >/dev/null 2>&1 || useradd -m -s /bin/bash aiku
usermod -aG docker aiku
# nginx serves the built assets from /home/aiku (750), as on boro
usermod -aG aiku www-data
install -d -o aiku -g aiku /home/aiku/aiku/releases /home/aiku/aiku/anchor /home/aiku/logs /home/aiku/soketi \
  /home/aiku/aiku/shared/storage/media /home/aiku/aiku/shared/private /home/aiku/aiku/shared/local_storage

# The production .env, nginx, HAProxy and Varnish address the servers by their private
# addresses (boro 10.0.0.3, litio 10.0.0.1, helio 10.0.0.2). On the single DR box they
# all answer locally, so every file runs unchanged; what lived on helio (archive DB,
# NightOwl) is simply absent.
cat > /etc/netplan/60-aiku-dr.yaml <<'YAML'
network:
  version: 2
  ethernets:
    lo:
      match: {name: lo}
      addresses: [10.0.0.1/32, 10.0.0.2/32, 10.0.0.3/32]
YAML
chmod 600 /etc/netplan/60-aiku-dr.yaml
netplan apply

# Restore config from the DR document, step 4. The key comes from the instance's IAM
# role (read-only on the bucket), never from a file.
cat > /etc/pgbackrest.conf <<'CONF'
[global]
repo1-type=s3
repo1-s3-endpoint=s3.eu-central-1.amazonaws.com
repo1-s3-bucket=aiku-pgbackrest
repo1-s3-region=eu-central-1
repo1-s3-key-type=auto
repo1-path=/aiku
process-max=16
archive-async=y
spool-path=/var/spool/pgbackrest
archive-get-queue-max=8GB
log-level-console=warn
log-level-file=info

[aiku]
pg1-path=/var/lib/postgresql/18/main
CONF
chown postgres:postgres /etc/pgbackrest.conf
chmod 600 /etc/pgbackrest.conf

# Nothing starts on its own from the image: the launch decides what runs, after the
# secrets, the code and (in a rehearsal) the egress lock are in place.
systemctl disable --now postgresql nginx haproxy varnish supervisor typesense-server redis-server

apt-get clean
# the build instance's key pair and identity must not ride into the AMI
cloud-init clean --logs
echo "OK: image ready. Next, in CloudShell: launch.sh image <this instance>"
