---
title: Asking the help desk for help
summary: Report a bug or ask for a feature from aiku or Slack, follow what happens to it, answer the engineers' questions in time, and rate the fix when it is done.
date: 2026-09-15
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
- Screenshots and files: paste them, drop them into the details box or press <b>Attach</b>. You can add up to five at a time: pictures, PDF, Word, Excel, CSV and ZIP files up to 10 MB each, and short videos up to 50 MB. A short screen recording showing the problem helps a lot.

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
- <b>Reporter replied</b>: you answered and it is the engineer's turn again.
- <b>Waiting for deployment</b>: the fix is finished and will be in aiku with the next update. The ticket closes by itself then.
- <b>Done</b>: fixed or delivered.
- <b>Cancelled</b>: closed without a fix.

You are told when an engineer asks you something, when your ticket is done, and when someone comments. Comments show up in the ticket's badge and, depending on your settings, by email, in Slack, or as a browser notification on your computer or phone. See [Getting notifications on your computer and phone](/docs/getting-notifications-on-your-computer-and-phone).

## When the engineer asks you something

If the engineer needs more information, the ticket goes to <b>Waiting</b> and you get their question. Reply with a comment on the ticket or in its Slack thread. By default you have <b>72 hours</b>; if nobody replies in time the ticket is cancelled with the note "No reply for … days".

Replying to a waiting or cancelled ticket brings it back to the engineer, so a late answer is never lost.

## Files on your ticket

All the files on your ticket are shown together under <b>Attachments</b>. Click one to look at it without downloading it. ZIP files cannot be previewed, so clicking one downloads it. You can also choose to see the comments newest first or oldest first, and aiku remembers your choice.

## Closing and rating

If the problem went away or you no longer need the feature, you can cancel your own ticket. aiku asks you for a short note first, so the engineer knows why it is no longer needed. When an engineer closes your ticket, they leave a note too, telling you what they did. When a ticket is closed you are asked <b>How did we do?</b>: give it stars and, if you like, a comment, then press <b>Send rating</b>. You can rate each ticket once.

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
Anyone who can log in to aiku can raise tickets, comment on them and follow them. You can change the status of your own ticket but not its priority, module, tags or assignee; those belong to the help desk. Sometimes more than one engineer works on your ticket: the engineer it is assigned to leads, and can add colleagues as collaborators to help. Confidential tickets are only visible to the person who raised them, the engineer working on them, their collaborators and lead engineers.
</aside>
