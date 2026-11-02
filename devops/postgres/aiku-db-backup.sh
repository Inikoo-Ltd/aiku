#!/bin/bash
#
# Nightly dump of the aiku database, taken from the delayed replica on helio and
# copied to the storage box and to Cloudflare R2, which is another provider, so the
# database survives losing Hetzner altogether. Alerts on Discord through the same
# webhook as devops/disk-alert.sh.
#
# Install: see devops/cron/aiku-db-backup.
#
# Usage: aiku-db-backup.sh [--check] [--secrets] [--alert TEXT] [--test] [--self-test]
#   (no flag)    dump, copy to the storage box and to R2, rotate
#   --secrets    on the app server: encrypt .env, the OAuth keys and shared/private onto
#                the storage box, from where the nightly run copies them to R2, and to
#                AWS S3 when AIKU_BACKUP_S3 is set (the disaster-recovery server reads them there)
#   --check      alert when the newest dump on the storage box is too old, or when
#                AIKU_BACKUP_S3 is set and today's secrets are not in S3
#   --alert TEXT post TEXT to Discord as a failure, pinging the role; for other
#                backup jobs on the host to report through (see devops/cron/pgbackrest-archive)
#   --test       post one test message to Discord
#   --self-test  check the rotation and alert logic, no side effects

set -uo pipefail

PORT=${AIKU_BACKUP_PORT:-5435}
DATABASE=${AIKU_BACKUP_DATABASE:-aiku}
COMPRESS=${AIKU_BACKUP_COMPRESS:-zstd:3}
LOCAL_DIR=${AIKU_BACKUP_LOCAL_DIR:-/var/lib/postgresql/backups}
MOUNT=${AIKU_BACKUP_MOUNT:-/sel}
REMOTE_DIR=${AIKU_BACKUP_REMOTE_DIR:-/sel/aiku/db}
R2=${AIKU_BACKUP_R2:-}
S3=${AIKU_BACKUP_S3:-}
SECRETS_DIR=${AIKU_BACKUP_SECRETS_DIR:-/sel/aiku/secrets}
SECRETS_SOURCE=${AIKU_BACKUP_SECRETS_SOURCE:-/home/aiku/aiku/shared}
SECRETS_KEY=${AIKU_BACKUP_SECRETS_KEY:-/etc/aiku-backup.pub}
SECRETS_NAME=${AIKU_BACKUP_SECRETS_NAME:-aiku-secrets}
SECRETS_FILES=${AIKU_BACKUP_SECRETS_FILES:-.env storage/oauth-private.key storage/oauth-public.key private}
KEEP_SECRETS=${AIKU_BACKUP_KEEP_SECRETS:-7}
KEEP_DAILY=${AIKU_BACKUP_KEEP_DAILY:-7}
KEEP_WEEKLY=${AIKU_BACKUP_KEEP_WEEKLY:-4}
MIN_PERCENT=${AIKU_BACKUP_MIN_PERCENT:-80}
MAX_AGE_HOURS=${AIKU_BACKUP_MAX_AGE_HOURS:-26}
MAX_BEHIND_HOURS=${AIKU_BACKUP_MAX_BEHIND_HOURS:-14}
COPY_ATTEMPTS=${AIKU_BACKUP_COPY_ATTEMPTS:-3}
COPY_RETRY_SECONDS=${AIKU_BACKUP_COPY_RETRY_SECONDS:-60}
DD_OUT=${AIKU_BACKUP_DD_OUT-oflag=direct}
DD_IN=${AIKU_BACKUP_DD_IN-iflag=direct}
ALERT_ROLE=${AIKU_BACKUP_ALERT_ROLE:-<@&1164019425154969600>}
ENV_FILE=${AIKU_BACKUP_ENV_FILE:-/home/aiku/aiku/current/.env}

HOST=$(hostname -s 2>/dev/null || hostname)

command -v sha256sum > /dev/null || sha256sum() { shasum -a 256 "$@"; }

env_value() {
    [ -r "$ENV_FILE" ] || return
    sed -n "s/^$1=//p" "$ENV_FILE" | head -1 | tr -d '\042\047\r'
}

json_string() {
    printf '%s' "$1" | awk '
        BEGIN { ORS = ""; print "\"" }
        { gsub(/\\/, "\\\\"); gsub(/"/, "\\\""); if (NR > 1) print "\\n"; print $0 }
        END { print "\"" }
    '
}

