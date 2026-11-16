---
paths:
  - 'devops/**'
---

# Devops

## Rebooting boro or litio: ordering, checks, recovery
supervisor must stay ordered After= Postgres, both Redis and MySQL (devops/systemd/supervisor.conf), and postgresql@/redis-server@ must wait for network-online (wait-for-network.conf). Without the ordering, a reboot stops everything at once, Horizon/Octane hang on dead connections and the reboot sits out the 1 h drain window. Without the network wait, the queue Redis (6380) and Postgres can't bind 10.0.0.3. Both caused the 55 min outage on 24 Oct 2026 (INI-099). Do not lower stopwaitsecs=3600; it is deliberate (6a31378a2d2).
Sessions live in their own persisted Redis on boro (redis-server@sessions, 6381, AOF). Never point the sessions connection back at the cache Redis on 6379: it runs without saves since 28 Sep 2026, so every restart there logs everyone out.
Before rebooting either host: horizon:pause on BOTH. After a boro boot, check `ss -ltn` shows 10.0.0.3:5432 and :6380, litio's replica is streaming and Horizon is running.
Stuck reboot (ping OK, SSH drops connections, Postgres closed) = usually a stuck SHUTDOWN. Use Robot > Rescue > vKVM + hardware reset; don't wait for the physical KVM. In the vKVM's GRUB, add systemd.debug-shell=1 and boot with F10 (the console swallows Ctrl); the shell is on Alt+F9.

## Varnish never answers a logged-in page request with a 304
The Cloudflare edge adds Last-Modified to the logged-out HTML it stores (origin sends none), so browsers keep that copy with a validator and revalidate it after login. Varnish answers If-Modified-Since from the object's age, so a 304 made the browser show its logged-out page to a logged-in customer (HELP-4671). vcl_recv drops If-Modified-Since and If-None-Match when X-Logged-Status is In; keep that, and never add Last-Modified or ETag to pages that differ by login state. Test: devops/varnish/tests/logged_in_revalidation.vtc. A VCL change is live only after it is applied and reloaded on both litio and boro.

## MySQL runs only on litio, without binary logs
Aurora's MySQL lives only on litio (10.0.0.1). boro's stale replica, which had not changed since Feb 2025, was removed with its data on 14 Nov 2026 (INI-258), and litio runs with disable_log_bin because nothing replicates from it. Don't reinstall MySQL on boro or turn binlog back on without a replica that needs it. mysql.service in supervisor.conf's After= is a no-op on boro and is kept for litio. On litio the default `php` CLI is 7.4 (for Aurora): run aiku artisan there with `php8.4`.
