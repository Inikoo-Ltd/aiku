---
title: SEO is plumbing
summary: Nobody on the team is an SEO specialist, and dozens of storefronts on one codebase rank anyway. That is because almost everything that matters is plumbing that a storefront engine either does for every shop or does for none — a computed canonical URL nobody types by hand, a 301 that follows a discontinued product to its family, a sitemap index regenerated nightly with a real lastmod, structured data that obeys the same price rules as the page. What we built, what we skipped on purpose, and the three things that bit us anyway.
date: 2026-08-30
tags: seo, storefront, iris, canonical, sitemaps
---

<aside class="tldr"><strong>TL;DR</strong>Dozens of shops, one storefront engine, no SEO team. What ranks them is machinery, not tactics: one canonical URL per page computed from the catalogue tree and enforced with a 301; discontinued pages redirected to the nearest live parent, never 404; a sitemap index per shop, split by type, regenerated nightly, <code>lastmod</code> from the real publish date; server‑side rendering behind a cache so crawlers get HTML in tens of milliseconds; JSON‑LD that hides the price when the page does. We skipped <code>rel=next/prev</code>, per‑shop robots rules and hreflang, and nothing happened. The things that hurt were a favicon, a CDN rule and a deploy.</aside>

Nobody on the team is an SEO specialist. We run dozens of storefronts — wholesale, retail, dropshipping — on one codebase, the [engine we wrote ourselves](/blog/your-website-your-rules), and they rank well in their markets. When we looked at why, the answer was not tactics. It was that the things search engines actually reward are mostly *plumbing*, and plumbing is exactly what a shared engine either does for every shop or for none. This note is the list.

## One canonical URL, computed, never typed

The most consequential decision was to take the URL away from humans. A product page's canonical URL is **computed** from where the product sits in the catalogue: department, sub‑department, family, product — walking up the tree, skipping anything that is closed, lower‑cased, no trailing slash, always on `www`. A family's URL is computed the same way from its ancestors; a collection's from its parents; the storefront is the root. There is no field where someone can type "a nicer URL".

Then the engine enforces it. Every request is compared against the stored canonical; if the path differs — an old slug, a trailing slash, a capital letter, the apex domain — the answer is a **301 to the canonical**, query string carried along. The `<link rel="canonical">` on the page is the same value. The sitemap lists the same value. There is exactly one spelling of every page, and the three places that could disagree about it cannot.

When a category is renamed or moved, the engine recomputes the canonical for everything beneath it — every product, family, sub‑department and collection — a couple of seconds apart so the queue is not flooded, and bans the old and new URLs from the cache so nobody is served a stale one. Half of what other people call "duplicate content problems" disappeared the day we stopped letting people choose URLs.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Canonical built per webpage type in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/Webpage/UpdateWebpageCanonicalUrl.php">UpdateWebpageCanonicalUrl</a>; catalogue pages walk the family → sub‑department → department ancestry and skip closed webpages; category changes cascade to children with a 2‑second delay per job.</li>
<li>Enforcement at request time in <code>ShowIrisWebpage</code>: requested URL ≠ canonical → <code>301</code>, with an <code>X-Aiku-Cacheable-Redirect</code> header so Varnish caches clean path redirects and skips ones carrying a query string.</li>
<li>On change: Varnish ban of old and new canonical, webpage cache key busted.</li>
</ul></aside>

## A discontinued product is a redirect, not a hole

Products go away. In a trading business they go away constantly — a supplier drops a line, a seasonal item ends, a pack size changes. The naive answer is a 404, which throws away every link and every bit of ranking the page earned. The other naive answer is to leave the page up, which sells things you do not have.

The engine does neither. When a product's webpage is closed because the product is discontinued, it creates a **301 to the nearest live parent**: the family, or if the family is gone the sub‑department, or the department, or as a last resort the shop's front page. The redirect is stored, counted per website, and resolved at request time when no live page matches the path. The crawler follows it, the link equity follows it, and the visitor lands somewhere that still sells the kind of thing they were looking for.

