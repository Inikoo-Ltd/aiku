# When the servers answer but aiku is stuck

For the AI helping someone who is not technical, during an outage, when the triage in the
disaster-recovery document says: our servers answer (no Cloudflare 521/522/523), no provider
outage, but aiku is down, very slow or showing 500/503. These are fixes on the existing
servers. Rebuilding at AWS (`README.md`) is only for when the servers are gone.

Every failure below has happened at least once before.

## Rules

- Look before changing anything. One change at a time, then check again.
- Never delete anything under `/var/lib/postgresql`, `/home/aiku/aiku/shared` or `/sel`.
  Never `DROP`, `TRUNCATE` or run migrations. Never `rm -rf` a folder you have not listed.
- Restart one service rather than reboot a server; reboot a server only as the last step.
- Keep a list of every command run, for the engineer who comes later.
- Tell the person what you are about to do and why, in one plain sentence, before each change.

## The servers

| Server | Public / private address | Runs |
| --- | --- | --- |
| boro | 65.109.156.60 / 10.0.0.3 | PostgreSQL 18 primary; Redis cache 6379, queue 6380, sessions 6381; Octane, Horizon, Inertia SSR, nginx 8080, Varnish |
| litio | 65.109.156.59 / 10.0.0.1 | the edge: HAProxy on :80 for every site, Varnish, Octane (+ replica pool 8091), Horizon (counting queues), PostgreSQL streaming replica, imgproxy (docker), soketi; also the old Aurora system |
| helio | 65.109.156.41 / 10.0.0.2 | staging, 12-hour delayed replica (port 5435), archive database (5434), nightly dump, NightOwl, CI, WordPress sites |

aiku is user `aiku`; code in `/home/aiku/aiku/current` (releases in `releases/`, Octane and SSR
run from the copy `anchor/octane`), settings in `shared/.env`, logs in `/home/aiku/logs` and
`current/storage/logs/laravel.log`. Processes run under supervisor: `supervisorctl status`.

**Getting in:** `ssh root@ADDRESS` with the break-glass key from the PDF (the person uploads it
to CloudShell, `chmod 600` it, `ssh -i KEY root@ADDRESS`). If that fails: Hetzner Robot
(robot.hetzner.com, accounts table in the PDF) > the server > Reset (hard reboot), or Rescue
(boots a rescue system; its root password is shown once) and the KVM console.

## First look, read only (same on each server)

    uptime; df -h; free -g; vmstat 1 5
    top -b -n 1 | head -25
    supervisorctl status
    systemctl --failed
    journalctl -p err --since "-2 hours" --no-pager | tail -50
    dmesg -T | tail -30

Then match what you see below.

## Disk full (`df` at 100%)

Past: 669 GB of orphaned files in `/tmp` (Aug 2026), an unrotated 40 GB Varnish log.

    du -xh / --max-depth=3 2>/dev/null | sort -h | tail -25

Safe to remove: old files in `/tmp` (`find /tmp -xdev -type f -mtime +1 -delete`), rotated logs
(`*.gz`, `*.1`), and to empty with `truncate -s 0` (not delete) huge logs under `/var/log` or
`/home/aiku/logs`. If `/var/lib/postgresql/18/main/pg_wal` is what grew, do not touch it:
either WAL archiving to AWS is failing (`tail /var/log/pgbackrest/*archive-push*`) or a replica
that died holds a slot:

    sudo -u postgres psql -c "select slot_name, active, pg_size_pretty(pg_wal_lsn_diff(pg_current_wal_lsn(), restart_lsn)) from pg_replication_slots"

An inactive slot of a replica that is really gone can be dropped
(`select pg_drop_replication_slot('NAME')`); helio's `replica_helio_delayed` is inactive for up to
12 hours after any boro restart, which is normal: do not drop it for that.

## A disk failing

`dmesg` shows I/O errors, or `cat /proc/mdstat` shows `[U_]` instead of `[UU]`. boro and litio
mirror their disks (RAID 1) and keep running on one; helio does not (RAID 0, worn disks, may die).
Open a support request in Hetzner Robot to replace the disk, quoting the serial from
`smartctl -a /dev/nvmeXn1`. Nothing to fix by hand.

