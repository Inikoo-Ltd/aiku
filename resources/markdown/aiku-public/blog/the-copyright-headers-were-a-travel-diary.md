---
title: The copyright headers were a travel diary
summary: Every file in Aurora starts with a header recording the exact time — and the city — where it was created. Nobody asked for it, and for seven years it recorded nothing but a company name. Then, from October 2015, the headers start naming places, and read end to end they become a travel diary — Spain, Sheffield, Macau, Penang, a factory city in China, Bali, Bangkok, Mexico City, Tokyo, Kuala Lumpur — including three files created mid-flight. The typos are the proof it was all typed live.
date: 2026-08-24
tags: history, aurora, origins
---

<aside class="tldr"><strong>TL;DR</strong>~914 file headers in Aurora carry a <code>Created:</code> line with a timestamp and a place. They start in Mijas Costa in October 2015 (spelled "Sain"), pass through Sheffield, Macau, Penang, Yiwu, Bali, Bangkok, Phuket, Mexico City and Tokyo, settle into Kuala Lumpur and Cyberjaya for the working years, and return to Spain by late 2019. Three headers were written on planes. None of it was planned as a diary — which is exactly why it is one.</aside>

When I create a file, I stamp it: author, copyright, and a `Created:` line with the exact time and the place where I'm sitting. I picked up the habit early and never dropped it. No tool asked for the city. It doesn't compile to anything. It was just true, so I wrote it down.

For the first seven years the headers say only `Copyright (c) 2009, Inikoo` — no geography. Then, in October 2015, a few weeks after [the afternoon in Singapore](/blog/born-in-a-singapore-coffee-shop) when Aurora began, the first place appears:

```
Created:  10 October 2015 at 18:27:24 CEST, Mijas Costa, Sain
```

"Sain". I was writing a basket module for the e-commerce front end and misspelled the country I was living in. That typo is my favourite line in the codebase, because it proves everything else: these headers were typed live, one at a time, by a person in a hurry — not generated from a template.

<figure><img src="/art/readme/draw-note-travel-diary.svg" alt="Watercolor sketch of luggage tags with city names and dates — Mijas Costa, Sheffield, Yiwu, Bali, Mexico City, Tokyo, Kuala Lumpur — joined by a dotted pencil route and a paper plane" width="1200" height="700" loading="eager"><figcaption>Read end to end, the headers draw a route.</figcaption></figure>

## The route

Read chronologically, roughly nine hundred dated, placed headers draw a map:

- **Mijas Costa, Spain** — the first placed header, October 2015, and the recurring home base for years after.
- **Sheffield, UK** — from November 2015 (`Created: 21 November 2015 at 14:41:00 GMT, Sheffield UK` on the API entry point) through 2017. The business ran from Yorkshire; a lot of Aurora was written there.
- **Bangkok and Phuket, Thailand** — around same time.
- **Macau, December 2015** and **Penang, January 2016** — short stops, each leaving a file or two behind. The Macau files are tax-region billing tables; wherever I was, the work was the work.
- **Yiwu, China** — March to November 2016. Yiwu is the wholesale-market city where the products came from; the image-handling tables were written there, between supplier visits.
- **Bali** — Lovina, Ubud, Kuta, Sanur, Legian, from April 2016 with return trips for years. The agent and warehouse-location classes carry Balinese datelines.
- **Mexico City, May 2017** — two headers on the same day, 09:21 and 22:06, a full working day in CdMx. One of them is the public storefront's 404 page.
- **Tokyo, September 2018** — one file, created at 23:17. Some cities you only get one evening in.
- **Kuala Lumpur and Cyberjaya, Malaysia** — the biggest cluster by far, 2016 through 2022. If Aurora has a second home town after Sheffield, it's KL.
- Back to **Mijas Costa** — densely from late 2019, through at least April 2022.

And three headers weren't written in any city at all:

```
Created:  19 August 2017 at 15:31:19 GMT+5:30, Flight Delhi India to Bangkok
```

Others say "Plane Kuala Lumpur, Malaysia - Denpasar, Bali, Indonesia" and "Plane (Hangzhou - Kuala Lumpur)". An orders table, written in seat-back-tray posture, somewhere over the Bay of Bengal.

## Metadata as memoir

The internet had just invented a word for working like this — the "digital nomad" movement got its websites and hashtags around 2014 — but I wasn't following a movement. I had suppliers in China, a warehouse in Sheffield, and a laptop that didn't care which country the coffee was in. The headers weren't documenting a lifestyle. They were documenting files. The lifestyle is just what falls out when you read two thousand of them in date order.

That's the thing I'd tell any developer working alone: write down more than the tools ask for. Version control remembers *what* changed and *when*. The header remembered *where*, and eighteen years later that's the difference between a git log and a diary. The misspellings — "Sain", "Malaydia", "Sheffied", "Indonesioa" — cost nothing at the time and now they're the watermark that makes the whole record credible.

The habit didn't stop with Aurora. The files in aiku carry the same headers — the class that renders this very blog post was stamped `Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia`. Ten years after the first placed header, still writing, still in KL, still writing it down.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Aurora is public: <a href="https://github.com/inikoo/aurora">github.com/inikoo/aurora</a>. Find the diary yourself: <code>git grep -iE "created:.*(GMT|Time)" -- '*.php'</code> — ~914 dated, placed headers.</li>
<li>First placed header: <code>EcomB2B/ar_web_client_basket.php</code>-family, 10 Oct 2015. In-flight headers: e.g. <code>prepare_table/orders.approved.ptble.php</code>.</li>
<li>No geography before Oct 2015 (headers carry only <code>Copyright (c) &lt;year&gt;, Inikoo</code>), and the habit continues unchanged in aiku's own file headers today.</li>
</ul></aside>
