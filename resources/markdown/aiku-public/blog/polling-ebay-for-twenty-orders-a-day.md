---
title: Polling eBay for twenty orders a day
summary: We asked eBay for new orders forty thousand times a day to find about twenty. A customer still waited eighty minutes for one. Why checking more often was the wrong fix, what we did instead (check the channels that sell, and check the moment a customer comes looking), and the column we added so we can tell whether it worked. Results pending.
date: 2026-09-26
tags: production, dropshipping, ebay, queues, scheduling
---

<aside class="tldr"><strong>TL;DR</strong>eBay orders reach aiku by polling. We checked 2,637 channels every hour by day and every four hours by night, with each check spread over a random hour. That came to about 40,000 API calls a day, 7 hours of worker time and 29,000 debug rows, all to find about 20 orders a day. The median wait was 30 minutes by day and 2 hours by night. Instead of checking everyone more often, we now check the ~260 channels that actually sell every 15 minutes, check a customer's channels as soon as they open our website, and log each order once rather than on every check. We also started storing when each order was placed on the platform, so the wait is a number we can read and not a guess. The results section is empty until there are two weeks of data.</aside>

## The complaint

A customer wrote to us early one morning: an order had come in on their eBay store, and it was not in aiku, so they could not send it. Customer service opened a ticket saying the product was missing from their portfolio.

It wasn't. The product was in their eBay portfolio and their TikTok portfolio, active and in stock. The order came into aiku about eighty minutes after they wrote and was dispatched before lunch. Nothing was broken. We had simply not asked eBay yet.

That is not a bug, but it isn't fine either. The customer got to the order before we did, and that is why they wrote.

## How eBay orders get here

Shopify tells us about an order; eBay, the way we are wired to it, does not. A scheduled job goes through every open eBay channel and queues a job per channel that asks eBay for its unfulfilled orders. Any order we have not seen before, for a listing in the customer's portfolio, becomes an aiku order.

The schedule was hourly from 06:00 to 17:00 UTC and every four hours outside that. Each channel's job was also delayed by a random 1–3,600 seconds, so 2,600 jobs would not hit the queue and eBay's API all in the same second.

That random delay stacks on top of the schedule. By day, an order waits for the next hourly run, then for its channel's random slot in the hour after it: up to two hours, not one. By night it is up to five.

## What it cost, and what it found

NightOwl had the numbers:

- **2,637** channels checked on every run. **168** of them had an order in the last 30 days.
- About **2,600** checks an hour by day, around **40,000** a day, each taking about **0.8 s**: roughly seven hours of worker time a day.
- About **21** eBay orders a day across all of them.
- Every check also saved a copy of every open order to a debug table, not just new ones. An order waiting to be sent was saved again every hour until it went out. That table was taking **29,000** rows a day and had reached **1.5 GB**.

The wait, measured over 30 days as the time between eBay's `creationDate` and our `created_at`, over 510 orders:

| Order placed | Orders | Median | 90th percentile | Over 2 h |
|---|---|---|---|---|
| Day (06–17 UTC) | 314 | 30 min | 81 min | 23 |
| Night | 196 | 2 h | 3 h 44 min | 98 |

## Why not just check more often

The obvious fix is to change `hourly()` to `everyFifteenMinutes()`. That is four times the API calls, four times the debug rows and four times the worker time. The dropshipping workers are shared with Shopify, TikTok and Woo, so it would also mean a burst of 2,600 jobs landing on the same queue every quarter of an hour.

And almost all of that extra work would go on the 2,469 channels that have not had an order in a month. The load is spread evenly across channels, but the orders are not.

## What we did instead

**Check the channels that sell.** A channel counts as active if it had an order in the last 30 days or was connected in the last 7. New channels are included because a customer waiting for their first order is the one watching most closely. That is 257 channels. They are now checked every 15 minutes, each within 5 minutes of the run: about 1,000 checks an hour, and a worst-case wait of about 20 minutes. The rest keep the hourly daytime run, and overnight goes from every four hours to every two.

The date comes from `last_order_created_at`, which the channel already keeps, so choosing the active set is a plain indexed comparison. It matched a count taken straight from the orders table exactly.

One trap is worth writing down. The obvious way to get the inactive set is `whereNot(active)`, and it silently drops every channel that has never had an order: `NOT (NULL > x OR false)` is `NULL`, not true. Those are the majority. So the inactive side is spelled out: `last_order_created_at IS NULL OR <= 30 days ago`, and connected more than 7 days ago. The test covers a never-ordered channel for exactly this reason.

**Check when the customer comes looking.** The complaint above has a shape. The customer sees an order on eBay, nothing moves, and they come to our website to look or to complain. Every page a logged-in customer opens already sends a hit that queues a job to record the visit. That job now also queues an eBay check for the customer's channels.

It must not become a way to hammer eBay just by browsing. Two things stop that:

- a cache key per customer, so this happens at most once every 10 minutes however much they browse;
- the per-channel job was already unique, so a login, a page view and the scheduled run arriving together still queue one check.

The worst case, a customer who browses all day, is 6 checks an hour. The usual case is one check, about a minute after they arrive, which is before they have finished writing the chat message.

**Log each order once.** The debug table keeps the raw eBay payload, which is useful when an import goes wrong, and useless the twentieth time we see the same order. The check skips it once a cache key for that order already exists, kept for 30 days. The key goes on the order, not on "was it imported". Cancelled orders and orders for listings outside the portfolio are never imported, so checking "is it already an aiku order?" would have kept logging exactly the orders that repeat most.

## Measuring it properly

The table above came from reading eBay's `creationDate` out of the JSON we keep on each order. It works, but only for eBay, and only for someone who knows where to look.

Orders now have a `platform_order_created_at` column: when the order was placed on the platform, as opposed to when it reached us. eBay orders fill it on import. The migration filled it for all 3,863 past eBay orders from the payload they already carried. This is a straight copy of a fact already stored on each row, not a guess. The wait is now `created_at - platform_order_created_at` on any order, and other platforms can fill the same column as their imports are touched.

We backfilled so there would be a *before*. Without it, the first two weeks of data would have had nothing to compare against.

## Results

*To be filled in once there are two weeks of orders after the change.*

What we expect, for the record, so the numbers can prove us wrong:

- active channels: median around 10 minutes, worst case about 20, the same by day and night;
- customer on the website: about a minute;
- channels without recent orders: no worse by day, and half the overnight wait;
- the debug table: from about 29,000 rows a day to a few hundred;
- total checks: up by about a third (the 15-minute tier), in exchange for most of the orders arriving four to eight times sooner.

## What we would keep

Before making something run more often, look at where the results actually come from. Forty thousand calls to find twenty orders is not a problem you solve with more calls.

Look at what the customer does just before they complain, and hang the check there.

And store the timestamp that lets you measure the wait, before changing the thing that causes it. Otherwise "it feels faster" is the only result you will have.
