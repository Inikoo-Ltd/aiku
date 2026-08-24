---
title: Archiving a quarter of a billion emails without losing a number
summary: Eight years of sent email — 245 million rows, 217 GB, never pruned — was slowing down a daily rollup and bloating every developer's copy of the database. How we moved it to an archive server in verified batches, why the retention window is 90 days and not 60, and the one trick that keeps historical stats correct after the rows are gone.
date: 2026-08-16
tags: postgres, email, archive, performance
---

<aside class="tldr"><strong>TL;DR</strong>Eight years of email history — <code>245 million</code> rows, <code>217 GB</code> — was slowing a daily rollup and bloating every developer database dump. A batched, replica-aware archiver copies emails and their child tracking rows to a separate archive database, verifies row counts, then deletes, all within one transaction per batch. The retention window is 90 days, comfortably past the email provider's 60-day tracking cutoff. Archived counts are banked as baselines in the same delete transaction so historical statistics never silently shrink after rows are gone.</aside>

Every email aiku sends — order confirmations, newsletters, reorder reminders, password resets — is a row in `dispatched_emails`, with its delivery and open/click events as children. We had been doing that since 2018 and never thrown anything away. By August 2026 the production database was 489 GB and the email stack was 217 GB of it: 245 million emails, 332 million tracking events, 14 GB of stored email bodies, and a dozen pivot tables hanging off them.

Disk was not the problem. The problem was that a daily aggregation job was spending nine hours of database time a month walking that table, and that every developer pulling production down to work against real data was downloading two hundred gigabytes of email nobody would ever open again.

## How old is useful?

Before deciding what to keep, we measured when events actually arrive. Sampling 300,000 recent tracking events against the age of their email: 94% arrive within a day of sending, 98% within a week, 99.85% within thirty days — and **none after sixty**. That is not a tail, it is a wall: our email provider's open/click tracking window is sixty days by specification. After that an email *cannot* receive another event.

So any retention window longer than sixty days loses nothing by construction. We chose **ninety**: one and a half times the hard limit, leaving room for a delayed job or a clock that drifts, while cutting the live table by about 40% compared with the 180 days we had first sketched. Staff lookups of older mail — a customer‑service agent checking what a customer was sent last year — go to the archive automatically when the record is an order, and via a clearly labelled button elsewhere.

## The archiver

A single command runs in batches of five thousand emails until nothing older than the cutoff remains. Each batch:

1. **Waits for the replicas.** Bulk deletes generate write‑ahead log faster than a replica can replay it; an unbounded backlog once filled a disk. If any replica is more than a set number of megabytes behind, the archiver sleeps.
2. **Selects** the next batch of ids older than the retention window.
3. **Copies them to the archive database** — the emails and every child table. The children are discovered live from the database's foreign keys, not from a list in code, so a table added next year is archived without anyone remembering to add it. Archive tables are created on first use by cloning the live definitions. The copy is delete‑then‑insert per batch, so a crashed run simply redoes the batch it was on.
4. **Verifies** source and archive row counts per table for the batch. Any mismatch aborts; nothing on production is touched.
5. **Deletes, in one transaction with the step below.**

It runs single‑threaded and unattended. The initial backlog was roughly 224 million emails — about thirteen days — and the daily job afterwards handles the ~130,000 emails that age out each day in about eleven minutes.

## The trick: bank the baselines before you delete

This is the part that would have bitten us silently. Every "emails sent / delivered / opened" figure in the application is a fresh `count()` over the raw rows, recomputed by hydrators. Delete the rows and the next recount quietly rewrites last year's mailshot statistics with post‑deletion numbers. No error, no log line, just wrong history discovered months later by someone comparing two reports.

So every statistics row that counts emails — per outbox, per mailshot, per bulk run — carries a small JSON map of **archived baselines**, keyed by the counter it feeds. The archiver adds each batch's counts to those baselines *in the same transaction as the delete*. The hydrators add the baseline on top of every recount. The numbers never move, and the transaction boundary is load‑bearing: if either half fails, both roll back.

## Disk comes back only if you ask for it

Deleting rows does not return space to the operating system; PostgreSQL keeps it for reuse. After the backlog cleared we ran `pg_repack` on the affected tables and indexes. The measured result: roughly 220 GB reclaimed, the database down from 515 GB to about 295 GB, a 43% cut — and the indexes that the daily rollup walks shrank in proportion, which was the point.

## Things we checked and left alone

While we were in there we audited the table's indexes, expecting to drop a couple of multi‑gigabyte ones that no code seemed to use. Statistics over a clean week said otherwise: one "unused" index was read by something roughly weekly at 124 million entries a scan. We could not identify the consumer from inside the database (the statement cache was at capacity and evicting), so we kept it. An index you cannot explain is not the same as an index you can drop.

## What we would tell our past selves

Measure when the data stops being useful before choosing a retention number; the answer may be a hard external limit, which makes the decision easy. Verify every batch before deleting. And if anything in your system counts rows to produce history, write down the count *before* the rows leave — in the same transaction — or the history will rewrite itself.

<aside class="tldr bottom"><strong>In one paragraph</strong>Archiving old rows safely means measuring how long the data stays useful, verifying every batch, and banking any counts you'll need before the rows that produced them are gone.</aside>
