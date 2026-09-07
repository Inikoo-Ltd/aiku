---
title: aiku existed inside Aurora before aiku existed
summary: The new system's name wasn't invented for a rebrand — and it wasn't even the first name. In October 2020, years before aiku served its first request, a file called trait.Aiku.php appeared inside Aurora, pushing entity changes to a system that barely existed. The app that eventually caught those changes was born as "Pika" — an experiment to extract warehouse picking into a micro-service, started on a warehouse visit in Eastern Europe — and only became aiku by a rename ticket in March 2023. This is the archaeology of a name.
date: 2026-08-24
tags: history, aurora, origins, migration
---

<aside class="tldr"><strong>TL;DR</strong>Oct 2020: <code>trait.Aiku.php</code> lands in Aurora, mixed into the base class of its hand-rolled ORM, pushing changes over HTTP with an <code>AIKU_TOKEN</code> — the name and the domain existed before the product. Aug 2022: the Laravel app is born, and its first tickets say <code>PIKA-2</code>, <code>PIKA-3</code> — Pika began as an experiment to pull warehouse picking out into a micro-service. Mar 2023: ticket <code>PKA-170</code>, "Change project name from pika to aiku". Apr 2025: Aurora learns to announce changes onto a stack table named <code>Stack Aiku Dimension</code>. Late 2025: the arrows reverse — <code>from_aiku_id</code> flows back into Aurora.</aside>

Naming a system is supposed to be a decision. A meeting, a shortlist, someone checking domains. aiku never got that. Its name was already three years old the day the project adopted it — and the project itself had spent its first eight months under a different name entirely. Both names are still in the git history, timestamped, for anyone to check.

## October 2020: the name, before the thing

The file that started it carries its own birth certificate, [in the usual style](/blog/the-copyright-headers-were-a-travel-diary):

```
// trait.Aiku.php
Author: Raul A Perusquía-Flores (raul@aiku.io)
Created: Fri, 16 Oct 2020 15:03:29 Malaysia Time, Kuala Lumpur, Malaysia
Copyright (c) 2020. Aiku.io
```

The Laravel application that today runs the warehouses, the storefronts and this blog did not exist. But the domain did, the email did, and the trait did. It was mixed into `class.DB_Table.php` — the base class of Aurora's hand-rolled ORM, the one every customer, order and invoice extends:

```php
include_once 'trait.Aiku.php';

abstract class DB_Table extends stdClass
{
    use Aiku;
```

The hook wasn't added to some entities; it was added to *the concept of an entity*. The first version was modest: for a handful of dimensions — staff, users, stores, customers — it pushed the change over HTTP to whatever was listening on the other end, authenticated with a constant named, hopefully, `AIKU_TOKEN`. What was listening, in 2020, was barely more than the token's name. The commit message from that day says, with no ceremony at all: *"trains in aikus"*.

<figure><img src="/art/readme/draw-note-aiku-inside.svg" alt="Watercolor sketch: an old house labelled AURORA with a small glowing side door labelled trait.Aiku.php, crates of orders, customers and stock flowing along a dotted line to a newer building labelled aiku, and a teal arrow labelled from_aiku_id flowing back the other way" width="1200" height="700" loading="eager"><figcaption>The escape hatch, and — from 2025 — the arrow that points back.</figcaption></figure>

## August 2022: Pika

The body the name would eventually inhabit started somewhere else. On a visit to one of our warehouses in Eastern Europe, watching the picking floor, I started an experiment: extract *just the picking* out of Aurora into a micro-service. Small scope on purpose. I prototyped it on Lumen, Laravel's micro-framework, and seriously considered Slim.

The framework choice was the whole point. After [fourteen years of hand-rolling everything](/blog/born-in-a-singapore-coffee-shop) — router, ORM, templates, job runner — the risk wasn't that my stack was bad. It was that it was *mine alone*. Nobody on earth could be hired who already knew it. Going mainstream wasn't a technical upgrade; it was insurance that someone else could ever help.

