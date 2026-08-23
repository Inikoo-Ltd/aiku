---
title: Telemetry you can query with SQL
summary: When the application moved to long‑lived workers, the commercial APM agent we had run for years stopped telling the truth — and on the test runner it stopped the tests. We replaced it with NightOwl, an open‑source telemetry stack for Laravel: an agent on each box, a local buffer that survives outages, and every request, job, query, exception and log line landing as rows in a PostgreSQL database we own, on a server we already pay for, with retention measured in years. The best part arrived by accident — an AI assistant connected straight to that database with read‑only SQL.
date: 2026-08-01
tags: observability, telemetry, postgres, mcp, open-source
---

For years the application wore a commercial APM agent. It was fine for a PHP‑FPM world: a process boots, handles one request, reports, dies. Then we moved to [Octane](/blog/moving-to-octane) — one process, thousands of requests — and the agent's model stopped matching ours: transactions blurred into each other, background work was invisible, and a few metrics were simply wrong. On the CI runner it was worse: the agent wraps PHP's exception handler in a way the test framework did not expect, and the whole suite died on memory before running a single test. We removed it from the runner, then from everywhere.

## What we wanted

A picture of the system that matched how the system actually runs: requests *and* queue jobs *and* scheduled commands *and* the SQL each of them issues, with the exceptions and log lines in the same timeline, across both servers. We wanted to keep it for a long time, because "did this get slower since March" is a question we ask. And we did not want a second bill that scaled with our own traffic.

## NightOwl

We settled on [NightOwl](https://usenightowl.com), an open‑source telemetry stack for Laravel. The shape of it is the reason it fits:

- **An agent on each server** — a small daemon the application talks to over a local TCP port. The app's cost is a write to a socket; the agent does the rest. It keeps a **SQLite buffer on disk**, so if the network or the store is down for an hour, nothing is lost: the buffer drains when things come back. (We watched it backfill a fifteen‑minute gap after an incident, unprompted.)
- **A PostgreSQL store** — the agent drains into plain tables: requests, jobs, commands, queries, exceptions, logs, notifications, with day‑partitioned raw tables and hourly/daily roll‑ups, low‑cardinality strings normalised into dictionaries. It is a database, not a product; you can `\dt` it.
- **A dashboard** on top for the everyday view — slow requests, failing jobs, exception groups — that reads the same tables.

We host the store ourselves, on the staging box: a machine we already run for [backups, the CI runner and the email archive](/blog/three-bare-metal-servers), with slow, enormous disks that are exactly right for telemetry. Retention is therefore not a plan tier; it is how much disk is left, and the answer is *years*. The software is free. The server we needed anyway.

## The accident: SQL to the telemetry

The part we did not plan became the part we use most. Because the telemetry is PostgreSQL, we gave an AI assistant a **read‑only role** on that database and connected it through a local tunnel — the same way the assistant already reads the application's own data through [MCP](/blog/an-mcp-server-for-a-whole-business). Now "which jobs got slower after Thursday's deploy", "show me the requests over two seconds on the storefront this morning", "how many times did this exception fire per hour last week" are questions answered with a query, in seconds, with the rows to prove it — no dashboard clicking, no export.

It is also how several of the incidents written up elsewhere on this site were diagnosed: the [stray workers](/blog/twenty-six-queues-and-the-feeling-of-cpu-at-100) running a query 780,000 times an hour were found by grouping the query table by hour; the [cache warmer's surge](/blog/warming-the-cache-three-times) by counting storefront renders per minute around a deploy.

## What we learned moving

- **Storage formats change; write the fence down.** An agent upgrade moved to a new table layout; the old tables kept answering with stale data. The date of the switch is in our notes, and every query goes to the new tables.
- **Not every `_id` is a dictionary key.** Some columns are inline values; joining them to the dictionary "works" by numeric coincidence and returns nonsense. We learned which ones the hard way and wrote them down.
- **Gate the agent, not the code.** A configuration switch can silence telemetry on a box without anyone noticing for days; we now check that the telemetry is *arriving*, not just that the agent is installed.
- **Own the data.** The moment telemetry was rows we could query, it stopped being a monitoring product and became part of the system's own memory.

## The bill

Zero for the software. The server was already there. The assistant's queries cost what a query costs. We would not go back to a per‑host subscription for a picture that was, in the end, less true.
