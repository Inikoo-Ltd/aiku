# Production servers: surviving everything up to an extinction-level event

Four servers run aiku production. Three share a rack with a dedicated switch (boro, litio, helio);
neon sits in another rack in the same Helsinki data centre. This page describes the target layout
agreed on 27 September 2026 and the playbook for every failure case, from one box down to losing
Helsinki entirely.

No passwords, tokens or IP addresses belong on this page: the repo is public.

## Who does what

| Server | Role |
|---|---|
| **boro** | Postgres primary. Queue Redis (own instance, never evicts). Database backups. A small fallback Horizon (one worker on each essential queue) and a second scheduler. The standby web node: HAProxy, Varnish, Octane and SSR stay installed and deployed, idling on a few workers. |
| **litio** | Web: HAProxy, Varnish, Octane, Inertia SSR. Cache and session Redis. Postgres replica (the failover target, also serving web reads). The light, customer-facing Horizon queues. Aurora, until it is retired. |
| **helio** | The bulk Horizon queues (reading and writing the primary on boro, never the replica). The scheduler. Staging. The CI runner. Staging and CI run with hard memory caps and always give way to production. |
| **neon** | NightOwl, the archive database, the WordPress sites. First copy of the backups. |

litio and boro have the same processors and disks, which is why litio is the failover for the
database and not helio. litio has less memory, so as the primary it runs slower until boro is back.

boro is the standby for the web because it is the one server known to carry web and database
together: it did both before this layout.

### Services by name

Every `.env` refers to the moving parts by name, never by address:

| Name | Normally on |
|---|---|
| `db-primary` | boro |
| `db-replica` | litio |
| `redis-queue` | boro |
| `redis-cache` | litio |

Each server maps these names in `/etc/hosts`. Moving a service during a failure means editing one
line of `/etc/hosts` on every server that uses it, then reloading the app (below). No `.env` edit,
no config cache rebuild, no deploy.

### Reloading the app after a change

```bash
cd ~/aiku/anchor/octane && php artisan octane:reload
cd ~/aiku/current && php artisan horizon:terminate
```

Supervisor restarts Horizon on its own. Octane and SSR run from `anchor/octane`, Horizon and the
scheduler from `current`.

### Horizon queues by server

| Server | Queues |
|---|---|
| litio | `urgent`, `default`, `price_change`, `stock-control`, `search`, `ses-send`, `cache-warming` |
| helio | `hydrators-slave*`, `sales*`, `analytics`, `stock-history`, `low-priority`, `dropshipping*`, `long-*`, `aurora`, `ses`, `ses-analytics`, `shopify-slave`, `translate*` |
| boro | one worker each on `urgent`, `default`, `sales`, `stock-control`, `price_change`, `ses-send`, `search` |

Worker counts are set per server with the `HORIZON_*_WORKERS` variables in that server's `.env`.
Never put `long-*`, `aurora`, `analytics`, `*_historic`, `stock-history` or the bulk hydrators on
boro, not even during an outage: the database box must not run out of memory.

The scheduler runs on helio and boro at the same time. Almost every task is `onOneServer()`, so
each one runs once; the few that are not are safe to run on both.

### Backups

pgBackRest runs from boro: a full backup weekly, an incremental daily, and the write-ahead log
archived continuously, so the database can be restored to any second, not just to last night.
Two copies: one on neon (outside the rack, fast to restore from), one offsite in another region
(survives losing Helsinki). The offsite copy keeps its own snapshots, which no server can delete,
so a compromised server cannot wipe the backups with it. Media files are backed up to the same two places.

Two further copies of the database sit completely outside our infrastructure and any cloud
provider, as the last resort if everything above is lost at once:

- **A secret location:** a copy at most 3 days old.
- **A secret mobile location:** a copy at most 2 weeks old. It moves, so no single geographic
  event can reach it.

Their whereabouts are known to the people who need to know and are deliberately not written down
here.

The weekly staging refresh on helio restores from the neon copy, so every week proves the backups
work.

## When one server fails

### boro is down (database primary)

The site is down for writes until litio is promoted. Target: back up in 15 minutes.

1. Confirm boro is really gone: no ssh, and the Hetzner Robot panel shows it down or unreachable.
   A slow boro is not a dead boro. Never promote while boro may still accept writes, or the two
   databases split.
