---
title: Forty‑two searchable things
summary: The search box in the staff app finds a customer, an order, a pallet, a mailshot, an employee, a chat message — forty‑two model types — scoped to what you may see, typo‑tolerant, in one keystroke. How it runs on Typesense next to the application, why three places must change in lockstep when a new thing becomes searchable, the reindex command that rebuilds a section while people keep working, and the afternoon the search engine's raft log got stuck.
date: 2026-04-20
tags: search, typesense, architecture, ops
---

<aside class="tldr"><strong>TL;DR</strong>The staff search box covers 42 model types on Typesense running next to the app, scoped to what each user may see via scope keys on every indexed document. A hard rule keeps it working: when a model becomes searchable, its Scout schema, the reindex command's section list, and the search action's scope map must change together, or it silently returns nothing. Reindexing runs per section without stopping the business, and a stuck raft log once froze writes behind a healthy-looking health check.</aside>

Press the search key anywhere in the staff app and type three letters. Before the fourth, the panel shows customers, orders, products, invoices, delivery notes, pallets, employees, mailshots, web pages, chat messages — whatever you are allowed to see, from whichever part of the system, ranked, with a typo forgiven. **Forty‑two model types** are searchable today. This note is how that works behind the box, and the operational lessons that came with running a search engine in‑house.

## Typesense, next to the app

The engine is Typesense, installed as a package on the same servers as the application, talked to over HTTP from the same process that serves the page. We chose it over a hosted service for the same reasons we run [bare metal](/blog/three-bare-metal-servers): latency that is a local round‑trip, a bill that is a number, and a version we pin in the setup script because a mismatch between two boxes once cost an afternoon. Laravel Scout is the glue on the model side; the raw HTTP API is used where Scout's abstraction is in the way (synonyms, curation, the storefront's multi‑search).

## One collection per thing, one document per row

Every searchable model has a **schema** — which fields are indexed, which are facets, which sort — and a `toSearchableArray()` that flattens the row into a document: the code, the name, the customer, the shop, the state, and critically the **scope keys** (group, organisation, shop, warehouse) that let a query be restricted to what the user may see. Indexing is queued: a change to a model pushes a small job onto the *search* lane, so the box is a few seconds behind the database and never in its way.

The query side is one action per area — customers, catalogue, inventory, dispatching, accounting, HR, chat… — each knowing which collections to hit and which scope filter to add for the current user. The box fans out to the areas the user has permission for, merges, and returns. A warehouse user searching "000071" sees the delivery note; a sales user sees the order; an accountant sees the invoice; all three typed the same thing.

## Three places, in lockstep

The one operational rule we wrote in capitals: when a new model becomes searchable, **three places change together** — the schema registry in the Scout config, the reindex command's section list, and the search action's scope map — or search for that model silently does nothing. There is no error; the results just never appear. So the count of models with the search trait must equal the count of schemas must equal the count of reindex calls, and a grep for each is part of the review of any pull request that adds one. The last few additions (reviews, employees, chat messages) each needed all three touched.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Search wiring lives under <code>app/Actions/Search</code>, including <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Search/WithTypesenseApi.php">app/Actions/Search/WithTypesenseApi.php</a>, the trait used where Scout's abstraction is bypassed for the raw HTTP API.</li>
<li>Typesense metrics for the ops side are pulled by <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Overview/GetTypesenseMetrics.php">app/Actions/Overview/GetTypesenseMetrics.php</a>.</li>
<li>Laravel Scout's own docs cover the <code>toSearchableArray()</code>/engine-driver shape this note builds on: <a href="https://laravel.com/docs/scout">laravel.com/docs/scout</a>; Typesense's collection/schema model is documented at <a href="https://typesense.org/docs/">typesense.org/docs</a>.</li>
</ul></aside>

## Reindexing without stopping the business

`search -s customers -r` rebuilds one section: drop the collection, recreate it from the schema, stream the rows through the queue. Sections are independent, so a schema change on invoices does not touch products, and the box keeps answering from the other collections while one is rebuilding. A full reindex is a loop over sections and takes a while; we have never needed one in working hours. After a bulk SQL write that bypassed the model events — a migration repair, say — the rule is to reindex the affected section, because the queue never saw the change.

## The afternoon the raft log stuck

Typesense runs a single‑node raft log even when it is one node. One day the health endpoint said 200 and every authenticated request hung. The log told the story: *known applied* one behind *committed*, pending writes frozen for hours, the thread pool exhausted with six thousand queued tasks. The health check needs no thread, so it kept smiling. A restart replayed the log for about two minutes of "not ready or lagging" and came back with nothing lost. Lessons: a health endpoint that does not exercise the thing you depend on is decoration; and write down the restart incantation for each box, because the service manager's own command was broken that day.

## The same engine, two faces

This is the staff search. The storefront search — typo tuning, synonyms across fourteen languages, boosts, the semantic arm, the buyers' gap report — is the [other half](/blog/what-people-type-into-the-search-box), on the same engine with the product collections, tuned for a different audience. And the small one you may have just used to find this page runs on it too, with its own collection of notes; the fallback if the engine is unreachable is a plain substring match, because a search box that says nothing is worse than one that is slightly dumber.

## What we would tell a team starting

Put the scope keys in every document and filter on them server‑side; never filter after. Keep the "three places" rule as a grep, not as memory. Make reindexing per section and queue‑driven. And test your engine's health the way the application uses it, not the way the engine describes itself.

<aside class="tldr bottom"><strong>In one paragraph</strong>Forty-two searchable model types run on one Typesense instance next to the app, held together by scope keys on every document and a "three places in lockstep" rule that is the difference between a new model appearing in search and silently not.</aside>
