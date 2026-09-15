---
title: Working the help desk queue
summary: For engineers and lead engineers - how to pick up tickets, keep them moving, work on them together with collaborators, ask the reporter a question, close them with a note, and ask QA to check your fix.
date: 2026-09-15
tags: help desk, tickets, engineers
category: help-desk
audience: engineers
help_routes: grp.tickets.board
---

<aside class="tldr">
A ticket is a request for help from a colleague or a customer. Your job is to take a ticket, work on it, keep the reporter informed and close it with a short note saying what you did. You can do all of this from the ticket page, from the <b>Board</b> or straight from the <b>List</b>. Everything you write on a ticket can be read by the person who reported it, so write as if you were talking to them.
</aside>

## Where to find your work

Open <b>Tickets</b> in the left menu. There are three ways to see tickets:

- <b>Dashboard</b>: what needs attention now. New tickets nobody has taken yet, the tickets assigned to you, the tickets you are collaborating on, tickets waiting for a reply, and tickets waiting for QA.
- <b>Board</b>: every ticket as a card, in columns from left to right: <b>Todo</b>, <b>Assigned</b>, <b>In progress</b>, <b>Waiting</b> and <b>Closed</b>. It is the easiest way to see what is happening at a glance.
- <b>List</b>: every ticket as a row in a table, newest first. Use it when you want to search, filter or change several tickets quickly.

<b>Tip:</b> on the List, use <b>Mine</b> to see only your own tickets: the ones you reported, the ones assigned to you, or the ones you are collaborating on. aiku remembers this, so the List opens the same way next time.

## Looking at a ticket quickly

You do not have to open every ticket in a new page. <b>Click a card</b> on the Board, or <b>click a row</b> in the List, and the ticket opens in a window on top of the page.

In that window you can:

- read the description and look at the attached files,
- read the comments and write a reply,
- change the status, the person working on it, the collaborators, the kind, the module and the tags on the right, if the ticket is yours.

Changes are saved as soon as you make them. Close the window with the <b>×</b> in the corner or by clicking outside it.

## The life of a ticket

Every ticket has a <b>status</b> that tells everyone where it is:

- <b>Todo</b>: new, nobody has taken it yet.
- <b>Assigned</b>: someone has it on their list.
- <b>In progress</b>: someone is working on it right now.
- <b>Waiting</b>: we asked the reporter a question and are waiting for the answer.
- <b>Reporter replied</b>: the reporter answered, so it is our turn again.
- <b>Waiting for deployment</b>: the fix is finished and the ticket will close by itself once the fix is live in aiku.
- <b>Done</b>: fixed or delivered.
- <b>Cancelled</b>: closed without a fix.

## How to work a ticket

1. <b>Take it.</b> A lead engineer assigns tickets to you. You will see them under <b>Assigned to me</b> on the Dashboard.
2. <b>Start it.</b> Press <b>Start</b> so everyone can see you are working on it.
3. <b>Ask if you are stuck.</b> Press <b>Ask reporter</b>, write your question and choose how long they have to answer (see below).
4. <b>Link your fix.</b> Put the ticket number, for example <b>HELP-3131</b>, in your commit message. When the fix goes live, aiku adds a comment to the ticket saying so.
5. <b>Ask QA to check</b> when the fix is live, if the change needs checking.
6. <b>Close it.</b> Press <b>Done</b>, write a short note on what you did, and confirm.

If you need to pause, press <b>Stop, back to assigned</b>. Press <b>Resume</b> to carry on later. If a closed ticket needs more work, press <b>Reopen</b>.

## Changing tickets from the List

For tickets assigned to you, you can change things directly in the List without opening the ticket. Click the value you want to change in the row, then pick the new one.

You can change:

- <b>Status</b>: start working, ask the reporter, carry on after a reply, or close it with <b>Done</b> or <b>Cancel</b>.
- <b>Priority</b>: how urgent it is.
- <b>Assignee</b>: pass it to a colleague.
- <b>Kind</b>: bug or feature request.
- <b>Module</b>: which part of aiku it is about.

Rows for tickets assigned to other people, and tickets nobody has taken yet, cannot be changed. Lead engineers can change every row.

<b>Tip:</b> while a change is saving you will see a small spinning icon. Wait for it to finish before changing the same ticket again.

