---
title: Sending a WhatsApp campaign
summary: Pick an approved template, choose who receives it, preview it as the customer will see it, and send now or schedule — plus what the delivery figures do and do not tell you.
date: 2026-09-10
tags: marketing, whatsapp, campaigns
category: marketing
series: WhatsApp
order: 2
help_routes: grp.org.shops.show.marketing.whatsapp_campaigns
---

<aside class="tldr">
A WhatsApp campaign sends one message to many people from your shop's own number. Unlike an email, you cannot simply write it: WhatsApp only allows a business to start a conversation using a <b>template Meta approved in advance</b>, and for campaigns that template must be in the <b>Marketing</b> category. You create the campaign, pick the template, choose the audience, preview it, then send now or schedule it. Replies come back to the chat inbox, so have somebody watching after a send.
</aside>

## Where campaigns live

Inside a shop, **Marketing → Whatsapp Campaigns** holds the list. Each row shows the campaign's state with an icon and colour: In process, Ready, Scheduled, Sending, Sent, Cancelled or Stopped. The **Subscribers** tab on the same page shows who has opted in to receive them.

## Why you cannot just write a message

WhatsApp does not let a business open a conversation with free text. Everything a campaign sends has to be a template Meta reviewed and approved beforehand. You write the template, submit it, and Meta usually answers within minutes — sometimes it takes until the next day.

Two rules catch people out:

- The template must be in the **Marketing** category to appear in the campaign picker. Utility and Authentication templates are fine in conversations but will not show up here, and the category cannot be changed after the template is created.
- Once approved, the wording is fixed. Changing the message means a new template and another review.

Templates are written under **Chat → WhatsApp templates**.

## Creating a campaign

Press **Campaign** at the top of the list. aiku creates it immediately with a default name you can change, and takes you to the **workshop**. A new campaign starts **In process**.

The workshop is two decisions on one screen: which template, and who receives it. The journey strip at the top shows where you are — **Compose**, then **Preview & send**.

## Choosing who receives it

Three groups, which you can combine:

| Group | Who that means |
| --- | --- |
| **Subscribers** *(on by default)* | Customers who opted in to the WhatsApp newsletter |
| **Contacted** | Anyone who has ever messaged the shop on WhatsApp, including people who are not customers |
| **Customers** | Customers with a usable phone number, whether they opted in or not |

On top of the groups you can add the usual customer filters, and the recipient count updates as you change them.

**Think hard before using Customers.** Having somebody's phone number is not the same as their permission to market to them. Subscribers is the default for a reason: in most places messaging people who never opted in is both a legal problem and the quickest way to have your number reported, and enough reports will get it limited by Meta.

### Why the audience looks so small

Two filters apply whether you ask for them or not.

**Opt-in.** With the default group, only customers who ticked the WhatsApp newsletter box count. That will be a small fraction of your customer list at first and grows as people register and check out.

**Deliverable numbers.** A number WhatsApp cannot route is dropped before it is ever tried: no country code, an email address typed into a phone field, a landline recorded as a mobile. These are not attempted and not counted.

So the recipient count sits well below the number of customers. That is aiku being honest about who it can actually reach, not a fault.

## Personalising the message

A template can carry blanks — the customer's first name, say — filled in per person. You map each blank to a customer field when you compose, and the preview shows real values so you can see what will actually land.

**If a value is missing for somebody, they are skipped rather than sent a broken message.** WhatsApp rejects a message with an empty blank, and "Hi ," would be worse than nothing. Those contacts appear afterwards as failed, saying which value was missing — usually a sign that a customer record is incomplete rather than anything wrong with the campaign.

## Preview, then send

**Preview & send** shows the message as the customer will see it, with real values filled in, next to the audience count. This is the last screen before anything leaves.

From here there are two ways on:

- **Send now** starts it immediately. The campaign moves to **Sending** and aiku works through the recipients in the background in batches of 50, rather than all at once. A large audience takes a while, and the page updates as it goes.
- **Schedule** opens a date and time picker. The campaign moves to **Scheduled**, and aiku checks every minute for campaigns whose time has come.

You can edit a campaign freely until it is scheduled or sent. Once it is scheduled the content is frozen — press **Cancel Schedule** to pull it back and edit it again. This is deliberate: the audience you chose is the audience it goes to.

A scheduled campaign is re-checked at the moment it fires, not only when you scheduled it. If something changed in between — the shop's WhatsApp connection was removed, the template was deleted — the campaign **stops** instead of sending, and its page says why. It does not sit there looking as though it is about to go out.

### What the states mean

| State | What it means for you |
| --- | --- |
| In process | Being written. Nothing has gone anywhere. |
| Ready | Has a template and an audience. Ready to send or schedule. |
| Scheduled | Waiting for its time. Cancel the schedule to edit it again. |
| Sending | Going out now. |
| Sent | Every batch has gone. |
| Cancelled | The schedule was cancelled before it fired. |
| Stopped | It ended without sending. The campaign page says why. |

## Reading the figures

Four numbers, which come from WhatsApp itself as it delivers:

- **Sent** — aiku handed the message to WhatsApp.
- **Delivered** — it reached the customer's phone.
- **Read** — they opened it. The blue ticks. Customers can switch read receipts off, so treat this as a floor rather than the truth.
- **Failed** — it did not go, either because WhatsApp refused it or because a personalisation value was missing and the person was skipped.

**Clicked is always 0.** Click tracking through WhatsApp does not exist in aiku yet. The box is there and it will stay at zero — that is not a fault, and not evidence that nobody clicked.

Delivery reports arrive over minutes and sometimes longer, so the numbers keep moving after a campaign says Sent. Come back to it rather than judging a campaign the moment it finishes.

## What a campaign cannot tell you

- **Whether anyone clicked a link** — see above.
- **Whether it sold anything.** WhatsApp campaigns are not yet joined up with marketing attribution, so revenue is not credited to them the way it is for a newsletter.
- **Why a number failed**, beyond WhatsApp's own reason, which is sometimes only "invalid".
- **Anything about the people you never reached.** Contacts filtered out for having no opt-in or an unusable number are simply absent from the figures.

## Rules of thumb

- Send to Subscribers unless you have a specific reason you would be comfortable defending.
- Keep the first campaign from a new number small. Meta watches new numbers closely, and a burst of blocks or reports early on can get one limited.
- Expect replies. A campaign lands in the same conversation the customer would use to answer, so somebody should be watching the inbox for a few hours afterwards.
- High Delivered with low Read is normal. High Failed is worth looking into — start with [when WhatsApp is not working](/docs/when-whatsapp-is-not-working).
- Do not compare Read here with Opened on an email campaign. They measure different things by different means.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See or start a campaign:</b> your shop → <b>Marketing → Whatsapp Campaigns</b> → press <b>Campaign</b> to create one.</li>
<li><b>Choose the template and audience:</b> the workshop screen you land on — pick a template, then set the groups and filters and watch the recipient count.</li>
<li><b>Send it:</b> <b>Preview &amp; send</b> → <b>Send now</b>, or <b>Schedule</b> for a date and time. While scheduled, <b>Cancel Schedule</b> pulls it back to editable.</li>
<li><b>Write a template:</b> your shop → <b>Chat → WhatsApp templates</b>. It must be Marketing category and approved before a campaign can use it.</li>
<li><b>See who has opted in:</b> the <b>Subscribers</b> tab on the campaigns page.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Creating and sending campaigns needs marketing edit rights on the shop. With view rights you can read a campaign and its figures but the send and schedule buttons will not be there.</li>
</ul>
</aside>
