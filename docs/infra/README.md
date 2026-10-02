# Production servers: surviving everything up to an extinction-level event

Three servers run aiku production, all in one rack with a dedicated switch in Helsinki: boro, litio
and helio. This page describes the target layout agreed in late September 2026 and the playbook for
every failure case, from one box down to losing Helsinki entirely.

No passwords, tokens or IP addresses belong on this page: the repo is public.

## Who does what

| Server | Role |
|---|---|
| **boro** | Postgres primary. Queue Redis (own instance, never evicts). Database backups. The light, customer-facing Horizon queues, at low CPU priority so the database always comes first. The scheduler. The standby web node: HAProxy, Varnish, Octane and SSR stay installed and deployed, idling on a few workers. |
| **litio** | Web: HAProxy, Varnish, Octane, Inertia SSR. Cache and session Redis. Postgres replica (the failover target, also serving web reads). The heavy Horizon queues, under a hard memory cap. The scheduler. Staging, with small caps, stopped whenever litio has to take over from boro. Aurora, until it is retired. |
| **helio** | No production role. The CI runner, fenced off from everything else. NightOwl monitoring (its own Postgres, low disk priority). The WordPress sites. The forecast service (TimesFM), which the nightly forecasts call; it runs on litio until litio takes over the web. |

litio and boro have the same processors and disks, which is why litio is the failover for the
database. litio has less memory, so as the primary it runs slower until boro is back.

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
| boro | `urgent`, `default`, `sales`, `stock-control`, `price_change`, `ses-send`, `search` |
| litio | `long-*`, `analytics`, `*_historic`, `stock-history`, `hydrators-slave*`, `low-priority`, `dropshipping*`, `aurora`, `ses`, `ses-analytics`, `shopify-slave`, `translate*`, `cache-warming` |

Worker counts are set per server with the `HORIZON_*_WORKERS` variables in that server's `.env`.
Every job reads and writes the primary, never the replica, even when it runs on litio.
Never put `long-*`, `aurora`, `analytics`, `*_historic`, `stock-history` or the bulk hydrators on
boro, not even during an outage: the database box must not run out of memory.

The scheduler runs on boro and litio at the same time. Almost every task is `onOneServer()`, so
each one runs once; the few that are not are safe to run on both.

### Backups

pgBackRest runs from boro: a full backup weekly, an incremental daily, and the write-ahead log
archived continuously, so the database can be restored to any second, not just to last night.
The copy lives on a storage box in another region, outside the rack and outside Helsinki. It keeps
its own snapshots, which no server can delete, so a compromised server cannot wipe the backups with
it. Media files are backed up to the same place.

Two further copies of the database sit completely outside our infrastructure and any cloud
provider, as the last resort if everything above is lost at once:

- **A secret location:** a copy at most 3 days old.
- **A secret mobile location:** a copy at most 2 weeks old. It moves, so no single geographic
  event can reach it.

Their whereabouts are known to the people who need to know and are deliberately not written down
here.

The weekly staging refresh on litio restores from the storage box, so every week proves the
backups work.

## When one server fails

### boro is down (database primary)

The site is down for writes until litio is promoted. Target: back up in 15 minutes.

1. Confirm boro is really gone: no ssh, and the Hetzner Robot panel shows it down or unreachable.
   A slow boro is not a dead boro. Never promote while boro may still accept writes, or the two
   databases split.
2. Stop staging on litio, and cut litio's heavy Horizon queues to a handful of workers: litio now
   carries the web and the database together.
3. Promote litio: `sudo -u postgres psql -c "SELECT pg_promote();"`
4. On litio, start a queue Redis instance. On every server, point `db-primary` and `db-replica` at
   litio, and `redis-queue` at litio. Queued jobs that were only in boro's Redis are lost; the
   hydrators and scheduled tasks rebuild what matters.
5. Run the light queues on litio.
6. Reload the app everywhere.
7. When boro is back, it rejoins as the replica (`pg_basebackup` from litio). Switch back in a
   quiet window.

### litio is down (web, replica, heavy queues, staging)

The site is down until traffic reaches boro. Target: back up in 10 minutes.

1. In Cloudflare, point the origin records for aiku.io, app.aiku.io and the customer domains at
   boro. They are proxied, so the change is immediate.
2. On boro, start the standby cache Redis instance (capped, evicts old keys). Point `db-replica`
   and `redis-cache` at boro. Everyone gets logged out once: sessions lived in litio's Redis.
3. Raise boro's Octane and SSR workers to full size.
4. Reload the app.
5. The heavy queues wait in Redis until litio is back; nothing is lost. Staging is down.
6. Do not deploy while litio is out: litio is the deploy's main host. If the outage is long, make
   boro the main host in `deploy/deploy.php` first.
7. When litio is back, rebuild its replica from boro, then move the web back.

### helio is down (CI, monitoring, WordPress)

The site stays up.

- CI stops. Merge nothing that has not passed.
- NightOwl telemetry buffers on each server for a while, then drops. Nothing in the app depends on it.
- The WordPress sites are down.
- The nightly forecasts are skipped. Dashboards keep the last forecast for the month, or fall back to last year's pattern.

## When several fail together

These are unlikely. Each gets harder, and the first question is always the same: **how long until
Hetzner brings the machines back?** If the answer is a few hours, waiting is usually faster and
safer than rebuilding.

### boro and litio together (both databases)

1. Stop CI on helio.
2. Restore the database onto helio from the storage box, to the latest moment archived. Expect
   several hours for a full restore.
3. Point `db-primary`, `db-replica`, `redis-queue` and `redis-cache` at helio, and point Cloudflare
   at helio. helio runs the database, the web and the light queues until boro or litio come back.
4. When they return, rebuild them from helio and move the roles back one at a time.

### The whole rack, or all of Helsinki

Only the storage box copy remains.

1. Ask Hetzner for an estimate. If it is short, wait.
2. If not, order servers, in another Hetzner location if Helsinki itself is out: one for the
   database, one for the web to begin with. Ask for the same types.
3. Run `devops/setup-server.sh`, restore the database and media from the storage box, deploy.
4. Point Cloudflare at the new servers. DNS and Cloudflare live outside Hetzner, so nothing else
   has to move.
5. Rebuild the search index (`php artisan search -r`). Queued jobs and caches are lost and
   rebuild themselves.

Expect most of a day. Orders placed after the last archived write-ahead log (usually minutes
before the loss) have to be re-entered from the payment providers and marketplaces.

### The storage box copy is gone too

Restore from the copy in the secret location (at most 3 days old); if that is unavailable, from
the one in the secret mobile location (at most 2 weeks old). Then follow the steps above.
Everything after the copy's date has to be rebuilt from outside records: payment providers,
marketplaces, couriers, and suppliers' own order confirmations.

## After any failover

- Post in Discord what moved where, so nobody deploys or restarts into the old layout.
- Update the table at the top of this page if the roles stayed swapped.
