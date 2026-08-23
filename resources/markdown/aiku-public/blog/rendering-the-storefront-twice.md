---
title: Rendering the storefront twice
summary: Every storefront page is rendered once on the server — so crawlers and slow phones get full HTML — and once more in the browser, where it becomes an app. How Inertia server‑side rendering runs here (a Bun process under a supervisor, health‑checked on every deploy), what a 418 KB props payload taught us about where the second actually goes, the silent client‑side‑rendering incident, and the three levers we keep on the list.
date: 2026-07-31
tags: ssr, inertia, vue, performance, storefront
---

A storefront page in aiku is a Vue application. That is good for the experience once it is loaded — navigation is instant, the basket updates in place — and bad for the two visitors who matter most and do not run JavaScript well: a search engine's crawler and a phone on a bad connection. So every storefront page is rendered **twice**: once on the server into complete HTML, and once in the browser, where the same components take over the HTML they were given and become interactive. This note is how the first render is made to happen, and what it costs.

## The shape

The storefront is an Inertia application: a PHP action builds the page's props (the webpage, its blocks, the product cards, the menu), and either returns them as JSON for an in‑app navigation or hands them to the **SSR process** for a first load. The SSR process is a long‑lived Node‑compatible runtime — we use Bun — running the same Vue components in server mode, listening on a local port, started from the [anchor directory](/blog/moving-to-octane) under the process supervisor like everything else. PHP posts it the page name and the props; it returns the HTML; PHP wraps it in the layout and sends it. The browser then loads the same bundle, hydrates, and from that moment it is an app.

The two renders must agree exactly — same component, same props, same output — or the browser throws away the server's HTML and re‑renders from scratch, which is slower than not having SSR at all. Keeping them identical is mostly discipline: no `window` in render code, no time‑of‑day in markup, and the storefront's locale chunks trimmed to the keys the server and client both use.

## The silent incident

A supervisor can report a process as *running* while the port it is supposed to listen on is dead. One July afternoon that happened to the SSR process: Inertia noticed, fell back to client‑side rendering, every page still worked for humans, and nothing alarmed — except that for some hours crawlers were receiving an empty shell, and first loads on slow phones were noticeably slower. Nobody reported it, because nothing was broken in the ordinary sense.

The fix went into the deploy: after every release the SSR port is **health‑checked**, and the process is restarted if the check fails — independently of whether the release changed the storefront. And the restart is otherwise gated on the [storefront build checksum](/blog/only-flush-the-cache-you-changed), so a staff‑only deploy does not bounce it for nothing.

## Where the second goes

With SSR in place the catalogue pages — three quarters of all user‑facing wait — still had a p50 around 1.1 seconds. We profiled them expecting slow queries and found the data build fast and cached: a quarter of a second cold, thirteen queries, six hours in Redis. The second was *after* the data: serialising, shipping, server‑rendering and then hydrating a **418 KB props payload** — two hundred product cards at about 1.5 KB each, two thirds of which was image URLs: [eight signed proxy URLs per card](/blog/one-image-six-urls).

That reframed the problem. The page was not slow because of the database; it was slow because of how much of it there was to render twice. The levers on the list, ranked:

1. **Shrink the card.** The eight image URLs share a base; ship base + size + per‑format signatures (a few hundred bytes) and assemble on the client. Signatures cannot be made client‑side — they are HMACs — so they still travel, but the rest does not. The wire saving after gzip is modest; the real saving is rendering and hydrating a quarter of the props.
2. **Warm the cache after a flush** so the cold 1.1 s hits go to [the warmer](/blog/warming-the-cache-three-times), not to a customer.
3. **Move the personal bits out of the page.** A logged‑in customer's per‑line details ride in the props today, which forces a separate cache entry per login state; moving them to the follow‑up request lets guest and logged‑in visitors share one Varnish entry.

The second lever is done. The first and third are open, and honest: field trimming is risky because the front end sorts on some of those fields, and we would rather carry a few kilobytes than break a sort.

## Why we keep both renders

Because the alternatives are worse in specific ways. Client‑only rendering hands crawlers nothing and phones a spinner. Server‑only rendering gives up the instant basket and the in‑app navigation that trade customers placing forty‑line orders actually use. Rendering twice costs a Bun process, a health check and a discipline about purity; it buys a page that is a document to a crawler and an application to a person.
