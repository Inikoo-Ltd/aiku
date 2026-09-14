---
title: Working the help desk queue
summary: For engineers and lead engineers - pick up tickets, move them through their statuses, ask the reporter, pass work on, link your fix to the deploy, ask QA to check, and use the AI assistant within its rules.
date: 2026-09-14
tags: help desk, tickets, engineers
category: help-desk
audience: engineers
help_routes: grp.tickets.board
---

<aside class="tldr">
Engineers work tickets from the <b>Tickets</b> dashboard and <b>Board</b>. Take a ticket, press <b>Start</b>, ask the reporter when you are stuck, put the ticket reference in your commit so the deploy is linked, ask QA to check, and press <b>Done</b>. Every comment is public: the reporter, and the customer on an AD ticket, can read it. Lead engineers also hand out work, see reports, handle confidential tickets and decide which old internal notes stay hidden.
</aside>

## Your dashboard and the board

<b>Tickets → Dashboard</b> shows the <b>queue</b> of open unassigned tickets, most urgent and oldest first, the tickets assigned to you, waiting tickets due within a day, and the QA queue.

<b>Tickets → Board</b> has one column per stage: <b>Todo</b>, <b>Assigned</b>, <b>In progress</b>, <b>Waiting</b> and <b>Closed</b>, where Done and Cancelled share a column. Each column has its own period filter; closed tickets default to the last 24 hours.

## Moving a ticket

- <b>Start</b> moves it to <b>In progress</b>.
- <b>Stop, back to assigned</b> parks it; <b>Resume</b> picks it up again.
- <b>Ask reporter</b> moves it to <b>Waiting</b> (see below).
- <b>Done</b> closes it as fixed and tells the reporter; <b>Cancel</b> closes it without a fix.
- <b>Reopen</b> brings a closed ticket back.

Set <b>priority</b>, <b>kind</b> (bug or feature), <b>module</b> and <b>tags</b> as you learn more. The preset tags explain why a ticket needed no code: <b>not a bug</b>, <b>lack of training</b>, <b>not enough info</b>, <b>duplicate</b>, <b>user error</b>, <b>data fix</b>, <b>wont fix</b>.

## Asking the reporter

<b>Ask reporter</b> sends your question and puts the ticket in <b>Waiting</b>. Choose how long they have: 2 hours, 1 day, 2 days, 3 days or 14 days. Without a choice, staff get 72 hours and customers 14 days. If nobody answers, the ticket is cancelled automatically with a "No reply for … days" comment. Any reply from the reporter moves it back to <b>Todo</b>.

## Assigning and passing on

Unassigned tickets are handed out by lead engineers. Once a ticket is yours you can pass it to a colleague, but you cannot unassign it or take one that is not yours. Every change leaves a comment such as "Assigned to …" or "Passed from … to …", and the new assignee joins the ticket's <b>Staff chat</b>.

## Comments

Every comment is public. Write for the reporter and, on AD tickets, for the customer. Comments are mirrored to the ticket's Slack thread and replies in the thread come back as comments. You can edit or delete your own comments.

Old internal notes from before comments became public stay hidden from everyone except lead engineers.

## Linking your fix to the deploy

Put the ticket reference in the commit subject, for example <b>HELP-3131</b> or <b>AD-45</b>. When that commit reaches production aiku adds a "Deployed to production (version): hash subject" comment and lists the commit on the ticket, so the reporter and QA can see when the fix went live.

## Asking QA to check

When the fix is live, press <b>Ask QA to check</b>. QA gets the ticket in their queue and gives a verdict: <b>QA passed</b>, or <b>QA failed</b> with a note on what is still wrong. You are notified either way. After a fail, fix it and press <b>Ask QA to check again</b>; <b>Withdraw QA request</b> takes it back. Marking the ticket <b>Done</b> is still your decision.

## Customer tickets and escalation

AD tickets come from customers in their shop's support pages. If one needs engineering work, press <b>Escalate to help desk</b>: it creates a linked HELP ticket of kind escalation, with the same subject, description and priority.

## Confidential tickets

A confidential ticket is visible only to the person who raised it and to lead engineers. Being assigned does not give you access.

## Using the AI assistant

The assistant's ticket tools are available only to engineers, lead engineers and QA. It acts as you, with your permissions:

- It can list and read the tickets you can see, and raise new tickets.
- It can change a ticket the same way you can in aiku.
- It can comment only on a ticket that is assigned, and only if it is assigned to you. Assign the ticket first.

## For lead engineers

- <b>Tickets → Reports</b>: tickets created and done, median resolve time, oldest open ticket, customer ratings and a table per assignee, over 7, 30 or 90 days.
- Assign any ticket to anyone.
- Tick <b>Confidential</b> on a ticket that must stay private.
- Delete a ticket; it removes the ticket and its comments.
- Every comment has <b>Hide</b> or <b>Make public</b>. Old internal notes start hidden; read them and make public the ones that are fine to share. Hide any comment that should not be visible.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Find work:</b> <b>Tickets</b> → <b>Dashboard</b>, or <b>Tickets</b> → <b>Board</b>.</li>
<li><b>Start, ask the reporter, finish:</b> open the ticket → <b>Start</b>, <b>Ask reporter</b>, <b>Done</b>.</li>
<li><b>Pass a ticket on:</b> open your ticket → change the assignee.</li>
<li><b>Ask QA:</b> open the ticket → <b>Ask QA to check</b>.</li>
<li><b>Escalate a customer ticket:</b> open the AD ticket → <b>Escalate to help desk</b>.</li>
<li><b>Reports (lead engineers):</b> <b>Tickets</b> → <b>Reports</b>.</li>
<li><b>Hide or publish a comment (lead engineers):</b> open the ticket → <b>Hide</b> or <b>Make public</b> on the comment.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
The <b>Engineer</b> role lets you work and resolve tickets, pass your own tickets on and ask QA to check. The <b>Lead engineer</b> role adds assigning any ticket, confidential tickets, reports, deleting tickets and hiding or publishing comments. Roles come from your job position.
</aside>
