#!/usr/bin/env bash
#
# Fence the GitHub Actions runner on the CI host so tests can never reach production:
# own Postgres cluster 18/ci (owner pgci, tmpfs, unix socket only), nftables rules for the
# runner and pgci uids, capped runner unit. Ends by proving the fence holds; exits non-zero
# if it does not.
#
# Usage (as root, on the CI host, from a repo checkout; pick caps for what else the box runs):
#     RUNNER_MEMORY_MAX=12G PEST_PROCESSES=4 ./devops/setup-ci-runner.sh
#
set -euo pipefail

DEVOPS="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
export RUNNER_MEMORY_MAX="${RUNNER_MEMORY_MAX:-24G}"
export PEST_PROCESSES="${PEST_PROCESSES:-6}"
[[ $EUID -eq 0 ]] || { echo "must run as root"; exit 1; }
id aiku_test >/dev/null || { echo "runner user aiku_test missing"; exit 1; }

getent passwd pgci >/dev/null || useradd --system --no-create-home --home-dir /nonexistent --shell /usr/sbin/nologin pgci
if id -nG aiku_test | grep -qw sudo; then gpasswd -d aiku_test sudo; fi

install -D -m 644 "$DEVOPS/ci/ci-fence.nft" /etc/aiku/ci-fence.nft
install -m 755 "$DEVOPS/ci/aiku-ci-postgres" /usr/local/sbin/aiku-ci-postgres
install -m 644 "$DEVOPS/systemd/ci-fence.service" /etc/systemd/system/ci-fence.service
install -m 644 "$DEVOPS/systemd/var-lib-postgresql-18-ci.mount" /etc/systemd/system/var-lib-postgresql-18-ci.mount
install -D -m 644 "$DEVOPS/systemd/postgresql-ci.conf" /etc/systemd/system/postgresql@18-ci.service.d/aiku.conf
install -D -m 644 "$DEVOPS/cron/actions-runner-watchdog" /etc/cron.d/actions-runner-watchdog

shopt -s nullglob
for unit in /etc/systemd/system/actions.runner.*.service; do
  perl -pe 's/\{\{(\w+)\}\}/$ENV{$1}/g' "$DEVOPS/systemd/actions-runner.conf" | install -D -m 644 /dev/stdin "$unit.d/aiku.conf"
done
shopt -u nullglob

systemctl daemon-reload
systemctl enable --now ci-fence.service var-lib-postgresql-18-ci.mount

if [[ ! -d /etc/postgresql/18/ci ]]; then
  install -d -o pgci -g pgci -m 2775 /run/postgresql-ci
  chown pgci:pgci /var/lib/postgresql/18/ci
  pg_createcluster 18 ci -p 5433 -u pgci -g pgci -d /var/lib/postgresql/18/ci -s /run/postgresql-ci --locale C.UTF-8
fi
# A restart drops every connection, so a running CI job would lose its test databases: only on a real change.
ci_conf=/etc/postgresql/18/ci/conf.d/99-ci.conf
if cmp -s "$DEVOPS/postgres/helio-ci.conf" "$ci_conf" && systemctl is-active -q postgresql@18-ci; then
  systemctl enable -q postgresql@18-ci
  echo "test postgres unchanged, not restarted"
else
  install -D -o pgci -g pgci -m 644 "$DEVOPS/postgres/helio-ci.conf" "$ci_conf"
  systemctl enable postgresql@18-ci
  systemctl restart postgresql@18-ci
fi

echo "fence checks:"
fail=0
must_fail() {
  if runuser -u aiku_test -- timeout 3 bash -c "$2" >/dev/null 2>&1; then echo "  FAIL $1"; fail=1; else echo "  ok   $1 blocked"; fi
}
must_fail "boro postgres"          '</dev/tcp/10.0.0.3/5432'
must_fail "boro redis"             '</dev/tcp/10.0.0.3/6379'
must_fail "local redis"            '</dev/tcp/127.0.0.1/6379'
must_fail "local standby (tcp)"    '</dev/tcp/127.0.0.1/5432'
must_fail "local standby (socket)" 'psql -h /run/postgresql -p 5432 -d postgres -Atc "select 1"'
must_fail "/home/aiku"             'ls /home/aiku'
must_fail "sudo"                   'sudo -n true'
if runuser -u aiku_test -- psql -h /run/postgresql-ci -p 5433 -d postgres -Atc 'select 1' >/dev/null; then
  echo "  ok   test cluster reachable"
else
  echo "  FAIL test cluster not reachable"; fail=1
fi
exit $fail