notify() {
    local text=$1 url code
    logger -t aiku-db-backup "$text"
    url=${AIKU_BACKUP_WEBHOOK:-$(env_value DISCORD_WEBHOOK_URL)}

    if [ -z "$url" ]; then
        printf 'aiku-db-backup: %s (no DISCORD_WEBHOOK_URL available)\n' "$text" >&2
        return 1
    fi

    code=$(curl -sS -m 15 -o /dev/null -w '%{http_code}' -X POST \
        -H 'Content-type: application/json' \
        --data "$(printf '{"content": %s}' "$(json_string "$text")")" "$url" 2>&1)

    case $code in
        2*) return 0 ;;
        *) printf 'aiku-db-backup: %s (Discord returned HTTP %s)\n' "$text" "$code" >&2; return 1 ;;
    esac
}

fail() {
    notify "🚨 **Backup failed — ${HOST}** 🚨"$'\n'"$1 $ALERT_ROLE"
    exit 1
}

newest() {
    ls -1 "$1"/aiku-????-??-??*.dump 2>/dev/null | sort -r | head -1
}

DAILY='^aiku-[0-9]{4}-[0-9]{2}-[0-9]{2}\.dump$'
WEEKLY='^aiku-[0-9]{4}-[0-9]{2}-[0-9]{2}-weekly\.dump$'
SECRETS="^${SECRETS_NAME}-[0-9]{4}-[0-9]{2}-[0-9]{2}\.tar\.gpg$"

surplus() {
    grep -E "$1" | sort -r | tail -n +$(($2 + 1))
}

prune() {
    local dir=$1 pattern=$2 keep=$3 file
    ls -1 "$dir" 2>/dev/null | surplus "$pattern" "$keep" | while read -r file; do
        rm -f -- "$dir/$file"
        case $file in *.dump) rm -f -- "$dir/${file%.dump}.globals.sql" "$dir/$file.sha256" ;; esac
    done
}

# --s3-no-head: after an upload rclone 1.60 asks R2 for the object by version id,
# R2 answers 501 Not Implemented, and a copy that worked is reported as failed.
r2() {
    rclone --s3-no-check-bucket --s3-no-head --s3-chunk-size 64M --s3-upload-concurrency 8 "$@"
}

prune_r2() {
    prune_remote "$R2" "$@"
}

# The same rclone flags serve AWS S3: the remote's own type decides the provider
prune_remote() {
    local remote=$1 pattern=$2 keep=$3 file
    r2 lsf "$remote" | surplus "$pattern" "$keep" | while read -r file; do
        r2 deletefile "$remote/$file"
        case $file in *.dump) r2 deletefile "$remote/${file%.dump}.globals.sql"; r2 deletefile "$remote/$file.sha256" ;; esac
    done
}

copy_to_r2() {
    local name=$1 secrets
    r2 copyto "$LOCAL_DIR/$name.dump" "$R2/$name.dump" \
        && r2 copyto "$LOCAL_DIR/$name.globals.sql" "$R2/$name.globals.sql" \
        && r2 copyto "$LOCAL_DIR/$name.dump.sha256" "$R2/$name.dump.sha256" \
        || return 1
    prune_r2 "$DAILY" "$KEEP_DAILY"
    prune_r2 "$WEEKLY" "$KEEP_WEEKLY"

    secrets=$(ls -1 "$SECRETS_DIR" 2>/dev/null | grep -E "$SECRETS" | sort -r | head -1)
    [ -z "$secrets" ] && return 0
    r2 copyto "$SECRETS_DIR/$secrets" "$R2/$secrets" || return 1
    prune_r2 "$SECRETS" "$KEEP_SECRETS"
}

