---
title: Sending a mailshot
summary: Build a marketing email, choose who receives it, design it in the email workshop, and take it from draft through to sent — with a look at what "publish" and "send" actually trigger.
date: 2026-09-01
tags: marketing, mailshots, email
category: marketing
help_routes: grp.org.shops.show.marketing.mailshots, grp.org.shops.show.marketing.newsletters
---

<aside class="tldr">
A <em>mailshot</em> is a one-off marketing email sent from a shop to a list of customers you choose. You create it, pick the recipients, design the email in the workshop, and only then send it — either straight away or at a scheduled time. Publishing the design and sending the email are two separate steps: publishing finishes the design, sending is what actually puts it in customers' inboxes.
</aside>

## Where mailshots live

Inside a shop, the **Marketing** area keeps two separate lists: **Mailshots**, for one-off marketing sends, and **Newsletters**, for the regular newsletter type. Both work the same way underneath — this article uses "mailshot" throughout, but everything applies to newsletters too.

Each row in the list shows the mailshot's state, with an icon and colour: In process, Ready, Scheduled, Sending, Sent, Cancelled or Stopped.

## Creating a mailshot

Press **Mailshot** at the top of the Mailshots list (or **New Newsletter** on the Newsletters list). aiku creates the mailshot straight away, gives it a subject like "Mailshot 1 Sep 2026" if you have not typed one, and takes you to the **recipients** screen. A brand-new mailshot starts in the **In process** state.

## Choosing recipients

The recipients screen lets you filter who the mailshot goes to. By default a new mailshot is set to reach **all customers**, but you can narrow it down instead — by family, by interest, by location (country, postcode, or a radius around a point), by department or sub-department, by order value, by items currently sitting in a basket, by past order collections, by showroom orders, by gold reward status, or to customers who registered but never ordered. As you adjust the filter, the screen shows an estimated recipient count so you can see the audience size before you commit.

From here, press **Compose email** to move on to designing the email itself.

## The email workshop

The workshop is where you design the actual email — subject line, layout and content — using a drag-and-drop builder. You can start from a template with **Choose Template**, or send a test copy to yourself before it goes anywhere near a customer.

The workshop carries a clear warning: you must press the **SAVE** button inside the editor to publish your email, otherwise it cannot be sent. In other words, changes you make in the builder sit as a draft until you explicitly save/publish them — a mailshot with unpublished design changes is not ready to go.

Publishing does two things: it commits your design as the live version of the email, and it moves the mailshot from **In process** to **Ready**. It does **not** email anyone — nothing goes to a customer at this point.

## From Ready to Sent

Once a mailshot is Ready, the show page offers two ways to move it on:

- **Send now** — asks you to confirm ("this action will send an email to all customers"), then starts sending immediately: the mailshot moves to **Sending**, and aiku works through the recipient list in the background, dispatching emails in batches rather than all at once.
- **Scheduled** — opens a date and time picker (with a timezone selector) so you can pick when the email should go out. Once confirmed, the mailshot moves to **Scheduled** and a background process checks periodically for mailshots whose scheduled time has arrived, then starts sending them the same way "Send now" does.

While a mailshot is Scheduled but hasn't started sending, you can press **Cancel Schedule** to pull it back to Ready — the scheduled time is cleared and nothing is sent.

While a mailshot is Sending, you can press **Stop** to halt it — any email batches still queued are stopped, though anything already sent has already gone. A stopped mailshot can later be **Resumed**, which picks up sending again from where it left off.

Once every batch has gone out, the mailshot moves to **Sent**. A sent mailshot can still be renamed, and — if it hasn't already been turned into a webpage — you can convert it to one from the show page.

## Reading the mailshot's own page

Once a mailshot exists, its page has three tabs: **Showcase** (an overview), **Recipients** (showing how many are on the list), and **Dispatched Emails** (showing how many have actually gone out). These counts update as sending progresses, so you can watch a send work through its list in real time.

## Deleting a mailshot

Deleting is available before a mailshot has gone out, and the confirmation is blunt about it: this action cannot be undone and permanently deletes the mailshot.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See or start a mailshot:</b> your shop → <b>Marketing → Mailshots</b> (or <b>Marketing → Newsletters</b> for the newsletter type) → press <b>Mailshot</b> / <b>New Newsletter</b> to create one.</li>
<li><b>Choose recipients:</b> on a new mailshot you land on its <b>Recipients</b> screen automatically; adjust the filters, check the estimated count, then press <b>Compose email</b>.</li>
<li><b>Design the email:</b> the workshop screen — build your layout, use <b>Choose Template</b> if you want a starting point, and press <b>SAVE</b> in the editor to publish the design.</li>
<li><b>Send it:</b> on the mailshot's page, press <b>Send now</b> to send immediately, or <b>Scheduled</b> to pick a date and time. While scheduled, <b>Cancel Schedule</b> pulls it back; while sending, <b>Stop</b> halts it and it can later be <b>Resumed</b>.</li>
</ul>
</aside>
