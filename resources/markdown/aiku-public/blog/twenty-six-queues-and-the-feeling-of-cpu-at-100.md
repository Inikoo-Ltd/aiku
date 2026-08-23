---
title: Twenty‑six queues, and the feeling of CPU at 100%
summary: Everything that is not a page view runs on Horizon — twenty‑six supervisors across two servers, from "urgent" to "historic backfill". It is the best thing about the system and was the source of its worst mornings: stray workers surviving a deploy and running a query 780,000 times an hour; a supervisor killed but its children kept alive so every queue ran twice; a retry‑after shorter than the job; ten thousand jobs in a queue from a loop that should have been one. What each one taught us, in the order they hurt — and how the beast was finally tamed.
date: 2026-08-01
tags: horizon, queues, ops, reliability, laravel
---

Open Horizon's dashboard on a normal afternoon and you see the whole business breathing: emails going out, stock counters recalculating, time series folding, search reindexing, marketplace portfolios syncing, a newsletter's recipient list being prepared, a backfill of two million historic jobs quietly draining at the back. **Twenty‑six supervisors**, each with its own queues, process counts, memory and timeout, split across the primary and the replica. Almost everything that makes aiku feel instant is something that was done on a queue a moment earlier.

That same dashboard, on a bad morning, shows CPU pinned at 100% and a number in the tens of thousands next to a queue name, and you know — before you know why — that a small bug has found a way to multiply itself. This note is about the queues, and about those mornings, because the second part is what taught us how to run the first.

## The lanes

The supervisors exist because jobs are not alike. A rough map:

- **urgent / normal / low‑priority** — the bread and butter; the staff expect "instant" from *urgent* (a mailshot's recipient list, a screen waiting on a result).
- **hydrators (replica)**, **stock‑control**, **stock‑history**, **price_change** — the [counter and valuation work](/blog/six-hundred-hydrators-and-thirty-five-time-series); most of it on the replica, where it cannot compete with an order being placed.
- **sales**, **sales (replica)**, **sales historic backfill** — time‑series processors; *historic backfill* is the lane for backfills, with its own worker count that is turned up overnight and down at nine.
- **ses‑send**, **ses‑analytics** — [email out, events in](/blog/a-quarter-of-a-million-emails-before-lunch).
- **dropshipping**, **dropshipping‑long**, **shopify (replica)** — [marketplace sync](/blog/eight-marketplaces-one-warehouse-queue), with a separate long lane for the unbounded bulk loops.
- **search**, **cache‑warming**, **analytics**, **metrics**, **trim** — index, warm, count, prune.
- **long‑running**, **long‑high‑priority**, **long‑low‑priority** — the honest admission that some jobs take an hour.

Two Redis connections back them: one with a short reservation window for fast jobs that should self‑heal quickly, one with a long window for jobs that genuinely run long. Which connection a supervisor uses is the single most consequential line in its config, as we will see.

## The mornings, in order of pain

**Stray workers after a deploy.** A deploy tells Horizon to terminate; workers finish their job and exit; the supervisor restarts them on the new code. Except sometimes they do not exit — a long job, a signal lost, an old master nobody noticed — and you end up with ~40 workers from a previous release, invisible to the dashboard, happily running old code. One morning those workers were running a per‑period customer metrics query **780,000 times an hour**, and the production database saturated: primary‑key lookups at a second, storefront pages at 1.3 s. The fix was two things: find and kill the orphans (and learn that `pgrep` missed the master because its command line ended differently on each host), and rewrite the processor so the same work is ~130× fewer queries. The refactor was overdue; the strays were what made it urgent.

**The supervisor that died but its children did not.** On a box where the process manager ran under a systemd unit with `KillMode=process`, stopping the unit killed only the manager. Its Horizon master was orphaned to PID 1; the unit restarted; now there were *two* masters, 24 supervisors each, 118 worker processes, 19 GB of memory — **every queue processed twice**. The tell was a job log with every line duplicated. The lesson, written down: count top‑level processes before you restart anything, and set the stop timeout longer than the drain you configured, or the kill arrives first.

**Retry‑after shorter than the job.** A supervisor popped jobs from the short‑window connection (160 s reservation) with a worker timeout of 1,000 s. Any job over 160 s was *released back* to the queue while still running, picked up again, and eventually failed with "max attempts exceeded" after doing its work three times. The rule that came out of it is one sentence — **retry‑after must exceed the worker timeout** — and the fix was a dedicated long lane rather than moving a whole supervisor, because short jobs want the short window's fast self‑healing.

**Ten thousand jobs from one loop.** A bulk upload dispatched one unique job per product; each unique dispatch costs about 400 ms of lock and serialisation; six hundred of them took five minutes *to dispatch*, which was itself longer than the reservation window — so the parent job was re‑released and dispatched them again. Mass‑dispatch loops are not "fast"; budget half a second per unique dispatch, and put the parent on the long lane too.

**A five‑second timeout on a six‑second job.** The analytics supervisor had a 5 s timeout; a hydrator's slow tail ran to 8 s; failures every night. Raised to 60. Not every lesson is deep.

**The O(n²) recipient list.** Preparing a newsletter's recipients with offset pagination over 77,000 subscribers took up to seventeen minutes and, on the *urgent* lane, blocked everything the staff expected to be instant. Cursor pagination took it to seconds. It stayed on *urgent*, because moving it would have been treating the lane as the problem.

## What we do now, because of all that

- **One deploy step** terminates Horizon, and the deploy script does it, not a person.
- **The stop timeout is longer than the drain** everywhere a process manager runs under another process manager.
- **Every supervisor's connection and timeout are chosen together**, and the rule is on the wall.
- **Bulk work fans out into chunk jobs**, parent on the long lane, children unique by id so a repeat collapses.
- **Backfills have their own lane** with a worker count we turn up at night and down in the morning.
- **The failed‑jobs table is read from the database**, not from screenshots, and every failure class gets a root cause, not a retry.

Horizon is still the best thing about the system. It is also a very efficient way to make a small mistake large. The haunting feeling — 100% CPU, a queue in the tens of thousands — is the system asking you which of the rules above you forgot. It has always been one of them.

And, for the record: we finally tamed the beast. The mornings described here are from the first half of this year. Since the rules went on the wall the dashboard has been boring — the backfill drains overnight at its own pace, deploys terminate cleanly, the failed‑jobs table is a short list with a reason next to each line, and the CPU graph looks like a business, not an alarm. Boring is what we were after.
