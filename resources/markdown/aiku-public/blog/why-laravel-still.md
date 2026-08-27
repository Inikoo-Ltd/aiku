---
title: Why Laravel (still), from a codebase that cannot afford to be wrong about it
summary: In 2022 we picked Laravel because it was the easiest way to find programmers for a system that would take years to build. In 2026 we would pick it again, for a reason that did not exist then — the models know it better than any hire ever would. What the choice bought us, what it cost, and what "is Laravel still worth it" actually asks.
date: 2026-08-21
tags: laravel, architecture, hiring, ai, php
---

<aside class="tldr"><strong>TL;DR</strong>aiku is one Laravel codebase running ERP, warehouses, storefronts and marketplaces for a live business: 793 tables, 6,100 action classes, 369 production releases in five months. We chose the framework in 2022 because a mid‑sized company outside a tech hub could hire for it. Four years on the framework is still the right call, but the hiring argument has been replaced by a stranger one: AI coding agents are astonishingly fluent in Laravel, and a convention‑heavy, well‑documented framework is exactly what lets them work safely in a big monolith. The costs are real too — we dropped to raw PostgreSQL in a dozen places and built our own aggregation layer — and none of them were Laravel's fault. And the larger truth: with agents writing most of the code, the framework matters less than ever; what is left is the part that was always scarce: architecture, knowing what can go wrong, thinking five steps ahead.</aside>

Every few months someone asks, on a forum or in a hiring call, whether Laravel is *still worth it in 2026*. The question usually hides a different one — "will I be embarrassed by this choice" — and it is normally answered by people who have built a demo with it. We have not built a demo. We have built a [system with 793 tables](/blog/793-tables-and-counting) that runs real warehouses, and we have to be right about this choice every day. So here is the honest version.

## Why we chose it in 2022

aiku started in August 2022 as an empty Laravel application [beside a twenty‑year‑old system](/blog/four-years-of-walking-out-of-the-old-house) that it would take years to replace. The decisive factor was not elegance and it was not benchmarks. It was **who we would be able to hire**.

We are a trading company, not a software company, and not in a city where engineers grow on trees. We needed a stack where a good programmer could be found, onboarded in weeks, and be productive inside a large codebase without a year of context. PHP had the pool; Laravel had the conventions that make one PHP programmer's code look like another's. Rails would have been a fine answer to the same question in 2012; Django, in a team that already wrote Python; Node, if we wanted to spend our lives choosing packages. In our circumstances Laravel was simply the best odds of having a team in two years' time. It was.

There were smaller reasons — the old system was PHP too, which meant every fetcher could be written by someone who could read both sides — but the hiring argument was the one that carried the room.

## What it bought us

Four years later the list is long and mostly boring, which is the point.

- **A pipeline we did not have to invent.** Queues with [Horizon](/blog/twenty-six-queues-and-the-feeling-of-cpu-at-100), a long‑lived application server with [Octane](/blog/moving-to-octane), search with Scout, a front end over [Inertia](/blog/rendering-the-storefront-twice), a deploy that is [a Deployer task list](/blog/anatomy-of-a-deploy). Each of these is first‑party or first‑party‑adjacent, maintained by people who are paid to maintain it, and each one has let us ship a capability in days that would have been a quarter elsewhere.
- **An upgrade path that kept working.** We went from Laravel 10 to 13 without a rewrite, in a codebase that was changing underneath us at seventeen releases a week. Laravel is a framework that takes its own upgrade story seriously; that matters more than any feature when you are four years in.
- **Conventions as a team multiplier.** Nineteen people have landed code this year. Most of them could read any corner of the repository on day one because a controller, a job, a policy and a migration look the same everywhere. The action pattern we settled on — [six thousand small classes](/blog/369-production-releases-in-five-months) — is a Laravel‑community idea, not ours.
- **Testing against the real thing.** Pest, a [real PostgreSQL in CI](/blog/tests-that-touch-the-real-database), parallel runs. We did not have to build a testing culture from nothing; we had to decide to use the one that came in the box.

## What it cost

A framework is a set of defaults, and defaults have a ceiling. We hit it in the places you would expect, and in every case the answer was to step outside the framework, not to fight it.

- **The ORM at volume.** Seventeen million stock movements, a quarter of a billion emails, time series over thirty‑five intervals — [the aggregation layer](/blog/six-hundred-hydrators-and-thirty-five-time-series) that keeps dashboards honest is written in SQL and triggered from PHP. Eloquent is for reading a customer; it is not for summing a year.
- **PostgreSQL features with no framework spelling.** Generated columns, partial indexes, non‑deterministic collations, `jsonb` everywhere — the schema is more PostgreSQL than Laravel, and the migrations say so in raw statements.
- **The parts nobody ships in a box.** Carrier APIs, marketplace sync, stock valuation, a tax engine for Europe. The framework gave us HTTP clients and queues; the domain was ours to get wrong, and we did, repeatedly.