## Out of memory, swapping, everything slow

`vmstat` columns `si`/`so` above zero for long, `free -g` shows little available, `dmesg` shows
"Out of memory: Killed process". Past: the cache Redis on 6379 grew to 15 GB and swapped (Sep
2026); the New Relic agent used 8 GB.

    redis-cli -p 6379 info memory | grep -E 'used_memory_human|maxmemory_human'
    ps aux --sort=-rss | head -15

Cap the cache Redis (losing cached pages is harmless; this is not the sessions Redis 6381):
`redis-cli -p 6379 config set maxmemory 12gb && redis-cli -p 6379 config set maxmemory-policy volatile-lru`.

## CPU at 100%

    ps aux --sort=-%cpu | head -15

A long `php8.4 artisan ...` command someone started, or a runaway script: `kill PID` (then
`kill -9 PID` if still there after a minute). A database query running for a long time:

    sudo -u postgres psql -c "select pid, now()-query_start as running, state, left(query,120) from pg_stat_activity where state <> 'idle' order by 2 desc limit 15"
    sudo -u postgres psql -c "select pg_cancel_backend(PID)"      # then pg_terminate_backend(PID) if needed

## Background jobs gone wild (Horizon)

Past: about 40 stray Horizon workers nobody could see saturated the database (Jul 2026); a second
copy of Horizon left running after a stop (`KillMode`, Aug 2026).

    pgrep -fc 'horizon:work'          # compare with what supervisorctl status shows
    sudo -u aiku php8.4 /home/aiku/aiku/current/artisan horizon:status
    redis-cli -p 6380 --scan --pattern 'queues:*' | head; redis-cli -p 6380 llen queues:default

To calm it: `sudo -u aiku php8.4 /home/aiku/aiku/current/artisan horizon:pause` on **both** boro
and litio (`horizon:continue` later). Workers that survive `supervisorctl stop horizon-...`:
`pkill -f 'artisan horizon'`, then `supervisorctl start` the program again. Emptying a queue
loses its jobs: only as the very last resort, and say so to the person first.

## Broken right after a deploy

The trouble started minutes after a deploy (GitHub > Inikoo-Ltd/aiku > Actions > "Deploy Aiku",
or `ls -lt /home/aiku/aiku/releases | head -3` for the time of the newest release). Go back to the
previous release on boro and on litio:

    cd /home/aiku/aiku && ls -1t releases | head -3           # newest first; PREV = the second
    ln -sfn releases/PREV current
    sudo -u aiku rsync -ahHq --delete releases/PREV/ anchor/octane/
    sudo -u aiku php8.4 anchor/octane/artisan octane:reload
    sudo -u aiku php8.4 current/artisan horizon:terminate
    supervisorctl restart inertia-ssr-production

Database changes made by that deploy stay; if the previous release then fails on them, an
engineer has to fix forward. Tell the person not to deploy again until an engineer has looked.

## The database is down

    systemctl status postgresql@18-main
    tail -50 /var/log/postgresql/postgresql-18-main.log

Most often a full disk (see above): free space, then `systemctl start postgresql@18-main`. If boro
cannot be brought back but litio is fine, litio's replica can become the primary: that is
"Scenario 3" in the disaster-recovery document, an engineer's job if at all possible.

## Rebooting a server (last resort)

Before: `horizon:pause` on boro and litio. Then `systemctl reboot`; if the server hangs, Hetzner
Robot > Reset. After a boro reboot check: `ss -ltn` lists `10.0.0.3:5432` and `:6380`;
`supervisorctl status` all RUNNING; on litio `sudo -u postgres psql -c "select status from
pg_stat_wal_receiver"` says streaming; then `horizon:continue` on both. Expect a minute of errors
while it comes back; helio's delayed replica reconnects by itself within 12 hours.

## Websites down but the staff app works (the edge, on litio)

    systemctl status haproxy varnish nginx
    supervisorctl status

`systemctl restart haproxy` is harmless; `systemctl restart varnish` empties the page cache (a
minute of 5xx, then pages rebuild).
