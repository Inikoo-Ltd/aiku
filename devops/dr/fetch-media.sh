#!/usr/bin/env bash
#
# Copy the product images and attachments (204 GB in October 2026) onto the DR server
# (INI-133). aiku runs meanwhile; images appear as they arrive. Safe to stop and run again:
# it only fetches what is missing.
#
# From AWS S3 (aiku-pgbackrest/aiku-media) with the server's own role: no key needed.
#     sudo fetch-media.sh [folder]          # a folder only, e.g. 9H, for a quick test
#
# If that copy is missing, from Cloudflare R2 (bucket aiku-media-backup). The key: Cloudflare
# dashboard > R2 > Manage API tokens > Create API token, "Object Read only", bucket
# aiku-media-backup, no IP filter. The person makes it, then on the server:
#     export R2_ACCESS_KEY_ID=...  R2_SECRET_ACCESS_KEY=...
#     sudo -E fetch-media.sh --r2 [folder]
#
set -euo pipefail
[[ $EUID -eq 0 ]] || { echo "FAIL: run it with sudo"; exit 1; }

if [[ ${1:-} == --r2 ]]; then
  shift
  : "${R2_ACCESS_KEY_ID:?FAIL: export R2_ACCESS_KEY_ID first (see the top of this script)}"
  : "${R2_SECRET_ACCESS_KEY:?FAIL: export R2_SECRET_ACCESS_KEY first}"
  # EU-jurisdiction buckets of the account 8c8a97c6d4117196e5275c09a53bf8ea, as in devops/cron/aiku-media-backup;
  # a read-only key gets 403 without no_check_bucket and no_head
  export RCLONE_CONFIG_SRC_TYPE=s3 RCLONE_CONFIG_SRC_PROVIDER=Cloudflare \
    RCLONE_CONFIG_SRC_ENDPOINT=https://8c8a97c6d4117196e5275c09a53bf8ea.eu.r2.cloudflarestorage.com \
    RCLONE_CONFIG_SRC_ACCESS_KEY_ID=$R2_ACCESS_KEY_ID RCLONE_CONFIG_SRC_SECRET_ACCESS_KEY=$R2_SECRET_ACCESS_KEY \
    RCLONE_CONFIG_SRC_NO_CHECK_BUCKET=true RCLONE_CONFIG_SRC_NO_HEAD=true
  source=src:aiku-media-backup
else
  export RCLONE_CONFIG_SRC_TYPE=s3 RCLONE_CONFIG_SRC_PROVIDER=AWS RCLONE_CONFIG_SRC_ENV_AUTH=true RCLONE_CONFIG_SRC_REGION=eu-central-1
  source=src:aiku-pgbackrest/aiku-media
fi
folder=${1:-}

started=$(date +%s)
rclone copy "$source/$folder" "/home/aiku/aiku/shared/storage/media/$folder" \
  --transfers 64 --checkers 64 --fast-list --stats 1m --stats-one-line
chown -R aiku:aiku /home/aiku/aiku/shared/storage/media
echo "OK: media copied in $(( ($(date +%s) - started) / 60 )) min"
