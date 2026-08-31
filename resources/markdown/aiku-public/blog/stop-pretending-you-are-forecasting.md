---
title: Stop pretending you can forecast. You're lucky if your data can predict the past.
summary: Data can only predict the past. Our stock forecaster leans into that - measure the past honestly (including the days the shelf was empty), then say how wrong you might be.
date: 2026-08-31
tags: inventory, forecasting, procurement, hydrators
---

<aside class="tldr"><strong>TL;DR</strong>No forecaster predicts the future; it predicts the past continuing. Ours makes that bet as honest as possible: every SKU in every warehouse carries two live numbers — the day it will run out and how much to reorder now — computed from demand measured only over days the item was actually on the shelf, with Croston or Holt chosen by the shape of the demand, last year's calendar as the seasonality, sister organisations filling the silence, and a pessimistic P90 date sitting next to the expected one to say how wrong the bet might be. Stored by a hydrator that fires whenever stock moves — no nightly batch, no on-page computation.</aside>

## Data predicts the past

Every forecast ever issued is the same sentence: *assume tomorrow behaves like the evidence.* That's not prophecy, it's extrapolation — and pretending otherwise is how companies end up trusting a number precisely when it's about to betray them. So before building a stock forecaster, we wrote its epistemology down: data can predict the past. From there, the job splits in two — measure that past **honestly**, and state **how wrong** the extrapolation might be. Everything below is one of those two jobs.

## The lie in the average

Ask a database how fast something sells and it will happily divide: units shipped over ninety days, divided by ninety. For a well-stocked item that's fine. For the item that spent eighty of those ninety days out of stock, it's a lie — it "sold" almost nothing because there was nothing to sell. The naive average then tells you demand is low, so you reorder a little, run out again, and the item spends another quarter looking unpopular. Stockouts launder bestsellers into slow movers.

The fix is to stop counting days that couldn't sell. Our stock movements ledger stores a running balance with every movement, so the forecaster rebuilds, day by day over the last quarter, whether the shelf had anything on it — and computes the selling rate over **in-stock days only**. An item that shifted 40 units in the 9 days it existed on the shelf is treated as what it is: a 4.4-a-day product you keep failing to buy enough of.

*measure the pallet, not the mood.*

## One rate, four models, in order of desperation

Demand curves come in shapes, and one estimator fits none of them well. The forecaster picks:

- **Steady movers** (sales on most in-stock days) get Holt's damped-trend exponential smoothing over weekly rates — it follows growth or decline but refuses to extrapolate a trend to infinity, which is how you avoid forecasting negative stock in July.
- **Intermittent movers** (the classic three-sales-a-month SKU) get Croston's method with the Syntetos–Boylan correction: smooth the size of a sale and the gap between sales separately, then correct the known bias. This is the standard answer for spare-parts-shaped demand, and most of a wholesaler's tail looks exactly like that.
- **No local history at all?** Borrow. First the item's own quarterly time series; then the *same stock* sold by sister organisations, damped to half — their market rhymes with ours, it isn't ours; finally the average of its product family here, damped harder. A new SKU starts life with an informed guess instead of a shrug.

Whatever produced the rate, it is then scaled by a **seasonality factor**: what the coming quarter did last year relative to a normal quarter, clamped so one weird Christmas can't triple a forecast. If the item's own history is too short to know its seasons, the sister organisations' combined history answers instead.

## Two numbers, not a dashboard

The output is deliberately small, per SKU:

**Days of cover** — stock on hand divided by the adjusted rate, plus the date that lands on. And because averages are polite fictions, a pessimistic twin: solving *qty = d·μ + 1.28·σ·√d* gives the day the 90th-percentile demand path empties the shelf. If the two dates are far apart, the item is volatile and the earlier one is the one to respect.

**Recommended order quantity** — cover the supplier lead time plus one review period at the forecast rate, add a safety buffer sized by the demand's measured variability, subtract what's on the shelf and already on order, round up to the supplier's pack size. Deterministic and explainable line by line; the AI layer that sits above it (budget-constrained auto-fill with plain-language instructions) consumes these numbers rather than replacing them.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Censoring: last <code>running_quantity_org_stock</code> per day from <code>org_stock_movements</code>, carried forward over silent days; a day counts only if the balance was positive.</li>
<li>Model gate: non-zero share of in-stock days &lt; 0.3 → Croston-SBA (α=0.15, bias factor 1−α/2); otherwise Holt damped (α=0.35, β=0.15, φ=0.9) on weekly in-stock rates.</li>
<li>Seasonality: same-quarter-last-year ÷ trailing four-quarter average from the quarterly time series, clamped to [0.6, 1.8]; falls back to the stock's series summed across all organisations.</li>
<li>Everything lands in <code>org_stock_stats</code> (<code>days_of_cover</code>, <code>predicted_out_of_stock_at</code>, <code>days_of_cover_pessimistic</code>, <code>demand_variability</code>, <code>recommended_order_quantity</code>, <code>forecast_source</code>) via a queued hydrator, unique per SKU, dispatched whenever <code>quantity_available</code> changes — pages read a column, never compute.</li>
</ul>
</aside>

## And from there, we hedge

Every number here is a bet that next quarter rhymes with the last one, and every design choice is a hedge for when it doesn't: the damped trend that refuses to believe growth is exponential, the seasonality clamp that won't let one strange Christmas write next year's plan, the pessimistic P90 date sitting next to the expected one precisely because the expected one will sometimes be wrong. The honest claim is smaller and more useful than prophecy: *if tomorrow behaves like the evidence, this shelf is empty on the 14th — and if tomorrow misbehaves in the usual ways, the 8th.* That is not the future foretold; it is uncertainty measured, which is the only kind of fortune-telling a warehouse can bank on.

<aside class="tldr bottom"><strong>In one paragraph</strong>A forecast is only as honest as its denominator. Count the days the shelf was actually stocked, choose the estimator by the shape of the demand, let last year's calendar and your sister companies fill the silence, and write the answer where a page can read it in one query.</aside>
