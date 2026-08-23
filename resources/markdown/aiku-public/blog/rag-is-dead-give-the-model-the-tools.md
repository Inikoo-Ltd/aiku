---
title: RAG is dead. Give the model the tools. (Enterprise AI at zero cost)
summary: We did not embed the business into a vector store and hope the right chunk came back. We gave the model forty‑five tools — the same actions the staff app runs, under the same permissions — and a guide on how to use them. The result is a report that used to take three people a day, or a ticket to engineering, arriving in thirty seconds with real figures. The reactions, in order: disbelief, checking whether the numbers were random, and then a very quiet "oh".
date: 2026-08-23
tags: mcp, ai, permissions, architecture
---

<aside class="tldr"><strong>TL;DR</strong>For a live business, retrieval over documents is the wrong shape: the answers are not in a document, they are in a query that has not been run yet. aiku exposes forty‑five read‑only <strong>tools</strong> over the <a href="https://modelcontextprotocol.io">Model Context Protocol</a> — thin wrappers on the same actions the staff app uses, scoped by the same permissions, identified by slugs so the model cannot guess its way into the wrong shop, with a guide served alongside. Any assistant that speaks MCP connects with a personal token and asks. Reports that used to be a day's work or an engineering ticket take thirty seconds, with real figures. Some staff were afraid of it; most, after checking the numbers were not random, called it magic.</aside>

## Why not RAG

The fashionable way to put an AI on top of a business is retrieval‑augmented generation: chunk your documents, embed them, and at question time fetch the chunks that look most similar and paste them into the prompt. It is a reasonable design for a library. It is the wrong design for a trading company, for a reason that is obvious once said: **the answer to "how did lavender candles do last quarter in the Spanish shop" is not in any document.** It is the result of a query nobody has run yet, over rows that changed this morning, filtered by what *you* are allowed to see. No amount of embedding a PDF gets you there, and a chunk of last year's sales report pasted into a prompt gets you a confident, stale, wrong number.

So we did not build RAG. We built **tools** — and gave the model the same door the staff use.

## The shape

aiku ships its own MCP server. Any client that speaks the protocol — a desktop assistant, an IDE, an agent framework — connects with a personal access token and sees a list of tools: *shop sales, top products, family sales, order status, customer lookup, stock levels, slow stock, warehouse performance, mailshot performance, marketing by channel, employee attendance, refunds by product…* forty‑five of them. Each tool is a thin wrapper over an action class the web application already runs, returns structured data, and carries a description written for the model: what it answers, what it needs, what to call first if you do not have it. A guide served from the same endpoint says how they fit together.

The model decides which tools to call and in what order; the tools decide what it is allowed to see. That division is the whole design.

<figure><img src="/art/readme/draw-note-mcp.svg" alt="Hand-drawn sketch: an assistant on the left, a row of labelled tools in the middle with a locked door for what the user may not see, the business on the right" width="1200" height="750" loading="eager"><figcaption>Not retrieval: tools. The assistant asks; the tool runs the real query under the real permissions; the answer is the answer.</figcaption></figure>

## Read-only, and that is a management decision

Every tool is read-only. There is no "create order", no "update price", and we do not intend to add one quietly. Giving a language model the ability to change commercial data is an authorisation question for the people who run the company, not a developer convenience. The server makes that boundary structural: a tool either reads or it does not exist.

The one exception to "purpose-built tools only" is a SQL tool, and it is gated behind a separate permission that we do not grant ourselves. When someone needs a question answered that the tools cannot, we build a tool for it. That keeps the model's reach legible.

## The same permissions as the UI

Every request runs as the user who owns the token. A tool called against a shop the user cannot see in the web app returns a permission error, not an empty result — the distinction matters when an assistant is deciding whether to try a different route.

This fell out naturally because the tools are thin wrappers over the same action classes the UI uses. There is no second authorisation layer to drift.

## Slugs, not names

Tools identify shops, organisations and warehouses by **slug**, never by display name. A model asked about "the UK shop" is told to call `my-access` first, which returns the slugs the user can reach, and then to use one of those. It is never allowed to guess. Guessing is how an assistant cheerfully reports numbers from the wrong entity with total confidence.

## A door into the app, not a copy of it

Lookup tools for products, customers and orders return a `grp_url`: the full URL of that record in the staff application. If the user's assistant can drive a browser, it can go and look. We chose that over re-implementing every screen as a tool, and over sprinkling the DOM with agent-only hints.

On that last point we were deliberate. We will make the busiest pages more *accessible* — labels on icon buttons, proper table headers, keyboard-reachable actions — because that helps humans and agents equally. We will not add hidden `data-agent-*` attributes or instructions in the markup: they are visible to crawlers, they drift, and on a public storefront they are a prompt-injection surface.

## What forty tools look like

A sample of the registry, by area:

