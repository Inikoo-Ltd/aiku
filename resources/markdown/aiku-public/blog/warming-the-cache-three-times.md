---
title: Warming the cache, three times
summary: After a flush, the first visitor to each storefront page pays for the render. We have written the thing that pre‑pays three times: a link‑following crawler from a package, a two‑pass seeder‑then‑deep crawl with concurrency rules, and — the one we kept — a list of the pages people actually visit, fetched in order of traffic, at a concurrency that can never outbid a live shopper. Why each version was wrong for the next stage, and the surge‑protection bug that made the warmer most aggressive exactly when it should have been gentlest.
date: 2026-08-15
tags: varnish, caching, performance, horizon, storefront
---

<aside class="tldr"><strong>TL;DR</strong>We built the storefront cache warmer three times: a link-following crawler that couldn't tell which of 267,000 pages anyone visited, a two-pass seeder-then-deep crawl whose surge-protection code accidentally raised concurrency right after a deploy (load hit 60 on 32 cores), and the version we kept — warming only the pages people actually viewed in the last 30 days, weighted by login status, covering 90% of traffic, under a single fleet-wide reduce-only concurrency budget.</aside>

The storefronts are [served from Varnish](/blog/varnish-in-front-of-a-storefront-that-knows-who-you-are). A deploy that changes the storefront flushes the cache; the first visitor to each page afterwards waits for a full server render. A **cache warmer** is the job that visits pages first so customers do not. We have built it three times. Each version was right for the cache we had and wrong for the one we got next.

## Version one: follow the links (May 2026)

The first warmer was the obvious one: point a crawler package at the storefront's home page and follow every link to a depth. It worked for the first week, on the first shop. Then it met the group: twenty‑nine live storefronts, 267,000 live pages, and a crawler that had no idea which of those pages anyone visited. It warmed everything, slowly, and competed with real customers for the same rendering workers while it did. A run after a deploy could still be going when the next deploy flushed it all again.

## Version two: seed shallow, then go deep

The second version kept the crawler but made it two‑pass: a *seeder* crawl to depth two — home, departments, families — to get the common pages hot fast, then a deeper crawl behind it. It got a state machine (a `Crawl` row per site with state, URL counts and concurrency), a stale‑crawl purge, a stop action, and a per‑site concurrency assignment: big sites more workers, small sites fewer, capped by a global budget. On paper it was sensible.

Then a Thursday. After a deploy, the primary's load went to 60 on 32 cores, 45% of it in system time, all of the SSR workers pinned, while real customer traffic was under one request per second. Twenty thousand server renders in half an hour. The culprit was a function called *protect from surges*: it capped a site's concurrency with `min()` and then, one line later, floored it back *up* with `max()` to a per‑tier minimum — so a crawl starting on an idle box was *promoted*, never limited. Every long‑tail site went from one worker to three, and a fleet crawl was at its most aggressive in the minute after a deploy, which is the one minute it should have been gentlest. The function was rewritten to be reduce‑only and the global budget halved. It was also the moment we admitted the crawler was the wrong tool.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The <code>Crawl</code> state machine lives at <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Web/Crawl.php">app/Models/Web/Crawl.php</a>, with its states and trigger/type enums in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Enums/Web/Crawl/CrawlStateEnum.php">app/Enums/Web/Crawl</a>.</li>
<li>The crawl actions themselves are <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Crawl/CrawlWebsite.php">CrawlWebsite.php</a> and <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Crawl/CrawlWebsites.php">CrawlWebsites.php</a>, with stale rows swept by <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Crawl/PurgeStaleCrawls.php">PurgeStaleCrawls.php</a> and a manual stop in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Crawl/StopCrawl.php">StopCrawl.php</a>.</li>
<li>The bug class — a <code>min()</code> cap immediately re-raised by a later <code>max()</code> floor — is the classic Horizon/queue concurrency footgun: a reduce-only budget must never have a later step that raises it back up. See the general pattern in the <a href="https://laravel.com/docs/horizon">Laravel Horizon docs</a> on balancing strategies and <code>maxProcesses</code>.</li>
</ul></aside>

## Version three: warm what people visit (August 2026)

We already had [our own page‑view rows](/blog/our-own-analytics-beacon). So the warmer stopped crawling. It now takes, per website, the pages viewed in the last thirty days, scores them — a logged‑in view counts three, an anonymous one counts one, because anonymous still includes whatever bots slipped through — and fetches enough of them, in descending order, to cover **90% of the weighted traffic**. On a typical large site that is about 3,400 URLs out of 8,700 that had any view and 267,000 that exist; the last 10% of traffic would have cost 60% more fetching. Never‑visited pages are not warmed; they render on demand as they always did. Zero‑traffic sites warm only their home page.

The fetches go through an HTTP pool at a concurrency that is a **single fleet‑wide budget, reduce‑only**, read from the database so both servers share it. Seeder mode, depth and crawl type were deleted; the `Crawl` row stayed as the progress record. The crawler package left the dependency list.

A deploy fires one warming run sixty seconds after it finishes — only when the storefront's checksum changed — so every deploy is its own test: watch the primary's load in that minute and the Varnish hit rate on the big sites in the next.

## What the third version still gets wrong, on purpose

It is blind to pages that only bots visit. Our beacon fires from the browser, so a page that search engines crawl and humans do not has no view rows and is not warmed; a crawler's first visit after a flush is slow. We accept that: a slow first render for a crawler is cheaper than warming a quarter of a million pages for them, and the pages humans want are hot.

## What we learned

Do not warm everything; warm what is visited, in order, to a coverage you can defend. Make the concurrency budget global and *reduce‑only* — any code path that can raise it is a surge waiting for a deploy. Let the warmer run through the same front door as customers, so you are warming what they will actually hit. And keep the page‑view rows next to the warmer; the crawler was only ever a way of guessing what those rows already knew.

<aside class="tldr bottom"><strong>In one paragraph</strong>Three rewrites taught the same lesson: don't guess what to warm by crawling links, warm what your own traffic data says people visit, and make any concurrency safety valve reduce-only so it can never promote itself into a surge.</aside>