## Working on a ticket together

Some tickets need more than one person. The person the ticket is assigned to is the <b>lead</b> on it, and can add colleagues as <b>collaborators</b>.

1. Open the ticket.
2. Under <b>Collaborators</b>, press the <b>+</b> button.
3. Tick the engineers or QA colleagues you want to add. You can tick several people in a row, aiku saves them together a moment after your last click.

Each collaborator is told they were added, and the change is shown in the ticket's <b>History</b>. To remove someone, press <b>+</b> again and untick them.

What collaborators can do:

- see the ticket, even when it is confidential,
- read and write comments,
- add and remove tags,
- write internal notes,
- ask QA to check, or withdraw that request.

What stays with the lead:

- changing the status, for example starting, pausing or closing the ticket, and moving it on the Board,
- changing the priority, the kind and the module,
- passing the ticket on and choosing the collaborators.

If you pass the ticket to one of its collaborators, they become the lead and are taken off the collaborator list.

<b>Tip:</b> tickets you collaborate on are shown under <b>Collaborating on</b> on the Dashboard, and as small pictures next to the assignee on the Board.

## Asking the reporter a question

1. Press <b>Ask reporter</b>.
2. Write what you need to know, for example "Please send the order number and a screenshot of the error".
3. Choose how long they have to answer: 2 hours, 1 day, 2 days, 3 days or 14 days.
4. Press <b>Send and wait</b>.

The ticket moves to <b>Waiting</b> and the reporter gets your question. When they answer, the ticket shows <b>Reporter replied</b>. If nobody answers in time, the ticket is cancelled automatically with a note saying so.

## Closing a ticket

When you press <b>Done</b> or <b>Cancel</b>, aiku asks you for a short note before closing the ticket:

- For <b>Done</b>: what did you do? For example "Fixed the rounding in the invoice totals".
- For <b>Cancel</b>: why is it being closed? For example "Duplicate of HELP-12, following up there".

The note is added to the ticket as a comment. It is required so the reporter always knows what happened to their request, and nobody has to ask later why the ticket was closed.

### When the fix is not live yet

Sometimes your fix is finished but it only reaches aiku with the next update. You do not want to tell the reporter "fixed" before they can see it.

1. Press <b>Done</b> and write your note as usual.
2. Instead of <b>Done</b>, press <b>Set as Done on Next Deployment</b>.

The ticket moves to <b>Waiting for deployment</b> and your note is kept aside. As soon as the next aiku update is live, your note is posted and the ticket closes by itself.

## Passing a ticket to a colleague

Open the ticket, click the name of the person it is assigned to, and pick your colleague. You can only pass on tickets assigned to you. Only lead engineers can take a ticket off someone without giving it to someone else.

If you only need a hand and want to stay in charge, add your colleague as a collaborator instead.

aiku does not add a comment when a ticket changes hands. You can always see who had it and when in the ticket's <b>History</b>.

## Kind and module

<b>Kind</b> says whether a ticket is a bug or a feature request. <b>Module</b> says which part of aiku it is about. Only the person the ticket is assigned to, or a lead engineer, can change them, because they decide how the ticket is counted in reports.

A ticket that came from a customer and was escalated keeps the kind <b>Escalated customer ticket</b>. It cannot be changed, so the link back to the customer is never lost.

## Comments and files

Every comment can be read by the reporter, and by the customer on customer tickets. Write clearly and politely.

If a comment is only for the people working on the ticket, tick <b>Internal note</b> under the comment box before posting. Internal notes are shown in yellow. Every engineer and lead engineer can read them, and so can QA colleagues working on the ticket, but the reporter cannot and is not told about them. Only the assignee, the collaborators and lead engineers can write them. You can edit or delete your own comments.

Comments and <b>History</b> each have a button to show the <b>newest first</b> or the <b>oldest first</b>. aiku remembers your choice.

You can add files to a comment by pasting, dropping or pressing <b>Attach</b>:

- screenshots and pictures, PDF, Word, Excel and CSV files, up to 10 MB each,
- short videos (MP4, WebM or MOV), up to 50 MB each,
- ZIP, RAR and 7z files, up to 10 MB each, for example a folder of log files.

