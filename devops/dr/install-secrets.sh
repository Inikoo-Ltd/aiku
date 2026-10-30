#!/usr/bin/env bash
#
# Put aiku's secrets on the DR server (INI-133): .env, the OAuth keys and shared/private
# (Font Awesome Pro, Firebase key), from the nightly encrypted backup. The newest copy is
# read from AWS S3 with the server's own role; give a file instead to use one downloaded
# from Cloudflare R2 (bucket aiku-db-backups). The private key comes from the
# disaster-recovery PDF; the person types its passphrase when gpg asks. Nothing is written
# in clear outside /home/aiku/aiku/shared.
#
# Usage:  sudo install-secrets.sh aiku-backup.private.asc [aiku-secrets-DATE.tar.gpg]
#
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "FAIL: run it with sudo"; exit 1; }
key=${1:?usage: install-secrets.sh PRIVATE_KEY_FILE [SECRETS_FILE]}
secrets=${2:-}
shared=/home/aiku/aiku/shared

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
if [[ -z $secrets ]]; then
  export RCLONE_CONFIG_S3_TYPE=s3 RCLONE_CONFIG_S3_PROVIDER=AWS RCLONE_CONFIG_S3_ENV_AUTH=true RCLONE_CONFIG_S3_REGION=eu-central-1
  newest=$(rclone lsf s3:aiku-pgbackrest/aiku-secrets | grep -E '^aiku-secrets-[0-9-]+\.tar\.gpg$' | sort | tail -1)
  [[ -n $newest ]] || { echo "FAIL: no aiku-secrets file in AWS S3; download the newest from Cloudflare R2 (bucket aiku-db-backups) and give it as the second argument"; exit 1; }
  rclone copyto "s3:aiku-pgbackrest/aiku-secrets/$newest" "$work/$newest"
  secrets=$work/$newest
  echo "OK: $newest fetched from AWS S3"
fi

gpg --homedir "$work" --batch --import "$key" 2>/dev/null || { echo "FAIL: $key is not a readable private key"; exit 1; }
gpg --homedir "$work" --pinentry-mode loopback --decrypt "$secrets" | tar -x -C "$shared" ||
  { echo "FAIL: could not decrypt $secrets (wrong passphrase or wrong file?)"; exit 1; }
chown -R aiku:aiku "$shared"
chmod 600 "$shared/.env"
for f in "$shared"/storage/oauth-*.key; do chmod 600 "$f"; done
[[ -f $shared/.env && -f $shared/storage/oauth-private.key ]] || { echo "FAIL: the backup did not contain .env and the OAuth keys"; exit 1; }
[[ -d $shared/private/fa ]] || echo "FAIL: shared/private is missing (backups before 31 Oct 2026 lack it); the code build will stop on the icons"
echo "OK: secrets in place"
