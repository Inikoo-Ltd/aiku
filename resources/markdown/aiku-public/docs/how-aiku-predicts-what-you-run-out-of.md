---
title: How aiku predicts what you run out of
summary: What "we run out in ~12 days" and the suggested quantity actually mean, why a bestseller that is out of stock asks for so much, and when to trust the number over your own judgement.
date: 2026-09-01
tags: procurement, stock, intercompany, shopping-list
category: procurement
---

<aside class="tldr">
For anyone who buys stock. Two numbers follow every SKO you carry around the buying screens: <b>we run out in ~N days</b> and a <b>suggested</b> quantity. This page explains where they come from, so you know when to accept them and when to overrule them. If you just want to get an order out, the <a href="/docs/reading-the-partner-shopping-dashboard">shopping dashboard</a> and <a href="/docs/buying-from-a-partner">buyer's guide</a> are the practical guides — come back here when a number looks wrong.
</aside>

## The two numbers

Wherever you are buying — a partner's **Browse** cards, the **Shopping list**, a supplier or agent dashboard, an Auto-fill proposal — the same pair follows the item.

**We run out in ~N days** is what you have on hand divided by how fast aiku thinks it is leaving. It goes red at two weeks or less, amber up to a month. "We run out now" means the shelf is already empty.

**Suggested** is the quantity that would carry you to the next order and a bit beyond: enough for the supplier's lead time, plus the gap until you would normally order again, plus a cushion sized to how erratic the item is — then minus what is on the shelf and what is already on its way. It is rounded to whole shipping units, because that is what you can actually buy.

Both numbers refresh themselves whenever the stock moves, so they are current when you look at them, not last night's.

## The idea that makes it work: empty days don't count

The obvious way to measure how fast something sells is to average its sales over the last three months. That method quietly ruins a stockroom.

Take an item that sold out in week one and sat empty for the rest of the quarter. Averaged over ninety days it looks like it barely moves — so it is never reordered, so it stays empty, so next quarter it looks even worse. The better it sells, the faster it disappears, the more invisible it becomes. Most stockrooms have a handful of these, and they are usually items people are asking for.

So aiku doesn't average over the calendar. It reconstructs, day by day, whether the item was actually available, and measures the sales rate **only over the days you had it to sell**. Days when the shelf was empty are treated as days with no information — not as days with no demand.

That single rule is why a bestseller sitting at zero shows a large suggested order rather than a small one. It is not a bug and it is not the system panicking. It is the system finally seeing the demand that the empty weeks were hiding.

## Where the number comes from, and how much to trust it

Not every item has the same quality of evidence behind it, and it helps to know which case you are looking at.

- **Its own recent history.** The normal case, and the one to trust. Steady sellers get a trend-following estimate; slow, lumpy items — the ones that go out three at a time every few weeks — are measured differently, on how big the occasional order is and how long the quiet gaps run, which is the honest way to describe them.
- **Its own longer history.** Not enough recent movement, but the item has a past. Reasonable, a little slower to react.
- **The same item in a sister organisation.** You have never really sold it; somewhere else in the group has. aiku borrows their rate and halves it, because a different market is a hint, not a measurement. Treat it as a starting point.
- **The family it belongs to.** The weakest case: a brand new line with no history anywhere, estimated from its neighbours and heavily discounted. This is a placeholder for your judgement, not a substitute for it.

There is also a seasonal adjustment: aiku compares the same quarter last year against that year's average and nudges the rate up or down, within limits, so a Christmas item is not bought on its August rate. The limits matter — one freak quarter can't run away with the number.

The empty-days rule applies here too, and it has to. Last Christmas is only evidence about Christmas if you had the item to sell; a quarter you spent mostly out of stock says nothing about the season, only about the supply. So each quarter is measured per day the item was actually available, and any quarter you were empty for more than half of is dropped from the comparison entirely. If that leaves fewer than four usable quarters, aiku makes no seasonal adjustment at all rather than a confident one built on four thin ones.

## Why a number can look wrong (and often is)

The forecast reads history. Anything that happened outside the history, it cannot know.

- **A one-off bulk order.** One customer clearing you out looks exactly like sudden popularity. Overrule it.
- **A line you are discontinuing.** History says it sells; your plan says stop. The system does not know your plan.
- **A promotion, a catalogue photo, a marketplace listing going live.** Demand about to change for a reason that has not happened yet.
- **A brand new product.** See the family case above — that number is a guess wearing a confident face.
- **Something that hasn't moved at all but is worth money.** It lands in **Dead stock** on the dashboard, and it wants a decision from a person, not a reorder.

The rule of thumb: the forecast is better than you at the boring middle of the catalogue — hundreds of ordinary items nobody has time to think about — and worse than you at anything with a story attached. Let it handle the volume, and spend your attention on the exceptions.

## Reading it in an Auto-fill proposal

Auto-fill ranks candidates by how soon you run out and tops up the most urgent first until the budget is gone. Every proposed line carries its reason in plain words — *"Our sales/quarter ~48 · our stock 0 · we run out now"* — which is the forecast showing its working. Read the reasons before you confirm; that is where a wrong number is easiest to catch, and unticking a line takes one click. Nothing is ordered until you press **Add items to shopping list**.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See the numbers per item:</b> <b>Procurement → Partners</b> (or <b>Suppliers</b>, or <b>Agents</b>) → open one → <b>Browse</b>: each card shows <i>our stock</i>, <i>our sales / quarter</i>, <i>we run out in</i> and a dashed <b>suggested</b> chip that fills the quantity box.</li>
<li><b>See them across the catalogue:</b> the same partner's <b>Shopping</b> dashboard → the stock-at-risk tiles are built from the run-out day; click a tile's number for the items behind it.</li>
<li><b>See them on an open order:</b> <b>Shopping list</b> → the <b>Info</b> column carries the stock story for each line.</li>
<li><b>Overrule one:</b> type your own quantity in the stepper on the <b>Browse</b> card — it edits the open line directly. Nothing re-suggests over the top of you.</li>
<li><b>Fix the lead time behind a suggestion:</b> the SKO's settings, or the supplier product's settings, while it still says <i>estimate</i>.</li>
</ul>
</aside>