run_secrets() {
    local file encrypted
    mountpoint -q "$MOUNT" || fail "The storage box is not mounted on ${MOUNT}, so ${SECRETS_NAME} was not backed up."
    [ -r "$SECRETS_KEY" ] || fail "The public key ${SECRETS_KEY} is missing, so ${SECRETS_NAME} was not backed up."
    mkdir -p "$SECRETS_DIR" || fail "Cannot create ${SECRETS_DIR}."

    file="$SECRETS_DIR/${SECRETS_NAME}-$(date +%F).tar.gpg"
    encrypted=$(mktemp) || fail "Cannot create a temporary file for ${SECRETS_NAME}."
    # shellcheck disable=SC2086 # SECRETS_FILES is a list of paths
    tar -C "$SECRETS_SOURCE" -cf - $SECRETS_FILES \
        | gpg --batch --yes --encrypt --recipient-file "$SECRETS_KEY" --output "$encrypted" \
        && [ -s "$encrypted" ] \
        && copy_verified "$encrypted" "$file.partial" "$(checksum "$encrypted")" \
        && mv "$file.partial" "$file" \
        && { [ -z "$R2" ] || r2 copyto "$file" "$R2/${file##*/}"; } \
        && { [ -z "$S3" ] || r2 copyto "$file" "$S3/${file##*/}"; }
    local result=$?
    rm -f "$encrypted"
    [ "$result" = 0 ] || fail "Encrypting ${SECRETS_NAME} to ${SECRETS_DIR}${R2:+ and R2}${S3:+ and AWS S3} failed."

    prune "$SECRETS_DIR" "$SECRETS" "$KEEP_SECRETS"
    [ -z "$R2" ] || prune_r2 "$SECRETS" "$KEEP_SECRETS"
    [ -z "$S3" ] || prune_remote "$S3" "$SECRETS" "$KEEP_SECRETS"
}

shrunk() {
    local size=$1 previous=$2
    [ "$previous" -gt 0 ] && [ $((size * 100)) -lt $((previous * MIN_PERCENT)) ]
}

too_old() {
    local modified=$1 now=$2
    [ $((now - modified)) -gt $((MAX_AGE_HOURS * 3600)) ]
}

checksum() {
    sha256sum "$1" | cut -d' ' -f1
}

# The storage box is a CIFS mount. On 11 Oct 2026 a plain cp of the first dump
# lost a 4 MB write to a failed memory allocation in the kernel's SMB encryption
# and left a file of the right size with the wrong content. So: small direct
# writes, which fail at once instead of at close, then read the copy back past the
# page cache and compare it with the original before it counts.
copy_verified() {
    local source=$1 target=$2 sum=$3 attempt problem copied
    for attempt in $(seq "$COPY_ATTEMPTS"); do
        if ! problem=$(dd if="$source" of="$target" bs=1M $DD_OUT 2>&1); then
            problem="the write failed: $(printf '%s' "$problem" | head -1)"
        elif ! copied=$(dd if="$target" bs=1M $DD_IN 2> /dev/null | sha256sum | cut -d' ' -f1); then
            problem='the copy could not be read back'
        elif [ "$copied" != "$sum" ]; then
            problem='the copy read back differs from the original'
        else
            return 0
        fi
        logger -t aiku-db-backup "copy to $target, attempt $attempt of $COPY_ATTEMPTS: $problem"
        [ "$attempt" -lt "$COPY_ATTEMPTS" ] && sleep "$COPY_RETRY_SECONDS"
    done
    return 1
}

too_far_behind() {
    [ "${1:-0}" -gt $((MAX_BEHIND_HOURS * 3600)) ]
}

as_postgres() {
    runuser -u postgres -- "$@"
}

