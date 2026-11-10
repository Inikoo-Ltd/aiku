---
title: Setting targets for products in development
summary: How aiku follows products paid as development step by step, proposes a target per hour once a step has enough runs, asks the reviewers to accept, adjust or keep the product on development, and lists what is overdue.
date: 2026-11-10
tags: production, targets, development, payroll
category: production
help_routes: grp.org.productions.show.crafts.development
---

<aside class="tldr">
A product switched to <b>In development</b> has no target, so its artisans are paid without a bonus. Aiku records how long each step of it takes and, once a step has enough runs (5 sessions from 2 artisans and 4 hours, by default), proposes the median per hour as its target. The reviewers get one task listing the products ready, and decide on <b>Factory → Crafts → Development → Paid as development</b>: <b>Save targets</b> (as proposed or adjusted) or <b>Keep on development</b> with a reason. When every step paid by the piece has a target, the product is no longer paid as development.
</aside>

## Where to find it

Open **Factory → Crafts → Development** and the **Paid as development** tab. It lists every product with **In development** switched on, with:

- **Status**: **Overdue** (ready for its targets and run in the last 4 weeks), **Ready, not run lately** (ready, but not made in the last 4 weeks, so it is not counted as overdue), **Collecting** (not enough runs yet) or **Kept on development** (someone decided to wait; hover to read why).
- **On development since**: when **In development** was last switched on.
- **Recipe changed**: the last time a step was added to or taken off the recipe. Products whose recipe has not changed since this was first recorded show a dash.
- **Last run**: the last session worked on the product.
- **Steps**: for each step, the sessions, artisans and hours counted so far and the rate per hour they give. A tick means the step is ready for its target; steps with a target show it, steps paid by the hour need none.

Overdue products are listed first. They stay overdue until somebody decides.

## How the proposed target is worked out

- Each step is worked out on its own. Sessions on the same step (same task, same label, same units per artefact) of every product in the family are counted together, so a new colour of a candle learns from the others.
- Only sessions since the recipe last gained or lost a step count: the work changed, so the timing starts again.
- Each artisan's first session on the step is left out as a learning run. So are sessions shorter than 10 minutes, sessions still waiting for their times to be checked after being left running past the shift, and obvious outliers (much faster or slower than the rest).
- A step is ready once what is left has at least 5 sessions, from at least 2 artisans, adding up to at least 4 hours.
- The proposed target is the median per hour of those sessions. Next to it you see the middle half and the full range, so you can tell how much the artisans vary.

The production manager or the accountant can change these numbers with **Settings** on the tab: sessions per step, different artisans, hours in total, the shortest session, and after how many weeks without runs a product is no longer overdue.

## The review task

In **Settings**, **Who reviews** chooses the people who get the task (the production manager and the accountant). Every morning aiku checks the products in development. When some are ready, the reviewers get one task, **Set targets for products paid as development**, with a line per product and the proposed rates. Products that become ready later are added to the same task. A line ticks itself once the product is decided, or once it is no longer waiting (taken off development, or its targets set by hand), and the task closes when every line is done.

Nobody chosen in **Who reviews** means no task is sent; the tab says so.

## Deciding

Press **Review** on a product. For each step you see the proposed target, the sessions counted and those left out (with why), and the targets of similar steps in the factory for comparison. Then:

- **Save targets**: the target box of each ready step is filled with the proposed rate. Keep it to accept, or type another rate to adjust it. Steps not ready yet are left empty; you can type a target for them too. The targets apply from today, so sessions already worked stay as they were paid.
- **Keep on development**: type why (for example, a new mould is coming). The product stays on development and comes back for review once its steps have as many new sessions again.

When every step paid by the piece has a target, **In development** is switched off and the product is paid against its targets from then on. If a step still has no target, the product stays on development until it does.
