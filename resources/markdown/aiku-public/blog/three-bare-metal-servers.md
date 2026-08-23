---
title: Three bare‑metal servers
summary: No Kubernetes, no managed database, no serverless. aiku runs a multi‑country commerce operation on a handful of rented bare‑metal machines behind Cloudflare — a primary, a replica and a staging box — and we would choose it again. Why metal beats "cloud" for a workload like ours, what the boxes actually do, and the two incidents that are the honest cost of owning the iron.
date: 2026-08-12
tags: infrastructure, postgres, deploy, ops
---

<aside class="tldr"><strong>TL;DR</strong>aiku runs on three rented bare-metal boxes — a primary (PostgreSQL, Redis, Octane, queues, search, Varnish), a replica for read-heavy work, and a staging box — behind a CDN, with no Kubernetes or managed database. Metal wins because the workload is predictable, PostgreSQL wants dedicated RAM and disk, and the bill is flat. The trade-off is manual failover and no managed alarms, illustrated by two incidents: a disk filled by 377 GB of retained WAL, and a RAID resync that slowed a restore.</aside>

People assume that a system with 800 tables, a few hundred million email rows and storefronts in several countries must live on a large cloud account with autoscaling groups and a managed database. It does not. It lives on **three rented bare‑metal servers**, one small CDN in front of them, and a deploy script. This note is why, and what that looks like day to day.

## The shape

- **The primary.** One big box — 32 cores, 192 GB of RAM, fast local disk. It runs PostgreSQL as the primary, Redis, the long‑lived application workers ([Octane](/blog/moving-to-octane)), the queue workers, the search engine, the storefront renderer and the storefront cache ([Varnish](/blog/varnish-in-front-of-a-storefront-that-knows-who-you-are)). Behind a load balancer on the same host.
- **The replica.** A second box with a streaming PostgreSQL standby, a second Varnish, and its own application and queue workers. Read‑heavy work — historic reporting, time‑series backfills, the weekly staging dump — runs here so it never competes with an order being placed.
- **The staging box.** A cheaper machine in another country with a lot of slow disk: staging, the CI runner for the test suite, the email archive database, and a few static sites. It is rebuilt from the primary's configuration, not the other way round.

In front: a CDN for TLS, DNS and the first line of abuse handling. That is the entire production estate. We give the boxes names, the way you name things you expect to keep.

## Why metal

**The workload is known.** A wholesale business does not have the traffic shape that autoscaling was invented for. We know when the warehouse is busiest, when the newsletters go out, when the accountants close a month. Capacity is a planning exercise once a year, not a per‑minute decision made by a control plane we do not understand.

**PostgreSQL wants iron.** The database is the product. It wants RAM for its cache, fast local disk for its WAL, and a CPU that is not shared with a neighbour. A large dedicated server gives all three for a fraction of what the equivalent managed instance costs, and the managed version would still make us choose between the instance size that fits the budget and the one that fits the working set. On metal we do not have to choose; the whole working set is in memory and has been for years.

**The bill is a number, not a surface.** A rented server costs the same on the 31st as on the 1st. There is no egress charge on the two hundred gigabytes a developer pulls down to work against real data, no line item for the Varnish hit rate, no surprise when a backfill runs all weekend. Predictability is worth more to a trading company than elasticity it will never use.

**Latency is where the customers are, not where the region is.** Our customers and warehouses are in a handful of European countries. A box in the same continent, with the CDN's edge doing TLS close to the visitor, is as fast as anyone needs. "Edge compute" solves a problem we do not have.

**One deploy script, one kind of machine.** Everything that runs is a process under a supervisor on a Linux box we can SSH into. The deploy is a release directory, a symlink and a reload. When something is wrong at three in the morning, `top`, `journalctl` and `psql` are the whole toolkit, and they are the same on every host.

## What we do not get, and how we cope

No automatic failover: the replica is a manual promotion. We accept that; the runbook is short and the replica is always minutes behind at most. No object store by default: media lives on local disk and is rsynced; backups are dumps streamed off the primary. No managed search, no managed cache: Typesense and Redis run as packages, and their versions are pinned in our setup script because a mismatch once cost an afternoon.

We also do not get someone else's on‑call. Which brings us to the honest part.

## The cost, in two incidents

**The disk that filled.** One morning the primary's root filesystem hit 100%, PostgreSQL stopped, and every staff request failed (the storefronts stayed up, served entirely from the Varnish cache — which is the best argument for that cache we have). The culprit was not a log. The replica had fallen behind, and the replication slot on the primary was faithfully **retaining 377 GB of WAL** for it. The fix was to let the replica catch up; the lesson was a monitoring rule — *check retained WAL first on any disk alert* — and a hard "never drop the slot to reclaim space". A managed database would have had that alarm for us. We have it now.

**The RAID resync that wasn't done.** The staging box, freshly installed with four large disks in RAID 10, was mysteriously slow for a day. The array was still resyncing in the background, and a bulk restore on top of it crawled. Pause the resync, load, resume. Twenty minutes of reading, once.

Neither incident lost data. Both would have been invisible on a managed platform, and both taught us something about our own system that we would rather know.

## What we would tell a team deciding

If your traffic is spiky and unknowable, or your team is two people who never want to see a shell, the cloud's premium buys something real. If your workload is a known shape, your database is your product, and you have people who can read `iostat`, rent big machines, put a CDN in front, write the deploy script once, and spend the difference on engineers. It has worked for us for years, and the bill still fits on one line.

<aside class="tldr bottom"><strong>In one paragraph</strong>Three plain rented servers, a CDN, and a deploy script have run a multi-country commerce operation for years, at a predictable cost and with two honest, well-understood incidents to show for it.</aside>