None of this is a case against Laravel. It is a description of what a framework is: the first eighty percent, done well, and a clean way out for the rest.

## The reason that did not exist in 2022

Here is the part we did not see coming. The hiring argument is weaker than it was — not because the PHP pool shrank, but because **a good part of the code in this repository is now written with AI coding agents**, and the question has become which stacks the agents are actually good at.

The answer, to a degree that surprised us, is Laravel. The framework's decade of documentation, tutorials, conference talks and open‑source packages is exactly the kind of corpus a model learns from, and its conventions mean that "the Laravel way" to do a thing is usually one thing, not five. An agent asked to add a queued job, a policy, a scoped query or a migration in this codebase gets it right far more often than it gets it wrong — and when it is wrong, it is wrong in a way a reviewer recognises in seconds, because it still looks like Laravel.

That changes the framework calculus. In 2022 we wanted a stack a new hire could learn fast. In 2026 we want a stack a model already knows, with conventions strong enough that the model's output is reviewable at speed. Laravel scores higher on the second test than it ever did on the first. The [cadence went up](/blog/369-production-releases-in-five-months) without the bar coming down, and the framework's predictability is a large part of why that was possible.

We are not claiming that this makes Laravel uniquely suited to the moment. We are saying that the thing people worry about — "is there still a community, is there still a pool" — has quietly been answered by something else, and the answer is better than the one we had.

## And, honestly: the framework matters less than it ever did

There is a larger point hiding here, and we might as well say it. In the age of AI agents, **the choice of language and framework is close to irrelevant.** It used to be the decision: it fixed who you could hire, what you could read, how fast a feature took. Now the agent writes the queued job, the migration, the Vue component, the test — in whatever the repository is written in — and a team that switched stacks tomorrow would be productive in the new one by Friday. The syntax was never the hard part; it was the only part that was expensive to learn, and that cost has collapsed.

What is left is what was always the actual work, and it is not typing. It is being an **architect**: deciding where the boundaries go, what is a fact and what is a cache, which table is the truth when two disagree. It is knowing the domain — what a delivery note is allowed to do, how an invoice becomes a fact, what a stock valuation setting means. It is **knowing what can go wrong** before it does, being professionally paranoid about money, stock and anything that crosses a system boundary, and **thinking five steps ahead**: what this column will mean when there are seventeen million rows in it, what happens to the open orders when someone changes the units of a product, who gets paged when the queue that was fine at ten thousand jobs meets a million. And it is the discipline around the code: tests that gate, a deploy that is boring, conventions strong enough that a reviewer can read an agent's diff at speed.

None of those are properties of a framework, and none of them are things the agent brings, because they are not learned from documentation. They are learned the hard way: the cold feeling when you realise the migration you just deployed is rewriting invoices; the ten‑o'clock call on a Friday night that the server is down and the warehouse starts at six, and you are two glasses in, fixing it over SSH on a phone screen; the morning you discover a "harmless" bulk update quietly rewrote three hundred dispatched lines of history. Every one of those is in this repository somewhere, as a guard, a test, or a note, and every one of them cost somebody a night's sleep. The model has read about all of them. It has not lived through any, and that is the difference between knowing that a thing can go wrong and *feeling it in your stomach* before you press enter.

The agent is very good at producing the code you asked for. Whether you asked for the right thing — and whether you noticed the three ways it could fail a year from now — is still entirely on you. That is the skill set that matters now, and it is the one that was always scarce.

So the honest answer to "is Laravel still worth it" is: yes, and it is the wrong question. Pick a mature, well‑documented, conventional stack — Laravel is an excellent one — and then spend your worry on the things the model cannot know for you.

## What "is Laravel still worth it" actually asks

It almost never asks about the framework. It asks, in order: is the language embarrassing; will I find people; will it scale; will it be maintained. Our answers, from the inside:

- The language argument is a decade out of date. PHP 8.4 with strict types and a modern framework is not the PHP people remember, and the people who remember it are not the ones hiring.
- You will find people, and increasingly you will need fewer of them than you thought, for reasons above.
- It scales in the way that matters — three bare‑metal servers, one database, a warehouse that does not stop — as long as you are willing to [step outside it](/blog/three-bare-metal-servers) when the database is the better tool.
- It is maintained by a company whose business depends on it being maintained, with a release cadence that has not missed in years.

## What we would do differently

We would choose it again, and we would worry about it less. We would reach for raw PostgreSQL sooner and with less guilt. We would adopt the action pattern from day one rather than year two. And we would stop answering the forum question, because the only honest answer is "here is what it looks like after four years; decide for yourself" — which is this note.