The experiment got a name: Pika. And it refused to stay small. The repository that today holds aiku opens on 9 August 2022 with the commit *"initial laravel app"*, and the tickets that follow tell you what it was called: `PIKA-2 Marketing external homepage`, `PIKA-3 Register/Login`, `PIKA-5 App layout`. On the Aurora side, the same months fill with `pika api`, `fetch pika part`, `pika deleted delivery note` — the old system learning to talk to the picking experiment that was quietly becoming a platform.

## March 2023: the rename that wasn't a decision

By early 2023 Pika wasn't a picking micro-service. It was a Laravel application growing fetchers, layouts, auth — the beginnings of everything. The name that fit had been sitting in Aurora's base class since 2020. The switch is one ticket:

```
2023-03-24  PKA-170  Change project name from pika to aiku
2023-03-24  PKA-174  Logo: Pika -> Aiku
```

No announcement, no meeting. The experiment grew into the name that had been waiting for it. (Project codenames are a tradition here — the 2008 system's tickets said `KAKTUS`, the picking experiment said `PIKA`; the products just eventually catch up.)

## April 2025: the stack

The HTTP push of 2020 was fine for four entity types. The [full migration](/blog/four-years-of-walking-out-of-the-old-house) needed something sturdier, and in April 2025 the trait got its final form — *"fetch aurora by stack"*. Every change now lands in a table literally named `Stack Aiku Dimension`:

```sql
insert into `Stack Aiku Dimension`
  (`Stack Aiku Creation Date`, `Stack Aiku Last Update Date`,
   `Stack Aiku Operation`, `Stack Aiku Operation Key`)
values (?,?,?,?)
ON DUPLICATE KEY UPDATE
  `Stack Aiku Last Update Date` = ?,
  `Stack Aiku Counter` = `Stack Aiku Counter` + 1
```

Not an event bus, not a message broker — a stack, in a MySQL table, with spaces in the column names because that's how Aurora's schema had always been. Debounced by the `ON DUPLICATE KEY` clause: a product saved fifty times in an hour is one row with a counter at fifty, not fifty jobs. Eighteen `trait.*Aiku.php` files — `CustomerAiku`, `OrderAiku`, `DeliveryNoteAiku`, `InvoiceAiku`, down to `TimesheetAiku` — teach each domain class what to announce. Read together they're a census of everything the old system considered real, and a to-do list for everything the new one had to hold.

## The arrows reverse

The best part is recent. For twenty years, data was born in Aurora and flowed outward. Then, in October 2025, commits like *"create prospect from aiku"* appear in Aurora's log — and with them files like `api_aiku_create_customer.php`, which does something the 2020 trait could never have imagined: it receives a customer created in aiku, stores the new system's id in a column called `from_aiku_id`, and keeps Aurora's copy in sync.

The old system, which spent five years announcing its changes to the new one, now takes dictation from it. Aurora is still running — [it gets patched to serve aiku's needs to this day](https://github.com/inikoo/aurora) — but the direction of truth has flipped, one entity at a time, exactly the way it began: quietly, from the basement, under a name that arrived before the thing it named.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Aurora is public: <a href="https://github.com/inikoo/aurora">github.com/inikoo/aurora</a>. The trait: <code>trait.Aiku.php</code> (created 16 Oct 2020, commit "trains in aikus"; HTTP-push first version, stack version from "fetch aurora by stack", Apr 2025); the mixin: <code>class.DB_Table.php</code>; the per-entity traits: <code>ls trait.*Aiku*</code> (18 files).</li>
<li>aiku is public too: <a href="https://github.com/Inikoo-Ltd/aiku">github.com/Inikoo-Ltd/aiku</a> — first commit "initial laravel app" (9 Aug 2022), <code>PIKA-*</code> tickets through Mar 2023, rename in <code>PKA-170</code>/<code>PKA-174</code> (24 Mar 2023).</li>
<li>High-churn entities (favourites) skip the stack and go through Aurora's hand-rolled fork mechanism (<code>utils/new_fork.php</code>) — the same trade-off aiku makes today when it chooses a queue over a hydrator.</li>
<li>The reverse path: <code>api_aiku_create_customer.php</code>, <code>api_aiku_create_prospect.php</code>, and the <code>from_aiku_id</code> column on <code>Customer Dimension</code>; first commits Oct 2025.</li>
</ul></aside>
