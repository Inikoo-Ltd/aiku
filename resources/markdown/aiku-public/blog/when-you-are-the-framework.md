---
title: When you are the framework
summary: Aurora has no framework, because in 2008 there was nothing worth adopting — so it grew its own. A 5,864-line router, an active-record ORM with a built-in audit trail, a process-forking job runner years before queues were a composer require, translations in sixteen languages with a homemade string extractor, and a Content-Security-Policy assembled by string concatenation. A tour of what one developer hand-rolls in fourteen years, what each piece is called today, and why the answer to "was it bad?" is more interesting than yes or no.
date: 2026-08-24
tags: history, aurora, architecture, laravel
---

<aside class="tldr"><strong>TL;DR</strong>Aurora hand-rolls everything a modern Laravel app installs: routing (<code>parse_request.php</code>, 5,864 lines), ORM with dirty-tracking and an audit trail (<code>class.DB_Table.php</code>), background jobs by forking processes (<code>new_fork.php</code>), i18n in 16 locales with a homemade extractor, auth, API keys, CSP headers built by string concatenation. None of it was bad — most of it predates the tools that replaced it. The real cost was different: a stack only one person on earth knew. That's the debt [Pika was created to pay off](/blog/aiku-existed-inside-aurora).</aside>

When people hear that Aurora has no framework, they picture spaghetti. The truth is stranger: Aurora *is* a framework. It has a router, an ORM, a template layer, a job runner, an i18n pipeline, auth, API keys — every organ a framework has. It just has exactly one user, and I wrote all of it.

That wasn't a stubborn choice in the way it sounds now. [In 2008](/blog/born-in-a-singapore-coffee-shop), "use a framework" wasn't obvious advice in PHP land — Laravel didn't exist, and the options that did were young, slow-moving, or likely to die. Building your own plumbing was what serious PHP shops did. The interesting part is what fourteen years of that actually looks like.

<figure><img src="/art/readme/draw-note-hand-rolled.svg" alt="Watercolor sketch: a workshop pegboard of hand-made tools labelled the router, the ORM, the views, the queue and the i18n, with a dotted line to a boxed toolkit labelled framework in a box — 2022: bought the box" width="1200" height="700" loading="eager"><figcaption>Every organ a framework has — one user each.</figcaption></figure>

## The organs, and their modern names

**The router.** Requests land in `app.php` and are dispatched by `parse_request.php` — 5,864 lines — against `tabs.defaults.php`, another 5,532 lines defining hundreds of "tabs": Aurora's word for what you'd now call routes and controllers, fused. And [in August 2015](/blog/born-in-a-singapore-coffee-shop), when the whole thing turned into a single-page app, this hand-rolled dispatcher quietly became something most 2015 frameworks couldn't do yet: one page, swapping tabs, no reloads.

**The ORM.** Every entity extends `class.DB_Table.php` — hand-rolled active record: load by key, track dirty values, `update()` writes only what changed. And baked into the base class, an editor trail — who changed the field, and when — because in a business system the question is never just "what is the value" but "who set it". Eloquent would give me the first half today; the audit half is *still* a package you have to go and choose.

**The views.** Smarty templates — the respectable choice of its day, upgraded over the years rather than replaced ("upgrade smarty", March 2016), progressively demoted as the SPA moved rendering to the client.

**The queue.** There is no queue. `utils/new_fork.php` forks the PHP process: the request finishes, the fork does the slow work — reindexing, emails, housekeeping. `new_housekeeping_fork()` is Horizon, if Horizon were eighty lines and had no dashboard, no retries, and no supervisor. It ran the business for fifteen years.

**The i18n.** A gettext-style `_()` on every user-facing string, sixteen locale folders — Czech, Slovak, Polish, Hungarian, Croatian, Chinese, Hindi, Malay, Indonesian... — and `tsmarty2c.php`, a homemade extractor that walks the Smarty templates and harvests translatable strings. Warehouses speak the local language; the system had no choice.

**The security headers.** No middleware stack, so `app.php` just concatenates the Content-Security-Policy by hand — nonce generation and a long string of allowed domains, inline, where you can read every character of it.

**The parts nobody hand-rolls.** PDF generation is a vendored TCPDF — 30,884 lines, the single biggest file in the repo — *plus* mPDF via composer, because fifteen years of invoice fixes accumulate libraries the way a kitchen drawer accumulates openers. The frontend strata are archaeology on their own: YUI widgets ("updating to yui 2.6.0", the first week of the repo), then jQuery, then a 44-kilobyte Gruntfile, then Bower, each layer deposited on the last.

## Was it bad?

Wrong question. Most of it worked for fifteen years under real load, and most of it predates the tool that would have replaced it. The right question is the one [I finally asked in a warehouse in Eastern Europe in 2022](/blog/aiku-existed-inside-aurora): *who else can work on this?*

A framework's real product isn't the router or the ORM — you can write those, clearly. It's the shared knowledge. Every Laravel developer on earth already knows how aiku's routing, queues, auth and ORM work before they read a line of it. Nobody alive knew how Aurora's worked except me, and every year that got more expensive: no Stack Overflow answers, no documentation I didn't write, no hire that starts productive. Hand-rolling the plumbing was the right call in 2008 and the wrong call to still be making in 2022 — not because the code aged, but because I stopped being the only person who needed to understand it.

So when people ask [why Laravel](/blog/why-laravel-still), the honest answer starts here: I know exactly what a framework is worth, because I priced one from scratch, in parts, over fourteen years.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Aurora is public: <a href="https://github.com/inikoo/aurora">github.com/inikoo/aurora</a>. The organs: <code>app.php</code>, <code>parse_request.php</code> (5,864 lines), <code>conf/tabs.defaults.php</code> (5,532), <code>class.DB_Table.php</code>, <code>utils/new_fork.php</code>, <code>locale/</code> (16 folders), <code>tsmarty2c.php</code>.</li>
<li>Other giants, all hand-written: <code>helpers/view/get_breadcrumbs.php</code> (6,090 lines), <code>class.Part.php</code> (5,473), <code>class.Store.php</code> (5,016), <code>class.Product.php</code> (4,810).</li>
<li>The SPA turn: commit "thought experiments to a full SPA", 27 Aug 2015.</li>
<li>What replaced each organ in aiku: routing → Laravel router; <code>DB_Table</code> → Eloquent + model audits; <code>new_fork</code> → Horizon queues; <code>_()</code>/<code>tsmarty2c</code> → Laravel localization; Smarty → Inertia/Vue; hand-built CSP → middleware.</li>
</ul></aside>
