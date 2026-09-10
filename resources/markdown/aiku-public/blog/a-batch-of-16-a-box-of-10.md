---
title: A batch of 16, a box of 10, and the orders that hitchhike
summary: The factory counts in units, the shop counts in boxes, and no amount of wishing makes 16 divide by 10. Rather than force one side to surrender, we named the exchange rate, converted at exactly two edges, and taught the system to say "one sealed box and six loose" out loud. Then we let small orders wait at the roadside until something bigger came past going the same way.
date: 2026-09-09
tags: production, inventory, manufacturing, partners
---

<aside class="tldr"><strong>TL;DR</strong>Production works in units and is paid per unit; stock, sales and every partner order are counted in boxes. <code>packed_in</code> is the only bridge, and it belongs at exactly two edges: raising a job (boxes → units, rounded up to a whole batch) and receiving one (units → boxes). Both edges were missing or wrong. A batch of 16 on a box of 10 lands as <code>1 6/10</code> — one sealed box and six loose on the bench — which is not a rounding error, it is a true sentence about the shelf. <code>40</code> of our <code>1,923</code> batch-sized artefacts sit off the ladder, so we measure it instead of forbidding it. Partner orders are steered onto the smallest quantity whole batches fill exactly, and an order below that is allowed but goes hitchhiking: hidden from the factory board until other partners fill the batch or a paying customer triggers the job anyway.</aside>

## Two departments, two currencies

A maker fills a mould tray, and the tray holds sixteen. That is a batch. Their pay is per unit made, their targets are per unit, the recipe consumes ingredients per unit.

Everything downstream counts in boxes. Stock levels are boxes. Picking is boxes. A partner's shopping list is boxes. The number we call `packed_in` says how many units go in one — often ten.

Both departments are right, and they are irreconcilable in the only way that matters: sixteen does not divide by ten. This is not a bug anyone introduced. It is two teams doing their jobs correctly with different rulers, and it will still be true after every deploy we ever ship.

The mistake is not the mismatch. The mistake is letting the number cross the boundary without being converted, and that is exactly what we were doing — in both directions.

## Both edges were wrong

Demand arrives in boxes. A partner wants twelve. The code that turned that into a job for the maker did this:

```
quantity = ceil(demand)
```

Twelve boxes became a job for twelve **units** — a bit over one box, when the ask was twelve. The unit label was right there in the column name and the number crossing into it had been counted in boxes.

The return trip was the same error mirrored. When a finished job was received into stock, the units made were written straight into the stock movement, which is denominated in boxes. Sixteen bottles made would have landed as sixteen boxes on the shelf: a tenfold phantom.

We got lucky on that one. A query against production found zero stock movements of type `production` in the entire history — the receive path had never once run for real. The bug was real, complete, and had never yet been given the chance to lie to anybody.

The fix is boring, which is the point. One shared function, applied at the two edges and nowhere else:

- raising a job: `units = ceil(boxes × packed_in)`, then rounded **up** to the next whole batch
- receiving a job: `boxes = units ÷ packed_in`

In between, nothing converts. Recipes stay in units, because ingredients are consumed per unit. Pay stays in units, because a maker who fills a tray of sixteen should be paid for sixteen — if that counter ever moved to boxes, a ten-pack would pay a tenth.

## 1 6/10 is not a rounding error

So a batch of sixteen on a ten-pack arrives at the shelf as `1.6` boxes. The instinct is to call that corrupt data and round it away.

Don't. Walk to the bench and look: there is one sealed box, and six loose units sitting next to it. `1 6/10` is the most accurate sentence anyone can write about that shelf. Our stock display has always rendered part-boxes as fractions, so it was ready for this before we were. The six loose ones are not waste; they are stock, and the next batch tops them up to two.

The only honest thing to add was visibility. Where a batch is not a whole number of boxes we now show it — a column and a filter on the artefacts list, a line on the artefact page reading *16 is 1.6 boxes of 10 · whole boxes at 20*, and a counter on the factory dashboard.

What we deliberately did **not** do is paint it red. It sat briefly in a panel called "problems" and looked like an accusation, which it isn't: the factory sized that batch for the mixer, and the shop chose that pack for the shelf, and neither decision was wrong. It is now a plain blue tile counting the batches that *do* land whole, with the others as a quiet grey number beside it. If someone wants to fix one, the nearest batch that comes out even is printed right there. Nobody is forced.

The measurement, once taken, was smaller than the anxiety about it: `40` of `1,923` batch-sized artefacts, a hair over two percent, clustered almost entirely on three pack sizes. A morning's work for whoever cares to.

## Where the two ladders meet

For partners buying from us there is a nicer number available. The factory ladder is 16, 32, 48… units. The customer ladder is 10, 20, 30… units. They meet at eighty — five batches, eight boxes, nothing left over. That is the lowest common multiple divided by the pack size, and it is the smallest order that whole batches fill exactly.

So every suggestion we generate for a partner is raised onto that ladder, deterministic pass and AI pass alike, and clamped back down to it when budget or available stock caps it. The easy path is the default, and the number is explained on the card rather than imposed: *made in batches of 16 units · full batches every 8 boxes*.

They can still order one box. Of course they can — it is their business, not ours. But the line now says what will happen: **a full batch gets made either way, so this order may be delayed or the quantity adjusted.** Consequence stated before the commitment, which is the only form of "no" worth writing.

## Orders that hitchhike

That leaves the small order under the ladder. It shouldn't clutter the factory board — the maker cannot act on it — but it must not vanish either.

We call it **hitchhiking**. The line stands at the roadside waiting for a vehicle going its way, and there are three ways it gets picked up:

1. **Somebody takes pity.** The planner flips the filter, sees them, and makes a job with one for the partner and nine for stock.
2. **Other partners turn up.** Open demand for the same item is summed across every partner list on every page load. The moment the total reaches the ladder, all of those lines walk back into the backlog by themselves.
3. **A paying customer arrives.** One customer order against that item — even a single box against a batch of a thousand — pulls the hitchhikers back onto the board, because a job is going to run regardless. Someone has already paid; the small order gets a free ride.

Nothing is stored, nothing is swept, no nightly job promotes anybody. All three conditions are recomputed live in the same query that draws the board, which means the state is never stale and there is no reconciliation task to forget about. The board simply shows *"3 lines too small for a batch are waiting for company · show"*, and the toggle lives in the URL so it survives a reload.

A line can hitchhike forever. That is allowed. It is a truer description of that order's situation than any status we could invent for it.

## What we would keep

When two parts of a business count in different units, the losing move is to make one of them surrender. The mixer is not going to hold ten, and the shop is not going to sell in sixteens.

Name the exchange rate, convert at the smallest number of edges you can get away with — two, here — and then let the awkward remainder be visible and truthful rather than rounded into a lie. Most of the ugliness in inventory software is somebody, years ago, quietly picking a winner between two correct answers.

<aside class="tldr bottom"><strong>In one paragraph</strong>Production counts units, the shop counts boxes, and <code>packed_in</code> is the exchange rate — apply it when raising a job and when receiving one, nowhere else, and let stock hold <code>1 6/10</code> of a box because that is genuinely what is on the bench. Measure the batches that don't land whole instead of forbidding them, steer partners onto the quantity where the two ladders meet, and let orders too small for a batch hitchhike until other partners or a paying customer come past going the same way.</aside>
