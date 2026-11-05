---
title: Recording order progress as an agent
summary: For agents — the dates you record on each order to a supplier (deposit paid, production started, production done, proposed ready date, handed over) and your own order number, when and where to record them, what the buying company records instead, and why a missing date shows as a delay.
date: 2026-11-05
tags: procurement, agents, supply-chain
category: procurement
help_routes: grp.org.procurement.purchase_orders
series: PO journey
order: 2
---

<aside class="tldr">
For coordination agents. The buying company follows every order through eight stages: PO Launch, Deposit & Go, Making It Happen, Ready to Roll, Clean Hand Over, Homeward Bound, Counted & Costed and Mission Complete. Stages 1 to 5 are your part of the work. It can see by itself when goods are dispatched and when they arrive, but it cannot see what happens at the supplier. That part comes from you: on each order to a supplier you record when the deposit was paid, when production started, when production was done, the date you expect the goods ready, and the day you hand the goods over to the shipping agent. Record each one on the day it happens. The buyer's side is explained in <a href="/docs/reading-the-po-journey-board">reading the PO journey board</a>.
</aside>

## Why your dates matter

Every order the buying company places through you shows its stages at the top of the order page, and again as a row on the PO journey board. Each stage shows its date and who recorded it. When a stage passes its target, it turns red on the board and shows how many days late it is.

Without your dates the buying company only sees two things at your end: the day the order was sent to you and the day the goods left. Everything in between is invisible, and a delay looks the same whether it happened at the supplier, at quality check or at handover. With your dates, the stages show exactly where an order is, and a late supplier is visible weeks before the goods were due to ship.

Once you record the first date on an order, the next ones are expected. If a stage's target passes and you have not recorded it, that stage shows as late, even if the work was done. A missing date looks exactly like a delay.

## The dates you record

| Date | Record it when | Where |
|---|---|---|
| Deposit paid | The supplier has the deposit. | **Pay deposit** in the order's payments panel, with the amount and the bank statement. The first payment recorded is the deposit; what is paid after it is the balance. |
| Production started | The supplier starts making the goods. | The **Production started** button on the stages at the top of the order. |
| Production done | The goods are finished at the supplier. Finished means all of the order, not the first batch. | The **Production done** button on the stages at the top of the order. |
| Proposed ready date | You know when the goods will be ready for handover. A date you believe. The buyer approves it. | The **Clean handover** section of the order's **Edit** page. |
| Handed over | The goods reach the shipping agent. All of the order, not the first part of it. This completes stage 5, Clean Hand Over. | The **Handed over** button on the stages at the top of the order. Only if you hand goods to a shipping agent: see below. |
| Your order number | You have your own number for the order. | **Agent's order number** on the order's **Edit** page. It shows under PO submitted. |

## Clean Hand Over, stage 5

Clean Hand Over is the moment your work on an order is done. What it means is set for each agent:

- **Ready for the next container** (China, Indonesia). The stage is done by itself once every item of the order is on a shipment. There is no **Handed over** button: you record the goods with goods receipts, and they ship when their container is dispatched.
- **Sent to the shipping agent** (India, Nepal). You record **Handed over** on the day the goods reach the shipping agent.

A dispatched shipment always completes the stage. The buying company may also give a stage its own name for you, for example "Sent to forwarder": it shows on the order with that name.

Stages go in order. Record each date on the day it happens. A date more than a day in the past, a stage recorded before the one before it, or a date you clear asks you for a reason. The reason is kept in the order's history, with your name, so the buyer can see why.

## What the buying company records

Some dates you cannot enter, by design:

- **Sample approved.** The buyer approves the sample. New products only; reorders have no sample.
- **Approved ready date.** The buyer approves your proposed date. From then on it is locked, and it sets the targets: quality check is due that day, and the clean handover within seven days of it.
- **QC passed.** The buyer confirms it, usually from your quality evidence. Approving the QC releases the balance payment: until then, paying the balance asks for a reason.
- **Compliance pack complete.**

