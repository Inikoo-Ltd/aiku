---
title: Born in a Singapore coffee shop
summary: Every codebase has a birthday; Aurora — the system aiku replaced — has a timestamp, a timezone and a table by the window. The header at the top of its entry point reads "Created: 27 August 2015 12:49:03 GMT+8, Singapore", and the commit from that day says "thought experiments to a full SPA". This is the origin story — the 2008 system that came before it, the habit of writing the city into every file header, and how "aiku" was a trait inside Aurora years before it was a product.
date: 2026-08-24
tags: history, aurora, origins
---

<aside class="tldr"><strong>TL;DR</strong>Aurora's entry point carries the header <code>Created: 27 August 2015 12:49:03 GMT+8, Singapore</code>; the same day's commit reads <em>"thought experiments to a full SPA"</em>. The repository it lives in starts in 2008 with a system called inikoo (issue tracker codename: KAKTUS). Every file header records the exact time and city it was written in — a travel diary across a dozen countries, three files created on planes. And in October 2020, in Kuala Lumpur, a file called <code>trait.Aiku.php</code> quietly named the successor before the successor existed.</aside>

That header still sits at the top of `app.php`, the entry point of Aurora, the system that ran our business for a decade:

```
/*
Created: 27 August 2015 12:49:03 GMT+8, Singapore
*/
```

I typed it in a coffee shop in Singapore. I'd been up late the night before, turning the same idea over and over: stop patching the system I already had, and build my own SPA‑like application instead — no framework, my rules, one page that behaved like an app.

The commit from that day says exactly what it was:

```
2015-08-27  "thought experiments to a full SPA"
```

Not a roadmap. Not a spec. A thought experiment, committed from a café table, that ended up processing every order, delivery note, invoice and mailshot the company produced for the next ten years.

<figure><img src="/art/readme/draw-note-singapore.svg" alt="Watercolor and pencil sketch: a man with a laptop and a steaming kopi at a night hawker stall, string lights under a red awning, a crescent moon and lit windows behind" width="1200" height="700" loading="eager"><figcaption>One table, one laptop, one thought experiment. Singapore, 2015.</figcaption></figure>

## What came before

Aurora wasn't a blank page. The repository it lives in starts seven years earlier:

```
2008-10-03 22:58  "Initial import"
```

That first system was called *inikoo* — and before the tickets said anything else, they said `KAKTUS-304`, because even a one‑person project deserves an issue tracker with a codename. It was built the way you built web software in 2008: Smarty templates, YUI widgets, jQuery, page reloads. It worked. It also taught me everything I would later refuse to do again.

By 2015 the web had moved. Single‑page applications were the obvious future, but the frameworks were young and I was stubborn. So Aurora got a hand‑rolled router, a hand‑rolled active‑record ORM with its own audit trail, a hand‑rolled forking job system years before I'd ever hear the word "Horizon", and translations in sixteen languages extracted by a script I wrote myself. When you're one developer, the framework is just the part of the codebase you wrote first.

Around that time the internet was inventing a name for people who worked like this — "digital nomad" had existed as a phrase since a 1997 book, but it only became a movement with a website and a hashtag around 2014. I didn't know I was one. I had a business to run and a laptop, and the laptop didn't care which country the coffee was in.

## The proof is in the headers

I had a habit: every file I created got a header with the exact time and the place where I was sitting. Nobody asked for it. Eighteen years later, those headers are a travel diary — Mijas Costa, Sheffield, Macau, Penang, Yiwu, Bali, Bangkok, Mexico City, Kuala Lumpur, Tokyo. Three files were created on planes; one header just says *"Flight Delhi India to Bangkok"*. The typos — "Sain" for Spain, "Malaydia" — are the proof they were typed live, not templated.

And in October 2020, in Kuala Lumpur, one of those headers quietly named the future:

```
// trait.Aiku.php
Created: Fri, 16 Oct 2020 15:03:29 Malaysia Time, Kuala Lumpur, Malaysia
```

Before aiku was a product, `Aiku` was a trait mixed into Aurora's base ORM class — the mechanism that made every customer, order and delivery note hydrate outward into something new. The successor didn't get a made‑up name. It got the name of the escape hatch.

Aurora is still alive — 14,880 commits and counting, roughly 85% of them mine, the most recent one from this month. It began in that coffee shop in Singapore, at 12:49 in the afternoon, GMT+8 — the moment the old system stopped being the future. [How we eventually walked out of Aurora too, one company at a time](/blog/four-years-of-walking-out-of-the-old-house), is its own story; this one is about the day it began.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Aurora is public: <a href="https://github.com/inikoo/aurora">github.com/inikoo/aurora</a> — 14,880 commits, 2008‑10‑03 to today.</li>
<li>The birthday header: <code>app.php</code>, line 3. The same‑day commit: <code>git log --since=2015-08-27 --until=2015-08-28 --oneline</code>.</li>
<li>The travel diary: <code>git grep -iE "created:.*(GMT|Time)" -- '*.php'</code> — ~914 dated, placed headers.</li>
<li>The name reveal: <code>trait.Aiku.php</code>, mixed into <code>class.DB_Table.php</code>; 62 <code>*Aiku</code> trait files by 2021.</li>
</ul></aside>