- **Sales** — group, shop, family and product sales over any date range; top products; order funnel; refunds by product; offer performance.
- **Customers** — lookup, notes, conversion, email pressure (how many sends a customer has received recently, so nobody mails them a fifth time).
- **Warehouse** — stock levels, slow stock, warehouse performance, delivery-note summaries.
- **Marketing** — channel and campaign performance, mailshot performance, margin trends.
- **People** — employee directory and attendance, staff chat analytics.
- **Meta** — `my-access`, `describe-tables` (for SQL users), and the tool that tells the model how to use the others.

Each tool's description is written for the model, not for us: what it returns, what it needs, and what to call first if you do not have it.

## A guide in the server, not in the client

We also serve an `llms.txt`-style guide from the same endpoint: house rules for tone when the assistant drafts customer replies, the data-protection rules about customer ids, the things staff must never paste into a chat. Putting the rules next to the tools means every client gets the same ones.

## What happened when people used it

Reactions came in a predictable order.

First, **disbelief**. A manager typed a question they had been asking for years — which families in which shop were down against last year, with margins — and got a table back before they had finished reading their own question. The first response, from more than one person, was some version of *"how can it be telling me this?"* A few quietly went and checked whether the numbers were random. They were not. They were the figures from the same rows the dashboards read, and they matched.

Then, **the arithmetic of time**. A report like that used to be: three people, most of a day, if they were lucky and the spreadsheets lined up; or a ticket to engineering, which meant days and a place in a queue. It is now thirty seconds away, for anyone with the permission to see the underlying data, in their own words, as many times as they like, at ten at night if that is when they think of it. The same assistant can then open the record in the staff app, because [the tools hand it the URL](#a-door-into-the-app-not-a-copy-of-it).

Then, **the god‑like users**. For the handful of people with group‑wide access — the directors, the group admin — the assistant is a view over the whole business at once: every organisation, every shop, every warehouse, any period, compared any way, in one question. They had never had that; no dashboard had been built wide enough. And the reason they can have it is the same reason a picker cannot: access is wired to the user's permissions, and **managers have total control over who sees what**. Grant a job position and the assistant sees that much; take it away and it goes. Nothing is configured in the AI layer; it is the same switchboard HR already operates.

Then, **the split**. Some people were afraid of it and still are — of the speed, of being wrong in front of it, of what it means for work they used to do by hand. Some embraced it on day one and have not opened a spreadsheet since. Most are somewhere in between and drifting toward the second group. We did not push; we watched the [usage](/blog/watching-how-people-actually-use-the-mcp-server) and fixed what the log told us was confusing.

And, **the bill**. There is no API cost on our side. The server exposes tools; the model runs in whatever assistant the staff member already uses — a desktop AI client, a coding agent, a chat product — on that person's own subscription. We do not pay per token, per question or per seat; we pay nothing. A company‑wide analytics capability that would have been a six‑figure project and a line on the budget every year is a route and forty‑five classes. That, as much as the thirty seconds, is the game‑changer.

It is, honestly, the closest thing to magic we have shipped. Not because the model is clever — it is — but because for the first time the distance between a question about the business and its answer is the length of the question.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Server: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Mcp/Servers/AikuServer.php">app/Mcp/Servers/AikuServer.php</a> on <a href="https://github.com/laravel/mcp">laravel/mcp</a>, mounted in <code>routes/ai.php</code> with the package's OAuth routes; per‑request logging via <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Http/Middleware/LogMcpRequest.php">LogMcpRequest</a> (user, tool, arguments, duration, error).</li>
<li>Tools: <a href="https://github.com/Inikoo-Ltd/aiku/tree/main/app/Mcp/Tools">app/Mcp/Tools/</a> — 45 classes, each a wrapper on an existing action; authorisation inside the tool with the same permission traits as the UI; entities by slug; <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Mcp/Tools/MyAccessTool.php">my‑access</a> returns what the token may reach. SQL/describe‑tables gated by a separate permission.</li>
<li>Guide: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Mcp/Resources/AikuDataGuideResource.php">AikuDataGuideResource</a> — house rules, which tool for which question, tone rules for drafted customer replies, data‑protection rules.</li>
<li>Connect: any MCP client → <code>https://&lt;staff app&gt;/mcp</code> with a personal access token; spec at <a href="https://modelcontextprotocol.io/specification">modelcontextprotocol.io</a>.</li>
<li>Why tools, not retrieval: the "answer" is a live, permission‑scoped query; the tool description is the retrieval step.</li>
</ul></aside>

## What we learned

The tools were never the hard part. The hard parts were: deciding in advance what the model may not do, making wrong entities impossible to reach by accident, and writing descriptions a model actually follows. Everything else was a week.

<aside class="tldr bottom"><strong>In one paragraph</strong>Stop pasting documents into prompts. Give the model the same tools the staff use, under the same permissions, identified so it cannot guess, with a guide on how to combine them — and watch a day's reporting become thirty seconds, with numbers people go and check and find correct.</aside>