run_backup() {
    local name partial size sum previous previous_size started replayed behind

    mountpoint -q "$MOUNT" || fail "The storage box is not mounted on ${MOUNT}."

    name="aiku-$(date +%F)"
    [ "$(date +%u)" = 7 ] && name="$name-weekly"
    partial="$LOCAL_DIR/$name.dump.partial"
    started=$(date +%s)

    install -d -o postgres -g postgres -m 700 "$LOCAL_DIR" || fail "Cannot create ${LOCAL_DIR}."
    mkdir -p "$REMOTE_DIR" || fail "Cannot create ${REMOTE_DIR}."

    replayed=$(as_postgres psql -X -p "$PORT" -d "$DATABASE" -Atc 'select pg_last_xact_replay_timestamp()' 2>/dev/null)

    behind=$(as_postgres psql -X -p "$PORT" -d "$DATABASE" -Atc 'select coalesce(extract(epoch from now() - pg_last_xact_replay_timestamp())::int, 0)' 2>/dev/null)
    if too_far_behind "$behind"; then
        notify "⚠️ **Delayed replica is not applying changes — ${HOST}**"$'\n'"It is $((behind / 3600)) hours behind production and should be 12. Tonight's dump holds data as of ${replayed:-unknown}. Check for a paused replay (pg_is_wal_replay_paused) or a long query on port ${PORT}."
    fi

    as_postgres pg_dump -p "$PORT" -Fc -Z "$COMPRESS" -f "$partial" "$DATABASE" || fail "pg_dump stopped with an error. Last night's dump of ${DATABASE} is missing."
    as_postgres pg_restore -l "$partial" > /dev/null || fail "Last night's dump of ${DATABASE} cannot be read back by pg_restore."
    as_postgres pg_dumpall -p "$PORT" --globals-only -f "$LOCAL_DIR/$name.globals.sql" || fail "pg_dumpall of the roles stopped with an error. Last night's dump of ${DATABASE} is incomplete."

    size=$(stat -c %s "$partial")
    sum=$(checksum "$partial")
    printf '%s  %s\n' "$sum" "$name.dump" > "$LOCAL_DIR/$name.dump.sha256"
    previous=$(newest "$REMOTE_DIR")
    previous_size=0
    [ -n "$previous" ] && previous_size=$(stat -c %s "$previous")

    copy_verified "$partial" "$REMOTE_DIR/$name.dump.partial" "$sum" \
        && cp "$LOCAL_DIR/$name.globals.sql" "$LOCAL_DIR/$name.dump.sha256" "$REMOTE_DIR/" \
        && mv "$REMOTE_DIR/$name.dump.partial" "$REMOTE_DIR/$name.dump" \
        || fail "The dump was made but its copy to ${REMOTE_DIR} failed or does not match the original after ${COPY_ATTEMPTS} attempts. The good dump is still in ${LOCAL_DIR} on ${HOST}."

    find "$LOCAL_DIR" -maxdepth 1 -name 'aiku-*' ! -name "$name.*" -delete
    mv "$partial" "$LOCAL_DIR/$name.dump"

    prune "$REMOTE_DIR" "$DAILY" "$KEEP_DAILY"
    prune "$REMOTE_DIR" "$WEEKLY" "$KEEP_WEEKLY"

    if [ -n "$R2" ] && ! copy_to_r2 "$name"; then
        notify "⚠️ **Database backup not copied to R2 — ${HOST}**"$'\n'"${name}.dump is on the storage box, but the copy to ${R2} failed, so last night has no copy outside Hetzner."
    fi

    logger -t aiku-db-backup "$name.dump done: $size bytes in $(( ($(date +%s) - started) / 60 )) min, data as of ${replayed:-unknown}"

    if shrunk "$size" "$previous_size"; then
        notify "⚠️ **Database backup shrank — ${HOST}**"$'\n'"${name}.dump is $((size / 1073741824)) GB, the one before was $((previous_size / 1073741824)) GB. Check it before trusting it."
    fi
}

run_check() {
    local file
    mountpoint -q "$MOUNT" || fail "The storage box is not mounted on ${MOUNT}, so the dumps cannot be checked."
    file=$(newest "$REMOTE_DIR")
    [ -n "$file" ] || fail "There is no dump at all in ${REMOTE_DIR}."
    too_old "$(stat -c %Y "$file")" "$(date +%s)" && fail "The newest dump, $(basename "$file"), is more than ${MAX_AGE_HOURS} hours old."
    if [ -n "$S3" ] && ! r2 lsf "$S3" 2> /dev/null | grep -qx "${SECRETS_NAME}-$(date +%F).tar.gpg"; then
        fail "Today's ${SECRETS_NAME} is not in AWS S3 (${S3}). A server started at AWS after losing Hetzner could not get the settings and keys from there."
    fi
    return 0
}

