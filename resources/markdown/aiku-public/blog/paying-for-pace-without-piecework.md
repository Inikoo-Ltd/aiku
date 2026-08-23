---
title: Paying for pace, without piecework — Part I
summary: Coming soon in the manufacture module — a production reward scheme that pays people by the hour at a band earned by their pace, never below the legal wage, with targets derived so that faster work costs the business the same per unit. Why we did not build piecework, how the spreadsheet became rows with dates, and the live ladder on the shop‑floor screen.
date: 2026-08-25
tags: manufacture, payroll, hr, design
---

<aside class="tldr"><strong>TL;DR</strong>Coming to the manufacture module: a banded hourly pay scheme replacing a manual spreadsheet, not piecework. Four performance bands are earned by pace against a per-task standard rate, with band zero pinned to the statutory minimum wage; band targets are set so cost per unit stays the same at every band. It's built and tested but not yet switched on — pay bands, standard rates and pay-calculation snapshots are now dated rows, and a live ladder on the shop-floor screen shows the operator their current band in real time.</aside>

Our manufacturing floor has, for years, run a reward scheme out of a spreadsheet: a tab of tasks, a tab of targets, a tab of rates, a weekly tab that someone fills in by hand. It works, it is fair in intent, and it is one person's afternoon every week. We have rebuilt it inside aiku's manufacture module. It is built and tested and not yet switched on, so this is a note about the design rather than the results. Those come later.

## Not piecework

The tempting model is *pay per unit*. We did not build it. Piecework pushes quality down and stress up, and it breaks the moment someone spends an hour on maintenance or training. The scheme we inherited, and kept, is **banded hourly pay**: you are paid for the hours you work, at a rate set by the *band* you earned, and the band comes from your pace against the target for the task you were doing. Four performance bands, earned by speed; a couple of flat rates for development work that has no target by nature.

Band zero is the statutory minimum wage, and it is enforced as a floor in the code and in the data, per organisation — a band row can never be created below it.

## Cost‑neutral by construction

The clever part of the original spreadsheet, which we kept and made explicit: the targets for the higher bands are set so that **the cost per unit is the same at every band**. Faster work earns a higher hourly rate and produces proportionally more units, so the business pays the same per candle whether it was made at band one or band three. We store one *standard rate* (units per hour) per task and derive the four targets from it with the band multipliers; a test asserts the cost‑neutrality holds within a couple of percent, so nobody can quietly break it by editing one target.

## The spreadsheet becomes rows with dates

Every number that used to be typed into a cell is now a row: a **pay bands** table with an *effective from* date, so a rate change next April is a new row, not an edit; a standard rate on each manufacture task; a snapshot of the pay calculation on every work session, so the payslip can be explained months later even if the bands have changed since. An import command reads the existing sheet (its columns are detected by header, because real sheets drift), flags rows that disagree with what is already recorded, and refuses to create a task whose product family does not exist — the sheet had a few of those, and a hard failure is kinder than a silent orphan.

## Reasons, from day one

Time on the floor that is not production — maintenance, cleaning, waiting for materials, training — used to land in a free‑text column. Now it needs an activity type and a reason code before the session can be closed. This is the one rule we chose to enforce before asking anyone, because the first question any review of the scheme will ask is *where did the non‑productive hours go*, and the answer has to already exist.

## A ladder on the screen

The shop‑floor screen shows the operator their current band as a small ladder with a ticker: where they are, how far to the next rung, as of now. It is the same calculation the payroll uses, sharing the same *effective at* scope, so there is never a difference between what the screen promised and what the payslip says. We think the live feedback is the part people will actually care about; it turns a retrospective scheme into one you can steer during the shift.

## What is still open

Whether a quality gate should hold a band back, and at what threshold; how a rate review gets requested and approved; reconciling attendance against the production sessions; a KPI view for supervisors. All of these are rows and switches in the data — *requires approval*, *quality threshold*, *effective from* — not code, so that when management answers the open questions, the change is an edit, and when they do not, the defaults are the spreadsheet's.

We will write the second half of this note when it has run for a few pay periods and the numbers are real.

<aside class="tldr bottom"><strong>In one paragraph</strong>Banded hourly pay, cost-neutral by construction and built as dated rows instead of a spreadsheet, replaces piecework without breaking the floor's original logic — the results come once it has run for real.</aside>
