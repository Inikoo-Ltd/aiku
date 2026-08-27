---
title: Seven hours ahead of the warehouse
summary: The engineers are in Southeast Asia — a big city and an island people go to on holiday; the warehouses, the sales floor and most of the staff are in Europe. That is six to seven hours of offset, every day, for four years. It shaped almost everything in how we work — the quiet morning deploys, the ticket that has to carry the whole conversation, the boring release, the hand-off note — and it is the honest reason some of our best habits exist. The advantages, the costs, and what we would tell a team considering it.
date: 2026-08-22
tags: remote, workflow, deploy, communication, team
---

<aside class="tldr"><strong>TL;DR</strong>Engineering sits in Southeast Asia, the business in Europe, 6–7 hours apart. Our morning is Europe's night: deploys, migrations and risky work happen while nobody is using the system, and the tests have run before anyone in the warehouse has had coffee. The cost is the afternoon and the evening: Europe's working day starts when ours is half over, the urgent thing arrives at nine or ten at night, and they do not care what time it is for you — nor should they; that is the deal. The offset forced habits we would now keep at any distance: the ticket is the conversation, the release is boring, every session ends with a hand‑off note, and nothing important is said only out loud.</aside>

The people who write aiku are in Southeast Asia: a big city, and an island that other people fly to for their holidays. The people who use it — the warehouse teams, the sales floor, the people who answer customers — are mostly in Europe. Depending on the season that is six or seven hours of difference, and it has been that way since the first commit in 2022.

This is not a note about remote work in general. It is about what that specific number does to an engineering practice when the system is an ERP that a warehouse depends on, and why a surprising amount of what we are proud of exists because of it.

<figure><img src="/art/readme/draw-note-timezones.svg" alt="Hand-drawn sketch: a sun over a palm tree and a laptop on the left, a moon over a warehouse on the right; two day bands below, offset by seven hours, with the quiet morning marked as the deploy window and the evening marked as when the urgent thing arrives" width="1200" height="750" loading="eager"><figcaption>Our morning is their night. The quiet hours are when the risky work happens; the evening belongs to the business.</figcaption></figure>

## The morning is ours

At eight in our morning it is one or two in the morning in Europe. Nobody is picking, nobody is invoicing, the storefronts are at their quietest hour. That is when we do the things you would not want to do to a live warehouse: the migration that rewrites a large table, the reindex, the deploy that touches the queue workers, the data repair that has been waiting for a quiet window. By the time the first warehouse shift clocks in, the release has been live for hours, the tests have run, the monitoring is flat, and if something was going to fall over it already has — in front of us, not them.

That single fact — *we get a quiet production system for free every morning* — is worth more than any staging environment we could build. It is why [369 releases in five months](/blog/369-production-releases-in-five-months) was not reckless: most of them landed in Europe's night.

## The afternoon is theirs, and so is the evening

Now the part that the remote‑work posts leave out. Europe wakes at three or four in our afternoon; the questions, the tickets, the "this looks wrong" messages start when our day is already half spent. The warehouse's busiest hours are our evening. And the urgent thing — the one that actually matters, the server that is down, the invoice run that is wrong, the carrier label that will not print with a van waiting — arrives at nine or ten at night our time, which is the middle of their working afternoon.

Here is the thing you have to accept, and it is not negotiable: **they do not care what time it is where you are.** Not out of malice; it simply does not occur to a warehouse manager with a van at the door that it is ten at night for the person they are calling. Nor should it. The warehouse is the business. If you have chosen to be seven hours ahead of it, the evening call is part of the deal, and a team that resents it will not last a year. We have taken the call at dinner, on holiday, half‑asleep, with the fix typed into a phone. That is the price, and you either pay it or you do not do this.

In fairness, the offset only sharpens something that is true of running any live system: things break when it is least convenient, never during the quiet hour you set aside for them. The Friday‑night outage, the bulk update that went wrong on a bank holiday, the carrier API that changed its mind at lunchtime — none of those asked which time zone we were in. Being seven hours ahead did not create the 10pm call; it just guaranteed it. What it did do is make us permanently ready: the laptop is always in the bag, the phone can reach the servers, the runbook is a note not a memory, and nobody on the team is ever more than a few minutes from being able to act. A team that is ready at 10pm is ready at 3am too.

So the working day is not eight hours, it is two halves: a quiet, deep‑work morning, and a late stretch that belongs to the business, with a gap in between that is yours only until the phone rings. People adapt — some start late and finish late, some split the day — but nobody pretends the offset is free. It costs evenings, and it costs the assumption that your evening is your own.

## What it forced us to build

The offset means there is almost no overlap in which a question can be asked and answered in the same hour. Everything we do to compensate is really the same thing said different ways: **assume the other person is asleep.**

- **[The ticket is the conversation](/blog/the-ticket-is-the-conversation).** If a request, a decision or a diagnosis lives only in a chat or a call, it is lost by the time the other side wakes up. Every ticket is written so that someone who reads it cold, eight hours later, can act on it without asking anything. That is a discipline we resented and now would not give up.
- **The boring deploy.** A release that needs someone standing by to watch it cannot happen at a time when the watchers are asleep. So the [deploy is a task list](/blog/anatomy-of-a-deploy) that either completes or leaves the previous release serving, and nobody needs to be awake for it.
- **Hand‑off notes.** Every working session — human or, increasingly, an AI agent's — ends with a short note of what changed, what is pending, and what to look at first. The next person, in the next time zone, starts from that.
- **Tests that gate, not people.** When you cannot ask "did you try it", the test run has to answer instead. A [real database in CI](/blog/tests-that-touch-the-real-database) is the reviewer who is always awake.
- **The system explains itself.** The release number on the status bar, the telemetry you can query, the "why was this blocked" predicate on a delivery note — all of it exists because the person who could explain it is often not online when the question comes.

## What AI agents changed

Agents do not have time zones. A good part of our morning quiet is now spent with them — the survey, the migration draft, the test that proves the fix — and their output is reviewed and released before Europe is up. More usefully, the habits above turn out to be exactly what agents need too: a ticket written for someone asleep is a ticket an agent can act on; a hand‑off note for the next time zone is a hand‑off note for the next session. We did not design for agents. We designed for distance, and it was the same design.

## Would we recommend it

With conditions. It works because the system is built to be left alone, because communication is written rather than spoken, and because the engineers accepted, without a grudge, that the evening belongs to the business. If any of those is missing — if the deploy needs a babysitter, if decisions live in calls, if the people in Asia want their evenings back — the offset becomes a daily tax instead of a daily advantage, and the resentment will show up in the code.

What it gave us, beyond the quiet mornings, is that every habit on this list is one we would now keep even if we all sat in the same room. Distance was just the thing that made us build them.
