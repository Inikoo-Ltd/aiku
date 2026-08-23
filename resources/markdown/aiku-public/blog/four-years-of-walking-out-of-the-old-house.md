---
title: Four years of walking out of the old house
summary: We did not rewrite the twenty‑year‑old system and flip a switch. We built the new one beside it, pulled the data across entity by entity with 106 fetchers, ran both for years, and moved one company at a time — 588,000 customers, 1.08 million invoices, eventually 88 million rows of history. The "source id" on every row, the fetchers that could not be allowed to overwrite what people had edited in the new system, the guard that froze the catalogue, the import of two decades of audit trail, and the rule that a closed shop must rest in peace.
date: 2026-08-17
tags: migration, architecture, data, postgres
---

The system aiku replaced had been running the business since the early 2000s. It was good — better than most of what was on the market when it was written — and by 2022 it had one database per company, a schema nobody dared change, and a backlog of wants that would take years to build inside it. The usual answer is a rewrite and a cut‑over weekend. We did not do that, and the way we did not do it is the most consequential architectural decision in the repository.

## Build beside, pull across, switch one at a time

aiku started in August 2022 as an empty Laravel application and a decision: every entity in the old system would get a **fetcher** — a small class that reads the old row, translates it, and stores or updates the new one, writing the old primary key into a `source_id` column on the new row. Customers, products, orders, invoices, stock, locations, suppliers, purchase orders, employees, clockings, emails, web pages. Today there are **106 of them**, and 115 tables carry a `source_id`.

With fetchers, the migration is not an event, it is a *process*. A company's data is pulled in full, then kept in sync by scheduled fetches, while its staff keep working in the old system. The new system fills up with real data; the new screens are built against it; people try them. When a company's part of the new system is complete, that company *switches*: its staff log into aiku, and the fetchers for that company stop following. The next company is still on the old system, still being fetched. Four years, five companies, one at a time. The last modules — [procurement](/blog/buying-through-an-agent-part-i) and [manufacture](/blog/from-paper-tallies-to-task-sessions-part-i) — are leaving the old house now.

The counts, today: 588,000 customers and 1.08 million invoices carry a source id. The fetch runs that brought them across are part of the same queue system that runs everything else.

## The hard part: who may overwrite whom

Once a company has switched, its staff edit things in aiku. If a fetcher for that company runs again — a scheduled sync, a manual resync, a fetcher called *transitively* by another fetcher that still legitimately follows — it will overwrite the new edits with the old values. That is the single most dangerous thing in a long migration, and we got it wrong more than once before getting it right:

- First, a **following list**: which organisations are still fetched at all, and an **allow‑list of fetchers** per phase, both in config. A command gate refused anything not allowed.
- Then we found the *transitive* hole: fetcher A (allowed) calls fetcher B's `run()` to resolve a related row, and B writes — ignoring the gate, because the gate was on the command. The fix was to move the check into the one static entry point every fetcher shares, so a disallowed fetch reached through any path behaves like a lookup miss.
- Then the **catalogue tier was frozen**: a guard wraps every fetcher entry point, and the shared update chokepoint that all update actions pass through refuses to modify a protected model — trade units, products, categories, stocks, masters — unless the row was *created* in this very run. Create‑only survives; updates do not; a `--force` flag does not bypass it. Fifteen migration‑era maintenance commands that could write the catalogue from the old system were deleted outright.

The principle, stated late and held since: **the new system is the truth for anything its people have touched; the old system may only add, never change.**

## Two decades of audit trail

Late in the project management asked for the old system's *history* — who changed what, when — to come across too: roughly **88 million rows** spanning four companies and twenty years, in four different markup eras, with localised field labels, character‑set damage and the occasional credential that should never have been logged. Twenty‑one parsers, each a pure function from an old history row to an audit entry on the right new model (a "Part" history might belong to a trade unit, an org stock or a supplier product — the parser decides); a value extractor that knows the four eras and repairs the text; a dispatcher that resolves the target by source id and parks what it cannot yet resolve. Keyset pagination, after an offset bug skipped half the rows the first time. Some categories were deliberately skipped with management's agreement, because nobody would ever read them. The result is a single audit timeline per record that starts in 2004.

## Closed shops rest in peace

A migration drags in everything, including the shops that closed years ago. We decided they must be **pristine**: every product discontinued, no open orders, no customer balances, stock written off as of the closing date. Not deleted — history is history — but incapable of generating a new fact. A sweep enforces it; a verification battery proves it; the invariant is a sentence anyone can check.

## What we would tell a team facing the same

Do not cut over; pull across. Put the old key on every new row and keep it forever. Decide early, and in code, which side may write what — and put that decision in the one place every writer passes through, because the dangerous writes are the transitive ones. Bring the history; people will want it later. And when a thing is closed, make it *unable* to change rather than merely hidden.

It took four years. It never once required a weekend of downtime, and nobody had to learn the new system before it could do their job.
