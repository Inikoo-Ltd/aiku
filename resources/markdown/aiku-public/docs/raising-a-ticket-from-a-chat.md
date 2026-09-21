---
title: Raising a ticket from a chat
summary: Turn a customer conversation into a ticket without retyping it, and hold the chat open until the work is done.
date: 2026-09-21
tags: chat, tickets, help desk
category: crm
---

<aside class="tldr">
When a conversation needs work that outlasts it - a bug, a missing order, something to write down - raise a <b>ticket</b> from the chat itself. The ticket carries the customer, the shop and the conversation with it, so nobody has to go looking for the story later. Tick <b>Mark as blocked</b> and the chat cannot be closed until that ticket is <b>Done</b> or <b>Cancelled</b>, which is how a promise to a customer stops being forgotten at the end of a shift.
</aside>

## Raising it

Open the conversation in <b>Chat</b>, then the <b>&#8942;</b> menu at the top right of the thread, beside <b>Ignore</b> and the customer button. Choose <b>Create Ticket</b>.

The chat belongs to whoever is holding it, and so does this. If the conversation is assigned to a colleague the option is greyed out and tells you who is handling it: take the chat over first, or ask a supervisor. Supervisors and organisation admins keep the option on any conversation, so a chat left behind by somebody who has gone home can still be dealt with.

## Filling it in

<b>Subject</b> is one line saying what is wrong. It is what everybody sees in the ticket lists, so write the problem rather than the greeting: "Tracking missing on order 57316", not "Customer question".

<b>Details</b> is what the customer reported, in their words. Markdown works, so <b>**bold**</b>, lists and links all come out as you would expect. Paste a screenshot straight into the box or drop files on it - up to five images or documents.

<b>Priority</b> stays Normal unless the customer is blocked or money is at stake. <b>Kind</b> is Bug, Documentation or Data integrity; leave it unset if you are not sure, an engineer can set it later. Nothing on this form is final - it can all be changed on the ticket afterwards.

The line at the bottom tells you the ticket will be raised as a <b>Customer support</b> ticket, which means it carries a CUS reference and stays with customer service.

## Mark as blocked

Ticked, this conversation cannot be closed until the ticket is resolved or cancelled. Ending the chat is refused, and the refusal names the ticket that is holding it.

It holds only us. The customer can still close the conversation from their side, and a chat that goes quiet is still closed automatically - the point is to stop <em>us</em> filing away a conversation whose work is still outstanding.

Choosing the <b>Bug</b> kind ticks the box for you, because a bug is usually the case where the customer is left waiting on us. It is a suggestion, not a rule: untick it if this bug report is a note for later rather than something that customer is waiting on.

<b>When to tick it.</b> Tick it when the customer is owed an answer that depends on the ticket. Leave it unticked when the ticket is our own housekeeping - a documentation note, a tidy-up - and the customer has already been served. A chat held open for housekeeping is a chat nobody ever closes.

## Following it afterwards

The thread's header counts what is still open on the conversation. Click that count and the side panel opens on <b>Tickets</b>, listing every ticket raised from this chat with its reference, status, kind and date. Click a row to read the ticket without leaving the conversation.

When one of them is blocking, the header button turns amber and carries a padlock, and so does that ticket's row in the list. Hovering the padlock says why. The button disappears once everything raised from the conversation is settled.

To clear a block, finish the work and set the ticket to <b>Done</b>, or <b>Cancel</b> it if it turned out to be nothing. The hold lifts immediately; there is nothing else to undo. If several tickets are blocking, all of them have to be settled.

## What lands on the ticket

The ticket keeps the conversation as its source, so opening it shows the channel, the contact and a link back to the chat. The customer and shop come across automatically, and you are recorded as the reporter. If the bug needs an engineer urgently, mention them by name in a comment - the ticket says who to mention and how.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Raise it:</b> <b>Chat</b> &rarr; open the conversation &rarr; <b>&#8942;</b> &rarr; <b>Create Ticket</b>.</li>
<li><b>Hold the chat open:</b> tick <b>Mark as blocked</b> before pressing <b>Create</b>.</li>
<li><b>See what is outstanding:</b> the count button in the thread's header &rarr; <b>Tickets</b> in the side panel.</li>
<li><b>Release the hold:</b> open the ticket &rarr; <b>Done</b>, or <b>Cancel</b>.</li>
</ul>
</aside>
