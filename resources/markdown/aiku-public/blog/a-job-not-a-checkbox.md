---
title: A job, not a checkbox
summary: Staff access in aiku is granted by job position — "dispatch clerk in the Spanish warehouse", "customer‑service supervisor for the UK shop" — not by ticking permissions. Positions expand to roles, roles to permissions, all scoped to the group, organisation, shop or warehouse they name. Why we chose that, how a permission is checked in the same place for the UI, the API and the MCP server, the "team id" that makes it all silently false if forgotten, and the things we refuse to do with an admin flag.
date: 2026-07-22
tags: permissions, security, architecture, hr
---

<aside class="tldr"><strong>TL;DR</strong>Staff access is granted by HR job position, which expands to roles and then to permissions, each scoped to a group, organisation, shop or warehouse. The same permission check runs for the UI, API and MCP server, so all three agree by construction. If the multi-tenancy "team id" context isn't set for a request, job or console command, every permission check silently returns false. Admin roles don't bypass scope, grant SQL access, expose payment data, or allow impersonating a customer.</aside>

A new picker starts in the Spanish warehouse on Monday. Nobody opens a matrix of two hundred checkboxes. HR records the person, the contract and **the job position** — *picker, warehouse ES* — and the system already knows what a picker in that warehouse may see and do. When they move to the packing bench, the position changes and the access follows. When they leave, it goes. This note is how staff authorisation works, at the level a reader needs — the principles and the shapes, not the map of the locks.

## Position → roles → permissions

Three layers, each a small vocabulary:

- A **job position** is the HR word: *dispatch clerk, stock controller, customer‑service supervisor, buyer, accounts, webmaster, group admin…* — a few dozen of them, each meaning something to a manager.
- A position expands to one or more **roles** (about sixty), which are the engineering word for "a bundle of capabilities that go together": a warehouse supervisor role, an HR clerk role, and so on.
- A role carries **permissions** (about a hundred and thirty), the atoms the code actually checks: *view this, edit that, approve the other*.

Positions and roles are **scoped**: a role is granted *for* a warehouse, a shop, an organisation or the whole group, and the permission that results carries that scope in its name. "Edit products" is never granted bare; it is granted for shop X. A supervisor in one shop is a stranger in the next unless someone says otherwise.

The group admin and system admin roles exist and are few, audited, and not used for day‑to‑day work by the people who hold them.

## Why positions

Because the alternative — per‑user permission editing — drifts. Someone is given one extra right "just for this week"; a year later nobody knows why they have it. With positions, the question "what can this person do" has the same answer as "what is their job", and the audit of access is an audit of HR records, which are already kept carefully. Bulk changes — a new screen everyone in a role should see — are a change to the role, applied to everyone in it in one action, with the sync logged.

Pseudo‑positions exist for people who are not employees in the HR sense (an external accountant, a contractor) and are granted the same way, so there is no second door.

## One check, three doors

A permission is checked in the action, not in the screen. The same action serves the web page, the API and the [MCP server](/blog/an-mcp-server-for-a-whole-business), so the three agree by construction. The UI hides what you cannot do as a courtesy; the action refuses it as a rule. Lists are scoped in the query — *where organisation_id in (the ones you may see)* — before a row is fetched, so a list never contains something you then cannot open.

## The team id that makes everything false

The permission library underneath supports multi‑tenancy through a "team" context, and we use it with the group as the team. The consequence we learned the hard way: if the team context is not set for a request or a job — a queued worker, a console command, a test — every permission check returns *false*, silently, with no error. The fix was structural: the context is set in one middleware and one job bootstrap, and a test asserts it. Silent denial is safer than silent grant, but it still produces a confusing afternoon, and it is the first thing we check when "nobody can see X" arrives.

## What the admin flag does not do

- It does not bypass scope. An organisation admin sees that organisation; the group admin sees the group; neither sees a hidden "all".
- It does not grant SQL. The raw‑query tool in the MCP server is its own permission, not implied by any admin role, and [management decides who holds it](/blog/watching-how-people-actually-use-the-mcp-server).
- It does not read customer payment details, and neither does anything else in the staff app: the payment layer stores references, not card data.
- It does not let anyone see a customer's portal session. Staff impersonation of customers does not exist; customer service sees the customer's records in the staff app, with the customer's context loaded, under their own name.

## What we would tell a team starting

Grant by job, scoped by place. Check in the action, once, for every door. Put the tenancy context where it cannot be forgotten, and test that it is there. And keep the list of people with the big roles short enough to read aloud.

<aside class="tldr bottom"><strong>In one paragraph</strong>Granting access by job position rather than by checkbox keeps the question "what can this person do" answerable by looking at HR records, not by archaeology.</aside>