2. Promote litio: `sudo -u postgres psql -c "SELECT pg_promote();"`
3. On every server, point `db-primary` and `db-replica` at litio, and `redis-queue` at helio
   (start helio's Redis if it is not running; queued jobs that were only in boro's Redis are lost,
   the hydrators and scheduled tasks rebuild what matters).
4. Stop litio's Horizon so litio can carry the web and the database together.
5. Reload the app everywhere.
6. When boro is back, it rejoins as the replica (`pg_basebackup` from litio). Switch back in a
   quiet window, or leave the roles swapped: the two machines are the same.

### litio is down (web and replica)

The site is down until traffic reaches boro. Target: back up in 10 minutes.

1. In Cloudflare, point the origin records for aiku.io, app.aiku.io and the customer domains at
   boro. They are proxied, so the change is immediate.
2. On boro, start the standby cache Redis instance (capped, evicts old keys). On helio and boro,
   point `db-replica` at boro (reads go to the primary) and `redis-cache` at boro. Everyone gets
   logged out once: sessions lived in litio's Redis.
3. Raise boro's Octane and SSR workers to full size, and run the light queues on helio.
4. Reload the app.
5. Do not deploy while litio is out: litio is the deploy's main host. If the outage is long, make
   boro the main host in `deploy/deploy.php` first.
6. When litio is back, rebuild its replica from boro, then move the web back.

### helio is down (workers, scheduler, staging, CI)

The site stays up. The bulk queues pile up in Redis and wait; nothing is lost.

1. Nothing urgent: boro's scheduler carries on, the essential queues keep running on litio and boro.
2. If helio will be out for more than a few hours, run a few bulk workers on litio outside peak
   hours. Watch litio's memory and cut them first if the site slows down.
3. Staging and CI stop. Merge nothing that has not passed.

### neon is down (monitoring, archive, first backup copy)

The site stays up.

- NightOwl telemetry buffers on each server for a while, then drops. Nothing in the app depends on it.
- Archived emails and audits are not shown; pages fall back to live data and never error.
- The WordPress sites are down.
- The first backup copy is unreachable. Watch free disk on boro: if the write-ahead log stops
  archiving it piles up there. If neon will be out for more than a day, switch the neon copy off
  in the pgBackRest config so the offsite copy carries on alone.

## When several fail together

These are unlikely. Each gets harder, and the first question is always the same: **how long until
Hetzner brings the machines back?** If the answer is a few hours, waiting is usually faster and
safer than rebuilding.

### boro and litio together (both databases)

1. Restore the database onto helio from the neon backup copy, to the latest moment archived.
   Expect a few hours for a full restore.
2. Point `db-primary`, `db-replica` and `redis-queue` at helio. helio runs the database, the web
   and a reduced set of queues until boro or litio come back.
3. When they return, rebuild them from helio and move the roles back one at a time.

### The whole rack (boro, litio and helio)

Only neon is left, with the first backup copy.

1. Ask Hetzner for an estimate. If it is short, wait.
2. If not, order a replacement server (same type as boro) and restore onto it from neon.
   `devops/setup-server.sh` renders the server configs; `dep deploy` installs the app.
3. As a stopgap while the new server is provisioned, neon can restore the database and serve a
   slow site on its own.
4. Point Cloudflare at whichever box is serving.

### Helsinki is gone (all four servers)

Only the offsite backup copy remains.

1. Order servers in another Hetzner location: one for the database, one for the web to begin
   with. Ask for the same types.
2. Run `devops/setup-server.sh`, restore the database and media from the offsite copy, deploy.
3. Point Cloudflare at the new servers. DNS and Cloudflare live outside Hetzner, so nothing else
   has to move.
4. Rebuild the search index (`php artisan search -r`). Queued jobs and caches are lost and
   rebuild themselves.

Expect most of a day. Orders placed after the last archived write-ahead log (usually minutes
before the loss) have to be re-entered from the payment providers and marketplaces.

### The offsite copy is gone too

Restore from the copy in the secret location (at most 3 days old); if that is unavailable, from
the one in the secret mobile location (at most 2 weeks old). Then follow the Helsinki steps above.
Everything after the copy's date has to be rebuilt from outside records: payment providers,
marketplaces, couriers, and suppliers' own order confirmations.

## After any failover

- Post in Discord what moved where, so nobody deploys or restarts into the old layout.
- Update the table at the top of this page if the roles stayed swapped.
