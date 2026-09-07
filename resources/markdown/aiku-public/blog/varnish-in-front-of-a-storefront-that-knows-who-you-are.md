---
title: Varnish in front of a storefront that knows who you are
summary: Our storefronts are server-rendered and personalised, which is the worst case for an HTTP cache. Here is how we put Varnish in front of them anyway — two hash buckets, a logged-in bit, tagged objects, surgical bans, and a warmer that only warms what people actually visit.
date: 2025-10-20
tags: varnish, iris, ssr, caching
---

<aside class="tldr"><strong>TL;DR</strong>aiku's server-rendered, personalised storefront (Iris) sits behind Varnish by hashing the cache key on the Inertia headers to separate HTML and JSON payloads, and by collapsing personalisation to one `X-Logged-Status: In|Out` header instead of per-user pages. Responses are tagged with website/webpage/host/URL so bans can be surgical instead of full flushes. A traffic-weighted warmer, capped by a single fleet-wide reduce-only concurrency budget, pre-warms only the pages people actually visit.</aside>

aiku's storefront engine (we call it Iris) renders product pages on the server and then hydrates them as a single‑page app. Logged‑in trade customers see their prices, their basket, their favourites. Anonymous visitors see the catalogue. Search engines see the same HTML as humans. All of that is good for customers and bad for caching: the naive rule "cache the page" serves one person's basket to the next.

In October 2025 we put Varnish in front of it anyway. This note is what made that possible.

## The shape of the stack

```
Cloudflare → HAProxy → Varnish (two instances) → Octane workers
```

Cloudflare terminates TLS and absorbs the obvious abuse. HAProxy picks a Varnish by hostname. Varnish holds the rendered pages. Behind it, the same Octane workers that serve the staff app render storefront pages when Varnish misses.

## Two hash buckets, not one

An Inertia page has two faces: the full HTML document for a first load, and a JSON payload for subsequent in‑app navigation, distinguished by the `X-Inertia` header. Same URL, different body. So the cache key includes which face was asked for:

```vcl
sub vcl_hash {
    hash_data(req.http.host);
    hash_data(req.url);
    if (req.http.X-Inertia) { hash_data("Inertia"); } else { hash_data("Direct"); }
    if (req.http.X-Inertia-Version)           { hash_data(req.http.X-Inertia-Version); }
    if (req.http.X-Inertia-Partial-Component) { hash_data(req.http.X-Inertia-Partial-Component); }
    if (req.http.X-Inertia-Partial-Data)      { hash_data(req.http.X-Inertia-Partial-Data); }
    return (hash);
}
```

The Inertia *version* is part of the key on purpose: after a front‑end deploy, old cached payloads simply stop matching instead of needing to be purged.

## One bit of personalisation, not the whole user

The trick that made the rest possible: the page does not depend on *who* you are, only on *whether* you are logged in. Varnish reads the session cookie, reduces it to a header `X-Logged-Status: In|Out`, and the backend answers with `Vary: X-Logged-Status`. So every page has at most two cached variants, and the logged‑in variant is still shared by every logged‑in customer.

Everything that is actually personal — prices for your account, your basket count, your favourites — arrives by a second, uncached request after the page renders. The storefront shows a brief skeleton for those bits. Customers do not notice; the cache hit rate does.

## Tag the objects so you can ban precisely

Purging by URL is hopeless when a product appears on its own page, its family page, its department, search results, and a dozen collections. Instead the backend stamps every response with what it is made of:

```
X-AIKU-WEBSITE: 12
X-AIKU-WEBPAGE: 48213
X-AIKU-HOST: shop.example
X-AIKU-URL: /candles/lavender
```

and the VCL accepts bans against those tags:

```vcl
if (req.http.x-ban-webpage) {
    ban("obj.http.x-aiku-webpage == " + req.http.x-ban-webpage);
    return (synth(200, "Ban webpage " + req.http.x-ban-webpage));
}
if (req.http.x-ban-website) {
    ban("obj.http.x-aiku-website == " + req.http.x-ban-website);
    return (synth(200, "Ban website " + req.http.x-ban-website));
}
```

When a product changes, the application bans exactly the pages that carry it. When a website's design changes, it bans the website. When a canonical URL changes, it bans both the old and the new object — we learned that one the hard way, after cached 301s that pointed at each other produced an intermittent redirect loop on a couple of shops. Redirects are still cached for ten days (that is deliberate; they are the cheapest thing we serve), but now the VCL refuses to follow a redirect to itself and the application evicts the pair on change.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Ban and purge actions live under <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Webpage/BanVarnishWebpage.php">app/Actions/Web/Webpage</a> and <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Website/BreakWebsiteVarnishCache.php">app/Actions/Web/Website</a>, with the shared ban logic in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Traits/WithVarnishBan.php">app/Actions/Traits/WithVarnishBan.php</a>.</li>
<li>The VCL that implements the hash buckets and tag-based bans is checked into <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/devops/varnish/default.vcl">devops/varnish/default.vcl</a>.</li>
<li>Hit-rate and memory usage are recorded by <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Website/Analytics/RecordVarnishHitRatio.php">RecordVarnishHitRatio.php</a> and <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Website/Analytics/RecordVarnishMemoryUsage.php">RecordVarnishMemoryUsage.php</a>.</li>
<li>Varnish's own ban and vary mechanics are documented at <code>vcl_hash</code> and <code>ban()</code> in the <a href="https://varnish-cache.org/docs/">Varnish documentation</a>.</li>
</ul></aside>

## Don't flush what you didn't change

A deploy used to flush the whole cache. Now it computes a checksum of the storefront build outputs and flushes only when that changed — a separate [note](/blog/only-flush-the-cache-you-changed) covers the bug that hid behind that.

## Warm what people visit, not everything

After a flush the cache is cold and the first visitor to each page pays for the render. The first warmer we wrote crawled every link on every site — 267,000 live pages, more than half of which nobody had ever visited — and it competed with real customers for the same workers. It also had a subtle bug that made it *most* aggressive right after a deploy, exactly when you want it gentle.

The current warmer takes the pages viewed in the last thirty days, weights logged‑in views three‑to‑one over anonymous ones (anonymous may be bots), and fetches enough pages to cover ninety per cent of the weighted traffic — about 3,400 URLs on a typical large site. Never‑visited pages are not warmed; they render on demand like before. Concurrency is a single fleet‑wide budget, reduce‑only, so a crawl starting on an idle box cannot promote itself above a live shopper.

## What it looks like from the outside

Most storefront requests never reach PHP. The ones that do are the ones that should: a first view of a rarely‑seen page, a logged‑in payload, a basket. Deploys no longer cause a cold‑start dip unless the storefront actually changed. And when something is wrong on a page, the fix is a ban against a tag, not a prayer and a full flush.

<aside class="tldr bottom"><strong>In one paragraph</strong>A personalised, server-rendered storefront can still sit behind an HTTP cache if you shrink personalisation to a single header, tag every response with what it contains, and warm only the pages people actually visit.</aside>