self_test() {
    local failures=0 dir day
    logger() { :; }
    check() {
        if [ "$1" = "$2" ]; then
            printf '  ok    %s\n' "$3"
        else
            printf '  FAIL  %s (got %s, want %s)\n' "$3" "$1" "$2"
            failures=$((failures + 1))
        fi
    }

    KEEP_DAILY=7 KEEP_WEEKLY=4 MIN_PERCENT=80 MAX_AGE_HOURS=26

    dir=$(mktemp -d)
    for day in 01 02 03 05 06 07 08 09 10; do
        : > "$dir/aiku-2026-10-$day.dump"
        : > "$dir/aiku-2026-10-$day.globals.sql"
        : > "$dir/aiku-2026-10-$day.dump.sha256"
    done
    for day in 2026-09-06 2026-09-13 2026-09-20 2026-09-27 2026-10-04 2026-10-11; do
        : > "$dir/aiku-$day-weekly.dump"
    done
    : > "$dir/aiku-2026-10-11-weekly.dump.partial"

    check "$(basename "$(newest "$dir")")" 'aiku-2026-10-11-weekly.dump' 'newest ignores a partial copy and counts weekly dumps'

    prune "$dir" "$DAILY" "$KEEP_DAILY"
    prune "$dir" "$WEEKLY" "$KEEP_WEEKLY"

    check "$(ls "$dir" | grep -c -E '^aiku-[0-9-]{10}\.dump$')"        7 'keeps 7 daily dumps'
    check "$(ls "$dir" | grep -c -E 'weekly\.dump$')"                  4 'keeps 4 weekly dumps'
    check "$(ls "$dir" | grep -c -E 'globals\.sql$')"                  7 'removes the roles file with its dump'
    check "$(ls "$dir" | grep -c -E 'sha256$')"                        7 'removes the checksum file with its dump'
    check "$([ -e "$dir/aiku-2026-10-01.dump" ] && echo kept || echo gone)"        gone 'oldest daily is removed'
    check "$([ -e "$dir/aiku-2026-10-03.dump" ] && echo kept || echo gone)"        kept 'seventh newest daily is kept'
    check "$([ -e "$dir/aiku-2026-09-13-weekly.dump" ] && echo kept || echo gone)" gone 'fifth newest weekly is removed'
    check "$([ -e "$dir/aiku-2026-10-11-weekly.dump.partial" ] && echo kept || echo gone)" kept 'a partial copy is never rotated away'
    rm -rf "$dir"

    dir=$(mktemp -d)
    prune "$dir" "$DAILY" "$KEEP_DAILY"
    check "$(newest "$dir")" '' 'an empty folder has no newest dump'
    rm -rf "$dir"

    check "$(printf '%s\n' aiku-2026-10-0{1,2,3}.dump aiku-2026-10-04-weekly.dump aiku-2026-10-02.globals.sql other.txt | surplus "$DAILY" 2)" \
        'aiku-2026-10-01.dump' 'a listing from R2 gives up only the daily dumps beyond the ones kept'
    check "$(printf '%s\n' aiku-2026-10-04-weekly.dump aiku-2026-10-01.dump | surplus "$WEEKLY" 4)" '' 'nothing is surplus while under the limit'
    check "$(printf '%s\n' aiku-secrets-2026-10-0{1,2,3}.tar.gpg aiku-2026-10-01.dump | surplus "$SECRETS" 2)" \
        'aiku-secrets-2026-10-01.tar.gpg' 'secrets rotate on their own, apart from the dumps'

    if command -v gpg > /dev/null; then
        dir=$(mktemp -d)
        mkdir -p "$dir/src/storage" "$dir/gnupg" "$dir/out" && chmod 700 "$dir/gnupg"
        printf 'APP_KEY=base64:test\n' > "$dir/src/.env"
        : > "$dir/src/storage/oauth-private.key"
        : > "$dir/src/storage/oauth-public.key"
        mkdir -p "$dir/src/private/fa" && : > "$dir/src/private/fa/icon.js"
        gpg --homedir "$dir/gnupg" --batch --quiet --passphrase '' --quick-generate-key aiku-backup-self-test default default never 2> /dev/null
        gpg --homedir "$dir/gnupg" --batch --quiet --export aiku-backup-self-test > "$dir/key.pub"
        (mountpoint() { return 0; }; SECRETS_DIR="$dir/out" SECRETS_SOURCE="$dir/src" SECRETS_KEY="$dir/key.pub" DD_OUT='' DD_IN='' COPY_RETRY_SECONDS=0 R2='' S3=''; run_secrets)
        (mountpoint() { return 0; }; SECRETS_DIR="$dir/out" SECRETS_SOURCE="$dir/src" SECRETS_KEY="$dir/key.pub" DD_OUT='' DD_IN='' COPY_RETRY_SECONDS=0 R2='' S3='' \
            SECRETS_NAME=helio-config SECRETS_FILES='.env storage'; run_secrets)
        check "$(gpg --homedir "$dir/gnupg" --batch --quiet --decrypt "$dir/out"/helio-config-*.tar.gpg 2> /dev/null | tar -tf - | sort | tr '\n' ' ')" \
            '.env storage/ storage/oauth-private.key storage/oauth-public.key ' 'a server can back up its own list of files under its own name'
        check "$(ls "$dir/out" | grep -c partial)" 0 'no half-written secrets file is left behind'
        check "$(gpg --homedir "$dir/gnupg" --batch --quiet --decrypt "$dir/out"/aiku-secrets-*.tar.gpg 2> /dev/null | tar -xOf - .env)" \
            'APP_KEY=base64:test' 'secrets encrypted with only the public key open with the private key'
        check "$(grep -c APP_KEY "$dir/out"/aiku-secrets-*.tar.gpg)" 0 'the encrypted file does not show the key in clear'
        check "$(gpg --homedir "$dir/gnupg" --batch --quiet --decrypt "$dir/out"/aiku-secrets-*.tar.gpg 2> /dev/null | tar -tf - | grep -c '^private/fa/icon.js$')" \
            1 'shared/private (Font Awesome Pro, Firebase key) is in the secrets, the front end cannot be built without it'
        gpgconf --homedir "$dir/gnupg" --kill all 2> /dev/null
        rm -rf "$dir"
    fi

    check "$(shrunk 30 40 && echo yes || echo no)"  yes 'a dump at 75% of the previous one shrank'
    check "$(shrunk 32 40 && echo yes || echo no)"  no  'a dump at 80% of the previous one is fine'
    check "$(shrunk 50 40 && echo yes || echo no)"  no  'a bigger dump is fine'
    check "$(shrunk 40 0 && echo yes || echo no)"   no  'the first dump has nothing to compare with'
    check "$(shrunk 30000000000 41000000000 && echo yes || echo no)" yes 'real sizes do not overflow'

    check "$(too_old 1000000 $((1000000 + 25 * 3600)) && echo yes || echo no)" no  '25 hours old is fine'
    check "$(too_old 1000000 $((1000000 + 27 * 3600)) && echo yes || echo no)" yes '27 hours old is too old'

    dir=$(mktemp -d)
    head -c 3000000 /dev/urandom > "$dir/original"
    COPY_ATTEMPTS=2 COPY_RETRY_SECONDS=0 DD_OUT='' DD_IN=''
    check "$(copy_verified "$dir/original" "$dir/copy" "$(checksum "$dir/original")" && echo ok || echo failed)" ok 'a copy that matches the original is accepted'
    check "$(cmp -s "$dir/original" "$dir/copy" && echo same || echo differs)" same 'the copy is byte for byte the original'
    check "$(copy_verified "$dir/original" "$dir/copy" "$(checksum "$dir/copy" | tr 0-9 1-90)" && echo ok || echo failed)" failed 'a copy whose content differs from the expected checksum is refused, even at the right size'
    check "$(copy_verified "$dir/original" "$dir/missing/copy" "$(checksum "$dir/original")" 2> /dev/null && echo ok || echo failed)" failed 'a copy that cannot be written is refused'
    rm -rf "$dir"

    MAX_BEHIND_HOURS=14
    check "$(too_far_behind $((12 * 3600 + 600)) && echo yes || echo no)" no  'a replica 12 hours behind is as intended'
    check "$(too_far_behind $((15 * 3600)) && echo yes || echo no)"       yes 'a replica 15 hours behind has stopped applying'
    check "$(too_far_behind "" && echo yes || echo no)"                   no  'an unreadable delay does not raise a false alarm'

    check "$(json_string "one"$'\n'"\"two\"")" '"one\n\"two\""' 'json escapes newlines and quotes'

    [ "$failures" = 0 ] && { printf 'all checks passed\n'; return 0; }
    printf '%s check(s) failed\n' "$failures"
    return 1
}

case ${1:-} in
    --self-test) self_test ;;
    --check)     run_check ;;
    --secrets)   run_secrets ;;
    --alert)     fail "${2:?--alert needs the text to send}" ;;
    --test)      notify "🧪 **Database backup alert test — ${HOST}**"$'\n'"This is a test — nothing is wrong." ;;
    '')          run_backup ;;
    *)           printf 'Usage: %s [--check] [--secrets] [--alert TEXT] [--test] [--self-test]\n' "$0" >&2; exit 2 ;;
esac
