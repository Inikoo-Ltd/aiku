---
title: Reading the PO journey board
summary: For buyers and management — how every open purchase order becomes one ribbon, what each colour and date means, where the targets come from, how to mark the stages nobody else records, and how to find the orders that need you.
date: 2026-09-25
tags: procurement, supply-chain, agents
category: procurement
help_routes: grp.supply-chain.dashboard
series: PO journey
order: 1
---

<aside class="tldr">
For buyers, purchasing managers and directors. The first page of Supply Chain shows every open purchase order as one ribbon, left to right, from the day it was created to the day its products are on sale. Green is done, blue is where the order is now, red is the stage that is late, with the days it is late. Tick <b>Problems only</b> to see just the orders that need someone. Agents record their own side of the work as described in <a href="/docs/recording-order-progress-for-agents">recording order progress as an agent</a>.
</aside>

<figure><img src="/art/docs/draw-po-journey.svg" alt="Watercolor sketch of three purchase order ribbons: the first all green to the end, the second green then blue at In transit, the third green then red at Production with plus twelve days, and grey planned dates after it" width="1200" height="750" loading="lazy"><figcaption>One order, one ribbon. The red cell is where it is stuck.</figcaption></figure>

## What is on the board

Every order that is still on its way is on the board:

- **Orders to direct suppliers.**
- **Orders between AW companies**, for example one company buying from the factory.
- **Orders through agents.** These are shown as the agent's order to each supplier, because that is where production, quality checks and delays really happen. Switch **Orders to suppliers** to **Agent POs** at the top left to see each agent order as one row instead. An agent order the agent has not yet split by supplier shows as one row with a small *not split* tag.

An order leaves the board once its goods are placed in the warehouse and every product from it is on sale. Orders finished in the last 60 days stay visible as **Completed**, so you can see what came in. Orders more than a year old that never finished are set aside and do not show.

## The stages

| Stage | Done when |
|---|---|
| PO created | The order is sent to the supplier. A draft is not sent. |
| Spec / Sample | The sample is approved. New products only. |
| Deposit paid | The supplier's deposit is paid. |
| Production | The goods are made. |
| QC | The goods pass the quality check. |
| Clean handover | The goods are handed over complete, checked and with their paperwork. |
| Dispatched | The goods leave the supplier. |
| In transit | The goods arrive at our warehouse. |
| Warehouse received | The goods are checked and placed in their locations. |
| Products online | Every product from the order is created and on sale on the website. |

Not every order goes through every stage. Orders between AW companies go straight from created to dispatched. Reorders skip spec and sample. A column an order does not use shows as a thin line.

## Reading the colours

- **Green with a date:** done on that day.
- **Pale green tick:** done, but nobody recorded the date. A later stage happened, so this one must have.
- **Blue:** the stage the order is on now, and it is on time. The date is its target.
- **Amber:** the current stage is due within three days.
- **Red with +N days:** the current stage is late by that many days. There is only ever one red cell per order: the stage it is stuck at.
- **Grey date:** the plan for a later stage. **Struck through** means that plan date has already passed while the order is stuck earlier, so the order will be late there too.
- **Blank:** a stage nobody has recorded on this order yet. See below.

The status column on the right says the same thing in words: *On track* with the expected finish date, or *Overdue* with what the order is waiting for, for example *Waiting for dispatch* or *Not sent yet*.

## Where the targets come from

Each stage gets a number of days after the stage before it actually finished. So if production finished two weeks late, QC is measured from the day production really finished, and the red lands on the stage that slipped, not on everything after it.

The days come from, in this order:

1. **Dates agreed on the order.** An estimated production date, an arrival date on the order or on its shipment, or the agent's approved ready date. When an arrival date is known, dispatch is due the transit time before it. When a ready date is agreed, QC is due that day and the clean handover within seven days of it, as in the agent agreement.
2. **The agent's own stage days**, if set on the agent's page.
3. **The agent's or supplier's delivery time**, spread across the stages. Each agent's delivery time is set to how fast 80% of their past orders arrived, so red means slower than usual for that agent.

## The stages nobody else records

The system sees for itself when an order is sent, when goods leave, arrive and are placed, and when products go online. It cannot see when a sample is approved, a deposit paid, production finished, goods checked or handed over. Somebody has to mark those.

- **Agents** mark deposit paid, sample approved and production done on their own orders.
- **Buyers** confirm QC and the clean handover. Agents cannot set those two.

Until any of these is marked on an order, its cells stay blank and the order is followed on dispatch and arrival only. Once one is marked, the order waits at the next unmarked stage, so start from the first one and keep going.

To mark a stage, click its cell, check the date and click **Mark done**. **Clear** removes a date entered by mistake. Every change is recorded with who made it.

## Filters and numbers

Along the top: **AW company**, **Journey** (agent, direct supplier, between companies), **PO type** (*NPO* is an order with at least one product we have never received before; everything else is a reorder) and **Status**. Below them: **Agent**, **PO creator / buyer**, **Supplier**, **Country** and **Current stage**, plus **Problems only** and a search box for the PO, supplier, agent or buyer.

The cards count the orders in the current filter: open orders, their value in pounds at today's exchange rate, on track, at risk, overdue, and completed in the last 60 days. Click a card to filter by it. **Urgent blockages** on the right groups the late orders by what they are waiting for; click one to see them.

<aside class="wayfinder">
<b>Where to click in aiku</b><br>
<b>Supply Chain</b> in the left menu opens the board. <b>PO journey</b> in the top menu brings you back to it; <b>Overview</b> next to it has the supply chain cards and shopping lists. Click a cell to mark a stage; click the order reference to open the order. Stage days per agent: <b>Supply Chain → Agents →</b> the agent <b>→ Edit</b>, section <b>PO journey</b>.
</aside>

<aside class="wayfinder">
<b>Permissions you need</b><br>
Supply chain view permission to see the board. Supply chain edit permission, or purchasing edit permission for the company that raised the order, to mark stages. QC and clean handover on agents' orders are marked by AW staff only.
</aside>
