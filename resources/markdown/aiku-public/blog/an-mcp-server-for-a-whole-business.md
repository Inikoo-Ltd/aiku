---
title: An MCP server for a whole business
summary: Forty read-only tools, scoped by the same permissions as the UI, that let an assistant answer "how did lavender candles do last quarter in the Spanish shop" without anyone writing SQL. What we allowed, what we refused, and why slugs instead of names.
date: 2026-08-23
tags: mcp, ai, permissions
---

aiku ships its own [MCP](https://modelcontextprotocol.io) server. Any client that speaks the protocol — a desktop assistant, an IDE, an agent framework — can connect with a personal access token and ask questions about the business. This note is about the decisions, because the code is the easy part.

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

## What we learned

The tools were never the hard part. The hard parts were: deciding in advance what the model may not do, making wrong entities impossible to reach by accident, and writing descriptions a model actually follows. Everything else was a week.
