---
title: Checking tickets as QA
summary: For QA - find the tickets waiting for a check, test the fix on the live page, give a clear pass or fail, and know what happens next.
date: 2026-09-14
tags: help desk, tickets, qa
category: help-desk
audience: qa
---

<aside class="tldr">
When an engineer's fix is live they press <b>Ask QA to check</b>, and the ticket appears in your <b>QA queue</b>. Open it, check the fix where the reporter had the problem, and give a verdict: <b>Pass</b> with a note on what you checked, or <b>Fail</b> with a note on what is still wrong. The engineer is told straight away. You check; the engineer decides when the ticket is <b>Done</b>.
</aside>

## Finding tickets to check

Open <b>Tickets → Dashboard</b>. The <b>QA queue</b> lists every ticket with <b>QA check requested</b>, oldest request first.

## What to check

- Read the subject, details and comments so you know what the reporter saw and what was changed.
- Open the <b>page where it happens</b> from the ticket and repeat what the reporter did.
- Look for the "Deployed to production (version) …" comment. If there is none, the fix may not be live yet.
- Check the case from the ticket, then the obvious cases around it: another shop, another customer, an empty value.

## Giving the verdict

- <b>Pass</b>: write what you checked, then confirm. The ticket shows <b>QA passed</b>.
- <b>Fail</b>: write what is still wrong and how to see it. <b>Fail</b> stays disabled until you add the note. The ticket shows <b>QA failed</b>.

Each verdict adds a comment to the ticket ("QA passed", or "QA failed:" with your note), posts to the ticket's Slack thread and notifies the assignee. After a fail the engineer fixes it and asks again, and the ticket returns to your queue.

Comments are public, so the reporter can read your note too. Keep it factual.

## What QA does not do

QA does not request checks, change a ticket's status or close tickets. That stays with the engineer, who marks the ticket <b>Done</b> once they are happy. You can comment on tickets and raise new tickets when you find a new problem while testing.

## Using the AI assistant

The assistant's ticket tools are available to QA. It can list and read the tickets you can see, and raise new tickets for you. It can only comment on a ticket assigned to you, so give your verdicts in aiku.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See what needs checking:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>QA queue</b>.</li>
<li><b>Open the page with the problem:</b> open the ticket → its <b>page where it happens</b> link.</li>
<li><b>Give a verdict:</b> open the ticket → <b>QA passed</b> or <b>QA failed</b> → write the note → <b>Pass</b> or <b>Fail</b>.</li>
<li><b>Report a new problem found while testing:</b> the red <b>Bug</b> button, or <b>Tickets</b> → <b>New ticket</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
The <b>QA</b> role lets you see the QA queue and give verdicts. Lead engineers can give verdicts as well. Roles come from your job position.
</aside>