All files on a ticket are shown together under <b>Attachments</b>. Click one to see it without downloading, and use the arrows to go to the next file. Clicking a ZIP, RAR or 7z file shows the list of files and folders inside it, with a <b>Download</b> button. If a file cannot be shown, aiku tells you why, for example because it was deleted or you do not have permission. Use the menu next to the title to show only one type, for example only PDFs.

Engineers, QA, lead engineers and the person who reported the ticket can open its files. Other colleagues who can see the ticket get a yellow note above the files saying they cannot preview them.

## The ticket counter in the right-hand bar

The green ticket counter on the right of the screen shows how many open tickets you are working on: the ones assigned to you plus the ones you are collaborating on. Click it to see the two counted separately. It updates on its own when a ticket is given to you, when you are added as a collaborator, or when either is taken away, so you do not need to reload the page.

## Asking QA to check

When your fix is live, press <b>Ask QA to check</b>. The ticket goes to the QA team. They test it and answer <b>QA passed</b> or <b>QA failed</b> with a note. You are told either way. If it failed, fix it and press <b>Ask QA to check again</b>. Closing the ticket with <b>Done</b> is still your decision.

## Customer tickets

Tickets that start with <b>AD</b> come from customers. If one needs work from the engineers, open it and press <b>Escalate to help desk</b>. aiku creates a linked <b>HELP</b> ticket with the same details.

## Confidential tickets

A confidential ticket can only be seen by the person who raised it, the person it is assigned to, its collaborators and lead engineers.

## Using the AI assistant

Engineers, lead engineers and QA can ask the AI assistant to help with tickets. It works with your own permissions: it can find and read the tickets you can see, raise new tickets and change tickets the same way you can. It can only comment on tickets assigned to you.

## For lead engineers

As a lead engineer you can also:

- give tickets to anyone, and take a ticket off someone,
- change any ticket from the List, not only your own,
- mark a ticket <b>Confidential</b>, from the menu at the top of the ticket,
- delete a ticket,
- hide a comment: it is shown in red with <b>Lead engineers only</b>, and only lead engineers can see it until you press <b>Unhide</b>,
- change any ticket, including tickets nobody has taken yet,
- add and remove collaborators on any ticket,
- see <b>Tickets → Reports</b>: how many tickets were raised and closed, how long they took and how each engineer is doing. Use <b>Filter by</b> at the top right to see the numbers for one engineer only, or <b>All assignees</b> to see everyone again.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See my tickets:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>Assigned to me</b>.</li>
<li><b>Look at a ticket quickly:</b> <b>Tickets</b> → <b>Board</b> or <b>List</b> → click the card or row.</li>
<li><b>Change a ticket without opening it:</b> <b>Tickets</b> → <b>List</b> → click the status, priority, assignee, kind or module in the row.</li>
<li><b>Ask the reporter a question:</b> open the ticket → <b>Ask reporter</b>.</li>
<li><b>Close a ticket:</b> open the ticket → <b>Done</b> or <b>Cancel</b> → write a note.</li>
<li><b>Close when the next update is live:</b> open the ticket → <b>Done</b> → <b>Set as Done on Next Deployment</b>.</li>
<li><b>Pass a ticket on:</b> open the ticket → click the assignee → pick a colleague.</li>
<li><b>Add a collaborator:</b> open the ticket → <b>Collaborators</b> → <b>+</b> → tick your colleagues.</li>
<li><b>See tickets I help on:</b> <b>Tickets</b> → <b>Dashboard</b> → <b>Collaborating on</b>.</li>
<li><b>Ask QA:</b> open the ticket → <b>Ask QA to check</b>.</li>
<li><b>Reports (lead engineers):</b> <b>Tickets</b> → <b>Reports</b> → <b>Filter by</b> to pick one engineer.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
The <b>Engineer</b> role lets you work on and close tickets assigned to you, pass them on, add collaborators to them, change their kind and module, change them from the List and ask QA to check. On tickets where you are a collaborator you can comment, change tags and ask QA to check. You cannot change tickets assigned to someone else, or tickets nobody has taken yet. The <b>Lead engineer</b> role also lets you give out and take back any ticket, change any ticket, add collaborators to any ticket, mark tickets confidential, see reports, delete tickets and hide or show comments. Your role comes from your job position.
</aside>
