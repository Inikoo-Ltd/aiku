---
title: Our own analytics beacon
summary: Every storefront page sends one small request to our own endpoint — not to a third party. From it: visitors, sessions, page views, country, device, referrer, the traffic source that becomes attribution, registration and add‑to‑basket conversions, "who is on the site right now", product interest per customer, and the list of most‑visited pages that decides what the cache warmer warms. Why a beacon that fires on cached pages was the only design that could work, how bots are filtered, and what we deliberately do not collect.
date: 2026-08-06
tags: analytics, storefront, privacy, marketing, varnish
---

<aside class="tldr"><strong>TL;DR</strong>Every storefront page fires one browser-side <code>POST /analytics/hit</code> to our own endpoint, because Varnish-cached pages never reach the app server for server-side logging. Each hit becomes a visitor, page view, traffic-source touch and conversion event, feeding the live-visitor view, per-site analytics, attribution, the cache warmer's most-visited-page list, and customer service's visitor timeline. Roughly half of raw hits are bots and get filtered before any row is written; no third-party script, fingerprinting, or stored IP address.</aside>

We ran a hosted analytics script on the storefronts for years, like everyone. Then we put [Varnish in front](/blog/varnish-in-front-of-a-storefront-that-knows-who-you-are) of the storefronts, started doing [attribution](/blog/marketing-attribution-that-adds-up) properly, and found we needed to *join* visits to customers, orders and products — the one thing a hosted tool can never do, because it does not have the rows. So the storefronts now report to us. This note is what the beacon does, and what it does not.

## One request per page

When a storefront page renders in the browser, a tiny script sends one `POST /analytics/hit` to our own domain: the page, the referrer, the screen class, a session id held in a first‑party cookie, and — if the URL carried one — the click id or campaign reference that identifies a traffic source. That is the whole payload. It fires from the browser, *after* the page, which matters for a reason that took us a while to state clearly: **a page served from the cache never reaches our application server**, so server‑side logging would see only cache misses and go blind exactly as the cache got good. A client beacon sees every view, cached or not. The endpoint is rate‑limited and sampled out of the telemetry that watches the rest of the app, because it is high‑volume and boring by design.

## What a hit becomes

- A **visitor** (a session: first seen, last seen, country from the IP via a local GeoIP database, device and browser from a local device‑detector library, whether they are logged in, and — once they register or log in — the customer they turned out to be). About 243,000 of them in the recent window; 1.1 million page views.
- A **page view**, linked to the webpage and, when the page is a product or a family, to the product — which is how a customer's *product interests* ("you looked at this three times last month") are derived for customer service and for merchandising.
- A **traffic‑source touch** when the hit carried a source, which is the first step of attribution.
- A **conversion event** when the visitor registers or adds to basket, so a source's registrations and baskets can be counted the same way as its orders.

All of it lands on queues; the request itself returns in a few milliseconds.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The endpoint's action is [app/Actions/Web/Website/Analytics/RecordWebsiteHit.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Website/Analytics/RecordWebsiteHit.php), routed at <code>POST /analytics/hit</code> in [routes/analytics/analytics.php](https://github.com/Inikoo-Ltd/aiku/blob/main/routes/analytics/analytics.php).</li>
</ul></aside>

## Bots

Roughly half of raw hits are not people. The user agent is checked against a maintained bot signature list (the same open library that classifies devices), with the verdict cached per agent string, and bots are dropped before any row is written. Known crawlers never appear in the visitor counts; unknown ones are the accepted error. A later rule for the warmer — *weight logged‑in views three to one over anonymous* — exists because "anonymous" still includes whatever slipped through.

## What the rows are used for

- **The live view**: who is on which site right now, by country and by page — the customer‑service room's favourite screen.
- **Per‑website analytics**: visitors, views, top pages, conversion by page, device and country split, over any period, with the previous period alongside.
- **Attribution**: the touch joins to the customer and the order; revenue follows.
- **The cache warmer**: the list of most‑viewed pages in the last thirty days, weighted, is what [gets warmed after a deploy](/blog/varnish-in-front-of-a-storefront-that-knows-who-you-are) — about 90% of weighted traffic from a few thousand URLs, never the long tail nobody visits.
- **Customer service**: the visitor timeline on the customer record (where they went before they asked the question).

## What we deliberately do not do

No third‑party script, no fingerprinting, no cross‑site anything: the cookie is first‑party and the session id is meaningless outside our own database. The IP address is not stored — it is reduced to a country and a one‑way hash used only to tell sessions apart, then discarded. No page content, no keystrokes, no scroll maps. The data exists to answer our own operational questions about our own sites, and it lives under the same retention and erasure rules as the rest of the customer record.

## What we would tell a team weighing it

If you cache your pages, your analytics must fire from the browser or it will lie. If you want visits to mean anything commercially, the rows must live next to your customers and orders. And if you write the beacon yourself, you get to decide — and publish — exactly what it collects, which is a sentence you cannot write about a script someone else serves.

<aside class="tldr bottom"><strong>In one paragraph</strong>Owning the beacon means the storefront's analytics still work when pages are cached, and it lets visits join directly to customers, orders and products — the one thing a hosted script could never do.</aside>