Your <a href="/docs/your-clean-handover-score">Clean Handover Score</a> is calculated from these dates and from the handover date you record. The handover date is yours to record, so record the day the goods actually reached the shipping agent. The buyer sees who recorded it and when, can check it against the shipping agent's receipt, and can correct it; a correction is kept in the order's history with the reason.

## Your unit expense on each line

While an order is submitted or confirmed, you can type your own **Your unit expense** on each line, in the **Items** tab of the order. It is per unit and in the currency of the order. The expense of a line is your unit expense times the quantity ordered, and **Agent expenses** on the order is the sum of its lines.

- A line where you type nothing uses the buying company's estimate for that product.
- Empty the field to go back to that estimate.
- A new line for the same item on your next order, in the same currency, starts with the value you used last time, so you only edit what changed.
- The buying company sees your value on the line, but cannot change it.

It is an estimate for budgeting only. It is not paid to the supplier, not included in the order total and not used to cost the stock.

## When the supplier changes a price

If a supplier asks for a new price before you confirm an order, do not confirm at the old price. Open the order, find the product in **Items** and click **Supplier changed the price** on its line. Enter the price the supplier asks, per carton as shown, and a short note if it helps, then **Ask the organisation**.

The buying company is notified and answers on the line: it accepts the new price, keeps the old one, or sets another price. You are notified of the answer. Until every price question on the order is answered, **Confirm** stays grey. If the buying company keeps the old price and the supplier will not sell at it, cancel that line before confirming.

## When the supplier changes the quantity

If the supplier cannot supply the quantity ordered, or sells only in larger lots, ask the buying company before you confirm. Open the order, find the product in **Items** and click **Ask for another quantity** under its quantity. Enter the quantity, in the level the Items tab shows (cartons, SKOs or units), and a short note if it helps, then **Ask the organisation**.

The buying company is notified and answers on the line: it accepts the new quantity or keeps the old one. You are notified of the answer. Until every quantity question on the order is answered, the order cannot be confirmed.

## When the supplier cannot make a product

If a supplier cannot make a product on the order, give it to another of your suppliers instead of cancelling it. Open the order, find the product in **Items** and click **Move** on its line. Choose the supplier and how many units to move: move them all, or move part and the rest stays with this supplier.

If that supplier does not sell this SKO yet, the dialog shows the code, name, unit cost and units per carton of its new product, filled in from the current one. Check them, correct the cost, then **Move**: the product is created for the same SKO.

The units go onto that supplier's order in the same state, or onto a new order if it has none, at the price the buying company agreed. If the new supplier's cost is different, the buying company gets the price question described above and answers it on the new order. The buying company is told about the move, and both lines show where the units came from or went.

You can move a line only while you could still cancel it: before production starts and before it is on a stock delivery. If nothing is left on the original order, it is cancelled, unless a deposit was paid on it: then it stays open so the deposit can be recovered.

## Good habits

- **Record on the day.** Five seconds on the day saves an argument about a red stage later, and a reason to type.
- **Propose ready dates you believe.** A realistic date you hit is better than an optimistic date you miss.
- **Tell the buyer early** when a date will slip. A new approved date agreed in time keeps the order on track; a slipped date nobody agreed shows as late.

<aside class="wayfinder">
<b>Where to click in aiku</b><br>
Log in → <b>Purchase Orders</b> → open the order. Each purchase order is to one of your suppliers. The stages are at the top of the page: use the <b>Production started</b>, <b>Production done</b> and, if you hand goods to a shipping agent, <b>Handed over</b> buttons there. The deposit is recorded with <b>Pay deposit</b> in the payments panel. Then open <b>Edit</b>: <b>Proposed ready date</b> and <b>Agent's order number</b> are in the <b>Clean handover</b> section.
</aside>

<aside class="wayfinder">
<b>Permissions you need</b><br>
A login for your agent organisation. You can mark only your own stages, on your own orders: production started, production done and handed over on the stages, the deposit as a payment, and your proposed ready date and order number on the edit page. Sample approval, the approved ready date, QC and compliance are not open to you, because the buying company records them.
</aside>
