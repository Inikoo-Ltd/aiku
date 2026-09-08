---
title: From paper tallies to task sessions — Part I
summary: The factory still runs on paper: a tally per worker per task, totted up on Friday. The manufacture module that replaces it is built, tested, imported from the old system locally, and not yet on the floor. Part I is the model — artefacts, recipes as ordered tasks with raw materials per step, job orders that expand into a work queue, and a "task session" that is the single payable fact, with the pay rate frozen at the moment it closes — and the decision to make every product a trade unit first so a candle made on Tuesday can be sold online on Wednesday.
date: 2026-08-20
tags: manufacture, production, design, warehouse
---

<aside class="tldr"><strong>TL;DR</strong>The factory still runs on a paper tally per worker per task, totted up Friday. The new manufacture module models a product as an artefact linked to a trade unit and stock like anything bought in, a recipe as ordered tasks with materials per step, job orders that expand into a work queue, and a task session as the single payable fact — its pay rate frozen the moment it closes. It is built, tested and locally migrated, not yet on the floor.</aside>

Our group makes some of what it sells: candles, aromatherapy blends, incense, a long tail of hand‑finished things. The making has run, for years, on paper — a tally sheet per worker per task, collected and totted up at the end of the week for pay. It works, and it is invisible: nobody can say, on Wednesday, how many units of what were made by whom, from which batch of wax, for which order.

The manufacture module replaces the paper. It is built, it is covered by tests, it has been fed with the old system's data in a local migration, and it is not yet in use on a real floor — rollout is a two‑week parallel run with the paper, one line at a time. So this is Part I: the model. Part II is what the floor thinks of it.

## Every product is a trade unit first

The first decision is borrowed from the rest of the system: the thing a factory makes is an **artefact**, and an artefact is linked to a **trade unit** and to an **org stock** exactly like anything we buy. That is what makes a candle made on Tuesday sellable on the website on Wednesday — the trade unit already has the images, the properties, the route to a product and a page — and what makes it count in the warehouse like any other stock. Raw materials get the same two links, because management wants to sell some of those online too, and because "how much wax do we have" is a stock question, not a factory question.

## A recipe is ordered tasks with materials per step

An artefact's **recipe** is an ordered list of **manufacture tasks** — melt, pour, wick, label, pack — each with *units per artefact* (a pour might make six) and, per step, the **raw materials** consumed per unit. The recipe editor shows the steps, their materials, the line costs and the subtotal; the costed recipe is the artefact's cost. Nothing about a recipe is inherited from the old system, which never had one, so recipes are authored fresh — which is a feature, because the people who know them are the people typing them.

## Job orders expand into a queue

A **job order** says *make this many of these*. Each item expands, automatically, into **job‑order item tasks** — one row per recipe step per item — which is the floor's work queue. The floor screen, on a tablet, shows a worker their available tasks and two buttons: **start**, **done**. The supervisor's screen shows who is working on what right now, the queue, and lets the supervisor start a task too, because supervisors work.

## The task session is the payable fact

This is the heart of it. A **task session** is one worker, one task, from *start* to *done*, with the quantity made and the rejects recorded separately. One open session per person, enforced. When the session closes, the **pay rate is snapshotted onto it** — the task's rate and the reward terms at that moment — and never recomputed. The Friday sheet becomes a query over sessions, and a rate change next month cannot rewrite last month's earnings. The [reward scheme](/blog/paying-for-pace-without-piecework) reads the same sessions.

Rejects are recorded and, by default, unpaid; whether that rule survives contact with the floor is one of the questions on the list for management.

## Receiving production into stock

A confirmed job order is **received into stock** in one atomic step: the quantity made at the final step becomes a **batch** with a code built from the job order and the artefact, a *production* stock movement puts it into the chosen location, and the recipe's materials are **deducted** — per step, converted through units‑per‑artefact, aggregated per input stock, taken from the picking location or the fullest one. From that moment the batch is traceable: batch → sessions → workers → raw materials. Waste tracking is on the list, not in the code.

## What the migration taught us

Three organisations make things; one of them is much bigger than the other two. The old system held artefacts and raw materials but no recipes, and kept job orders in the same table as purchase orders, distinguished by a type — one of those quirks you only find by reading the data. The import pre‑links artefacts and materials to their trade units and stock, imports the old material ratios as a default recipe step that hand‑built steps are allowed to replace, and is safe to re‑run. Locally it brought in about 5,700 artefacts, 7,300 recipe rows and 2,800 raw materials across the group.

## What Part I deliberately does not answer

Whether artisans should see only the subset of hundreds of artefacts they know; how a job order's reference should be minted; what the payroll export looks like once it replaces the spreadsheet; how waste is recorded; whether rejects are paid. These are written down as questions for management, with our defaults, and the defaults are the paper sheet's — because the way to get answers out of a busy factory is to ship something that works and wait for the complaints.

Part II will have those complaints, and the numbers.

<aside class="tldr bottom"><strong>In one paragraph</strong>The manufacture module is built, tested and migrated locally — artefacts as trade units, recipes as ordered tasks, and the task session as the frozen, payable fact — but it stays off the real floor until a two-week parallel run proves it against the paper it replaces.</aside>
