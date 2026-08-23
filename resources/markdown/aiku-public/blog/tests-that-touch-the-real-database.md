---
title: Tests that touch the real database
summary: 3,300 tests against a real PostgreSQL with 800 tables, restored from a dump before every file, run in parallel on ten processes in CI and by a dozen people and their AI agents at once locally — without stepping on each other. No mocks of the schema, no synthetic repositories. How the TEST_TOKEN trick makes that possible, and the four ways such a suite goes flaky.
date: 2026-08-20
tags: testing, pest, postgres, ci
---

There is a school of testing that says the database is slow and scary, so you put an interface in front of it and test against a fake. We do not do that. aiku's tests run against PostgreSQL — the real schema, nearly eight hundred tables, the real constraints, triggers, generated columns and the jsonb shapes — because that is where the bugs are. A fake repository cannot tell you that a unique index rejects your second shop code, or that a generated column is read‑only, or that a cascading delete took the stats row with it.

The numbers: about **3,300 tests** in 126 files, the big modules in single files of two to three thousand lines (ordering alone is 2,800 lines), against a **6 MB seeded dump** of the schema plus reference data. CI runs them in parallel on ten processes on every push.

## Restore, don't migrate

A fresh `migrate` of eight hundred tables takes minutes. A `pg_restore` of the dump takes seconds — sixteen parallel jobs locally, two in CI where the runner is smaller. So each test file begins by dropping its database, restoring the dump, and resetting every sequence to `max(id)`. The dump is regenerated whenever a migration lands (one script), which is also how we notice when a migration and its seeder disagree.

Inside a file, tests share that restored database and often build on each other: "can create an order" leaves an order that "can submit an order" picks up. That makes the files read like a story of the module, and it makes them fast. It also has a cost, which we will come to.

## The TEST_TOKEN trick

Parallel test runners solve isolation by giving each worker a numbered database. We borrowed the convention and extended it to *people*: our bootstrap reads a `TEST_TOKEN`, restores the dump into `aiku_test_<token>`, and rewrites the database name in the environment *before the application boots*.

```bash
TEST_TOKEN=abc$RANDOM php artisan test tests/Feature/OrderingTest.php
```

That one line is why a dozen engineers — and, these days, several AI coding agents per engineer — can run the same heavy feature files on the same machine at the same time without a single clash. Every session gets its own fully‑restored database, named after a token nobody else used. CI's ten workers are just ten more tokens.

Two rules came with it, both learned the hard way. **Tokens are lowercase alphanumeric** — an underscore or a capital produces a database name that is never created, and every test in the file fails with a "model not found" that looks exactly like a regression. And **never list a unit‑test file and a feature‑test file in the same run**: the token is applied by the feature file's bootstrap, so a unit file listed first boots the app against the shared database and the feature file silently runs there, picking up someone else's rows.

## What CI does that a laptop doesn't

Two jobs. The first runs the whole suite in parallel and stops on the first defect. The second re‑runs, twice and in isolation, **every test file the push touched** — because a test that only passes when run after its neighbours is a test that will fail on somebody else's branch next week.

## The four ways a suite like this goes flaky

Sharing a database within a file, and parallelising across files, reveals a particular set of sins. We have catalogued ours:

1. **Absolute counts on shared fixtures.** `expect(stats->number_emails)->toBe(1)` breaks the moment an earlier test feeds the same outbox. Measure the baseline, assert the delta.
2. **Grabbing "any" record.** A test that takes the first delivery note with one item lands on a different row depending on what ran before. Constrain the query to the invariants the assertion needs.
3. **Tests not updated with the code.** A feature PR that changes a guard or a route without touching its test — it passes on the author's branch in one order and fails in CI in another. Every route, guard or UI‑blueprint change is a test change.
4. **In‑file leakage.** Test one creates a template; test two asserts the table is empty. Clean what you assert on, or key fixtures per test.

We also learned that one infrastructure failure — a lock‑exhaustion on the CI database, say — aborts files early and *masks* the flakes behind it, so a green‑again push surfaces the next layer. When a run goes red, separate the infrastructure errors from the real ones first, then reproduce the real ones in full‑file context, not in isolation.

## The coverage number is not the point

A coverage percentage says which lines were *executed*, not which behaviours were *proven*. A suite of unit tests with mocked repositories can reach ninety per cent and never once exercise the path that matters: order in → stock reserved → picked short → amount recalculated → invoice raised → stats hydrated. Those are five tables, three jobs and a generated column talking to each other, and the only test that means anything walks the whole path against semi‑real data and checks the numbers at the end.

So we do not chase the coverage figure. We chase process paths: the ones a customer, a picker or an accountant actually travels, with the data shapes they actually produce. A hundred per cent coverage of code that never met the database is a comforting lie; sixty per cent of code that did, along the paths that move money and stock, is a test suite.

## Honest about the cost

This suite is a nightmare to create and a nightmare to maintain. Every schema change ripples into a dump regeneration and a dozen fixtures. Every new module means writing the fixture helpers before the first assertion. Dependent tests make a file read well and make a single early failure take the rest of the file with it. A flaky test in a three‑thousand‑line file is an afternoon. We are not going to pretend otherwise.

## Why we keep it this way — and why you should too

Because the suite catches the things that matter: the penny that rounds the wrong way on a discount, the order amount that should not move when a pick comes in short, the generated column that someone tried to write, the permission that is silently false without a team id. Every one of those was found by a test that spoke to the real database. A mocked repository would have passed.

It is not fast in the abstract — a full parallel run is minutes, not seconds — but it is fast enough to run on every push, which is the only speed that counts. And with a token per person, it is fast enough to run twelve times at once on a Tuesday afternoon while the agents are busy.

If you are building something whose value lives in its data — an ERP, a ledger, a warehouse — do this. Test against the real database, restored fresh, in parallel, with a token per person. It will cost you more than mocks on day one and every day after. It will also be the only thing standing between you and the bug that moves money. That trade is worth it every time.