Products that are *discontinuing* — still in stock, not being reordered — stay live and in the sitemap until they are actually gone. There is no point telling a search engine a page is dead while you can still sell from it.

## Sitemaps that are true

Each shop has a **sitemap index** that points at one sitemap per page type: products, departments, sub‑departments, families, collections, blog posts, content pages, everything else. They are regenerated every night for every live website, capped at the 50,000‑URL limit, and every entry uses the canonical URL from the section above, so the sitemap and the page can never disagree.

The detail that mattered more than we expected: `lastmod` is the date the page's **live snapshot was actually published**, not the time the file was generated. A sitemap where every URL says "modified today" is a sitemap a crawler learns to ignore. Ours says when the thing really changed, and new products started appearing in results in a day or two instead of a week.

Products only make it in if they are for sale, active or discontinuing, and their webpage is live. Categories only if they are active or discontinuing. The same predicate that decides whether something is *in the website* decides whether it is in the sitemap, so there is no second list to keep in step.

## Fast, and in HTML

The storefront is [rendered on the server](/blog/rendering-the-storefront-twice) so a crawler gets the full page, not a shell that needs JavaScript, and it sits behind a [Varnish cache](/blog/warming-the-cache-three-times) that is [warmed after every deploy](/blog/only-flush-the-cache-you-changed). Time to first byte for a cached page is tens of milliseconds. Of all the ranking signals people argue about, speed is the one an engineer can actually control, and it is mostly a matter of not being slow.

Images get [the same treatment](/blog/one-image-six-urls): sized, formatted and served from a CDN by URL, so the page does not ship a 4 MB product photo to a phone.

## Structured data that obeys the page

Product pages carry JSON‑LD: `Product`, `Offer`, `BreadcrumbList`, with department and sub‑department pages carrying their own. The merchant can add structured data of their own in the page's SEO settings and it is merged in.

One rule we hold to: **if the visitor may not see the price, the `Offer` has no price.** Wholesale shops often hide prices from anonymous visitors. Structured data is public; a price in the JSON‑LD that is not on the page is both a policy leak and the kind of mismatch that gets rich results pulled. The markup follows the page, never the other way round.

## What we skipped, on purpose

- **`rel=next` / `rel=prev`.** Google stopped using it years ago. We never added it. Nothing happened.
- **Per‑shop robots.txt rules.** Every shop gets the same fifteen lines — keep crawlers out of baskets, account pages, attachments, feeds — plus its own sitemap lines. Nobody has ever needed a custom one.
- **hreflang.** Our multi‑language shops serve one language per country, and they rank fine in their own country without it. It would matter if we targeted the same language in two countries; we do not, so the complexity stays out.
- **Image sitemaps.** Product images are discovered from the pages. We have not seen evidence that a separate image sitemap would change anything for us.

The point is not that these are wrong. It is that a team without an SEO specialist should spend its effort on the plumbing above, which is measurable, and not on the long tail, which is mostly folklore.

## The three things that bit us anyway

None of them were SEO decisions. All of them were plumbing failures.

- **A favicon that 404'd on every page of every shop for months.** Nobody looks at favicons. Crawlers request them on every visit. The fix was one route; the cost was crawl budget and a log full of noise.
- **A CDN rule serving an old `robots.txt`.** The application was right; the edge was caching the wrong thing. It took longer to find than to fix, because everyone checked the app first.
- **A deploy that left old JavaScript chunks behind.** For an hour after each release, crawlers that had cached the old page requested files that no longer existed. [Fixed in the deploy](/blog/anatomy-of-a-deploy), not in the SEO.

## What we would tell a team

Compute the URL; do not let anyone type it. Redirect the dead; never 404 a page that earned a link. Make the sitemap say the truth about dates. Be fast, and be HTML. Let the structured data follow the page. Then stop, and watch your logs, because the next problem will not be an SEO problem either.
