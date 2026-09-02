---
title: Scoring agent clean handovers
summary: For procurement teams — why the Clean Handover Score exists, which five facts you must record on every agent purchase order, who should own each one, and how to read the hygiene strip that tells you when the score is being managed instead of earned.
date: 2026-09-01
tags: procurement, agents, supply-chain
category: procurement
help_routes: grp.supply-chain.agents
series: Clean Handover Score
order: 2
---

<aside class="tldr">
For anyone who runs purchase orders through a coordination agent. The Clean Handover Score turns "how is our agent doing?" from an opinion into a number you can pay commission against — but only if your team records five facts per purchase order, at the moment they happen. This page covers the recording discipline, who should own each field, and the anti-gaming instruments. The agent-facing explanation of the same number is <a href="/docs/your-clean-handover-score">your Clean Handover Score</a> — worth reading too, because your agents will.
</aside>

## Why score handovers, and not effort

An agent's work is mostly invisible to you: factory visits, phone calls, chasing, checking. You cannot audit effort from another country, and you shouldn't try. What you can verify is the outcome the effort exists for — **goods reaching the shipping agent on time, complete, quality-checked, and with complete paperwork**. That event is the clean handover, and the score is simply the value-weighted share of scheduled orders that achieved it in a quarter.

Scoring the handover instead of the activity has a second benefit: it makes the commission conversation short. Agreements typically tie the commission rate to quarterly bands (for example 3% at 80%+, 2.5% at 70–79%, base 2% below). When the quarter closes, the number is already on the screen — the same screen the agent has been watching all quarter, so the statement is a formality, not a negotiation.

## The five facts you must record

The score is computed; it is never typed in. But it is computed from facts your team records on each agent purchase order, and it is exactly as honest as that recording. Per PO:

1. **Proposed ready date** — the date the agent says the goods, QC evidence and compliance pack will be ready for handover. Record what they actually proposed, not what you wish they had.
2. **Approved ready date** — the date you approved in writing. This locks the PO's quarter and starts the seven-day handover window. Interrogate it before approving: this is the single moment where scrutiny is cheap and its absence is expensive.
3. **Handed over** — when the goods physically reached the shipping agent, confirmed from your side or the shipping agent's, never taken from the coordination agent's word alone.
4. **QC passed** — when the goods passed the agreed inspection.
5. **Compliance pack complete** — when the documents were verified complete. At handover, not after.

Record each the day it happens. A quarter reconstructed from memory in week thirteen produces a score no one should pay against — in either direction.

**Quantity** — the fourth clean-handover test — you do not record by hand: it follows from what your warehouse actually receives against the order.

## Who should own each field

Here is the frank part. Every one of those fields is a lever on the agent's income, and three parties could plausibly hold each lever: the agent, your procurement staff, or a hard system record. The design principle is simple:

> **The agent may propose; only you approve; and where a fact can come from the physical flow of goods, take it from there instead of from anyone's typing.**

Concretely:

- **Proposed ready date** — the agent's field, eventually even in their own login. Proposing dates is their expertise, and it is harmless: the score runs off the approved date.
- **Approved ready date, exclusions** — yours, always, no exceptions. These two are where score-gaming lives.
- **Handover, QC, compliance** — yours today; over time, the more these derive from warehouse receipts and document checklists rather than a staff member's data entry, the stronger the score gets.
- **The score itself** — nobody's. It is derived; there is no field to edit.

An agent must never score themselves — not because agents are dishonest, but because a self-scored number is worthless even when it is true. If your agents currently email you their own performance summaries, this system exists to replace exactly that.

## The hygiene strip: watching the watchable

A determined agent cannot edit the score, but can still try to manage its inputs socially — talking your staff into generous approvals. The score panel on each agent's page carries a **hygiene strip** for precisely this, and it is for your eyes, not the agent's (their dashboard shows the score without it):

- **Avg ready-date shift on approval** — the average gap between what the agent proposed and what was approved. Near zero with a strong CHS is the healthy picture. Near zero with weak delivery dates means proposals are being rubber-stamped: the padding, if any, is getting through. A consistently large gap means proposals are routinely unrealistic — a conversation to have with the agent.
- **Excluded from score** — the share of scheduled value granted exclusions. Exclusions are legitimate (your own late changes, documented force majeure) but they are the score's escape valve, and this is the gauge on it. It runs red above 10%. A rising exclusion rate with a stable score means the score is being *maintained* rather than earned.
- **Handed over without QC or compliance** — orders marked handed over with either check missing. This should be zero; each one is a hole in your own recording discipline before it is anything else, because an unchecked handover can never count as clean.

Every one of these fields is audited: who set it, when, from what to what. A padding pattern, a rubber-stamping pattern, or a quarter-end flurry of exclusions is all reconstructable after the fact.

## Exclusion discipline

Exclusions remove an order from both sides of the calculation. Grant them for what your agreement says — your own caused delays, your approved scope changes, evidenced force majeure — and refuse them for ordinary production trouble, supplier excuses and communication failures, which are the job itself. Two habits keep the valve honest:

- **Require the reason in writing, at the time.** The exclusion field carries a mandatory reason; "supplier was slow" should never survive review.
- **Grant early or not at all.** An exclusion requested when the problem emerges is probably genuine. A batch requested at quarter close, after the score is visible, is score management — the hygiene strip will show it, but better to refuse it.

## What the number does not tell you

The CHS measures handover execution, deliberately narrowly. It says nothing about price improvement, supplier development, communication quality or new-product work — the wider scorecard belongs in your periodic reviews, not in the commission formula. Resist the urge to fold everything into one number: the CHS works *because* every input is a verifiable event.

And one operational caveat: the score only exists for orders with an approved ready date. Until your team records dates on live orders, the panel will honestly report nothing. The measurement starts when the discipline does.

<aside class="wayfinder">
<b>Where to click in aiku</b><br>
Group → <b>Supply Chain → Agents</b> → pick the agent. The showcase shows the <b>Clean Handover Score</b> panel with the hygiene strip. To record the five facts: the agent's <b>Purchase Orders</b> list → open a PO → <b>Edit</b> → the <b>Clean handover</b> section (proposed/approved ready dates, handed over, QC passed, compliance complete, exclusion toggle + reason). The agent sees their score, without the hygiene strip, on their own dashboard at login.
</aside>

<aside class="wayfinder">
<b>Permissions you need</b><br>
<b>Supply-chain edit</b> (group level) or <b>procurement edit</b> in the relevant organisation to record the fields. Viewing the agent showcase needs supply-chain view.
</aside>
