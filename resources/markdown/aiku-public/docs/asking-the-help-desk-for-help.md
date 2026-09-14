---
title: Asking the help desk for help
summary: Report a bug or ask for a feature from aiku or Slack, follow what happens to it, answer the engineers' questions in time, and rate the fix when it is done.
date: 2026-09-14
tags: help desk, tickets, bugs
category: help-desk
help_routes: grp.tickets
---

<aside class="tldr">
When something in aiku is broken, or you need something it does not do yet, raise a <b>ticket</b>. It lands in the help desk queue, an engineer picks it up, and you follow it on the <b>Tickets</b> page until it is <b>Done</b>. A good ticket says what happens, links the page where it happens and has a screenshot. Everything written on a ticket is visible to the people working on it, so write it as you would say it to them.
</aside>

## Where tickets live

Open <b>Tickets</b> in the left menu. The <b>Dashboard</b> shows <b>Mine</b>, the tickets you raised that are still open, and the ones closed recently. <b>List</b> shows every ticket you can see, with filters for the ones you reported, their status and type.

## Raising a ticket in aiku

The fastest way is the red <b>Bug</b> button on every page, or <b>Alt+Shift+B</b>. It fills in the page you are on for you. You can also press <b>New ticket</b> on the Tickets pages.

- <b>Subject</b>: one line that says what is wrong or what you need. This is the only required field.
- <b>Details</b>: what you did, what you expected and what happened instead. For a feature, what you need and why.
- <b>Page where it happens</b>: the link to the page.
- <b>Kind</b>: <b>Bug</b> when something is broken, <b>Feature request</b> when you need something new.
- <b>Module</b> and <b>Priority</b>: pick them if you know; leave the priority at normal unless work is stopped.
- Screenshots: paste them or drop them into the details box, up to five.

## Raising a ticket from Slack

In Slack you can use the <b>Raise ticket</b> shortcut on any message, or type <b>/ticket</b>. Both open a short form with the same fields. <b>/ticket</b> followed by text creates the ticket straight away: the first line becomes the subject and the rest the details. You can also react to a message with the ticket emoji to turn it into a ticket.

If the ticket has no link and no screenshot, the help desk replies in the thread asking for them. Tickets without them are much harder to fix.

Every ticket gets a thread in Slack. Replies in that thread are added to the ticket as comments, and comments written in aiku appear in the thread.

## Following your ticket

A ticket moves through these statuses:

- <b>Todo</b>: waiting for an engineer.
- <b>Assigned</b>: an engineer has it on their list.
- <b>In progress</b>: someone is working on it.
- <b>Waiting</b>: the engineer needs an answer from you.
- <b>Done</b>: fixed or delivered.
- <b>Cancelled</b>: closed without a fix.

You are told when an engineer asks you something, when your ticket is done, and when someone comments. Comments show up in the ticket's badge and, depending on your settings, by email, Slack or both.

## When the engineer asks you something

If the engineer needs more information, the ticket goes to <b>Waiting</b> and you get their question. Reply with a comment on the ticket or in its Slack thread. By default you have <b>72 hours</b>; if nobody replies in time the ticket is cancelled with the note "No reply for … days".

Replying to a waiting or cancelled ticket moves it back to <b>Todo</b>, so a late answer is never lost.

## Closing and rating

If the problem went away or you no longer need the feature, you can cancel your own ticket. When a ticket is closed you are asked <b>How did we do?</b>: give it stars and, if you like, a comment, then press <b>Send rating</b>. You can rate each ticket once.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Report a bug on the page you are on:</b> the red <b>Bug</b> button, or <b>Alt+Shift+B</b>.</li>
<li><b>Raise a ticket from the menu:</b> <b>Tickets</b> → <b>New ticket</b>.</li>
<li><b>See your open tickets:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>Mine</b>.</li>
<li><b>Answer an engineer's question:</b> open the ticket → write a comment, or reply in its Slack thread.</li>
<li><b>Rate a closed ticket:</b> open the ticket → <b>How did we do?</b> → <b>Send rating</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
Anyone who can log in to aiku can raise tickets, comment on them and follow them. You can change the status of your own ticket but not its priority, module, tags or assignee; those belong to the help desk. Confidential tickets are only visible to the person who raised them and to lead engineers.
</aside>
