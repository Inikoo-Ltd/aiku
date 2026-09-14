---
title: When WhatsApp is not working
summary: The symptoms people actually hit — nothing arriving in the inbox, templates stuck on pending, a campaign that stopped or will not finish — with the cause and what to change.
date: 2026-09-10
tags: marketing, whatsapp, campaigns
category: marketing
series: WhatsApp
order: 3
help_routes: grp.org.shops.show.chat.whatsapp_templates
---

<aside class="tldr">
Two settings pages sit behind everything WhatsApp does — the <b>organisation's</b> Meta app and the <b>shop's</b> phone number — and most faults are a blank field on one of them. Start there. If everything stopped at once and nobody changed anything, it is almost always an expired access token. Setting them up is covered in <a href="/docs/connecting-whatsapp-to-your-shop">connecting WhatsApp to your shop</a>.
</aside>

## Nothing arrives in the inbox when I message the number

Incoming messages are either not reaching aiku, or reaching it and being turned away. Work through these in order — they are sorted by how often each turns out to be the culprit.

1. **Is the webhook still verified in Meta?** WhatsApp → Configuration. If it shows an error there, the callback address or the verify token is wrong.
2. **Is the `messages` field subscribed?** Verifying the webhook and subscribing to its fields are two separate steps in Meta, and it is easy to do the first and forget the second.
3. **Does the shop have the right Phone Number ID?** An incoming message is matched to a shop by that number. A wrong or blank one means the message arrives and goes nowhere.
4. **Does the organisation have an App Secret?** Without it every incoming message is turned away before it is read.

If all four look right, ask your developer to check the logs. They can tell causes 3 and 4 apart, which is not possible from the outside.

## Templates stay on "pending" although Meta approved them

Meta's verdict is not reaching aiku, or cannot be matched to a shop. Two things to check.

First, that **message_template_status_update** is subscribed in Meta. It is a separate tick from `messages` and it is usually the one missing.

Second, that the shop's **WABA ID** is filled in. Template verdicts are matched to a shop by business account rather than by phone number, so a shop can be receiving messages perfectly while losing every verdict. That is what makes this symptom so confusing: everything else works.

In the meantime, **Refresh** on the template asks Meta for its current status directly and unsticks it.

## "WhatsApp is not configured for this shop"

The shop has no **Phone Number ID**. Shop settings → Chat → fill in the WhatsApp Connection fields.

This appears when you try to send rather than while you are composing, which is why a campaign can look completely ready and then refuse at the last step. It is deliberate: shop settings are not part of a campaign's readiness, or changing one shop setting would un-ready every campaign in the shop at once.

## A campaign is stuck saying "Sending"

A campaign goes out in batches of 50 and only says Sent once every batch is done, so one batch never finished.

Give it time first — a large audience takes a while, and delivery reports keep arriving after the last message has gone. If it has not moved in an hour, ask your developer: there are commands to push the remaining batches through and close the campaign, and it usually means aiku's background workers have stopped rather than anything being wrong with the campaign.

## A campaign went straight to "Stopped"

When its scheduled time arrived it could not be sent, so it ended rather than sitting there pretending it was about to go out.

The campaign page says why. It will be one of: no template chosen, no recipients, or the shop's WhatsApp connection is missing. Fix that and send a new campaign — a stopped one does not resume.

## The audience is far smaller than my customer list

Usually not a fault at all.

By default a campaign goes only to customers who **opted in** to the WhatsApp newsletter, which is a small fraction of the list to begin with and grows over time. On top of that, numbers WhatsApp cannot deliver to — no country code, an email address typed into a phone field — are dropped without being counted.

If you genuinely want a wider audience, add the Contacted or Customers groups when composing, and read the warning in [sending a WhatsApp campaign](/docs/sending-a-whatsapp-campaign) first.

## Lots of messages failed with "Missing" something

The template has a personalised blank in it and those customers have no value to put in it, so they were skipped rather than sent a message with a hole in it.

Either fill in the missing field on those customer records, or use a template whose blanks every recipient can answer. There is no way to send a template with an empty blank — WhatsApp rejects it outright.

## A template does not appear in the campaign picker

It is not approved yet, or it is not in the **Marketing** category. Both are required. The category is chosen when the template is created and cannot be changed afterwards, so a Utility template has to be rewritten as a Marketing one and submitted again.

## Everything stopped at once and nobody changed anything

Nine times out of ten, an expired access token. Meta hands out a temporary 24-hour token by default and it is easy to save one during setup by mistake. Generate a permanent one for a system user and paste it into the organisation's settings under **Meta-configuration → Access Key**.

The other candidates, in order of likelihood: the Meta system user that owned the token was deleted, the number was limited by Meta after reports or blocks, or the app was switched back into development mode.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>The organisation half:</b> your organisation → <b>Settings</b> → <b>Meta-configuration</b>.</li>
<li><b>The shop half:</b> your shop → <b>Settings</b> → <b>Chat</b> → the WhatsApp Connection fields.</li>
<li><b>Unstick a template:</b> your shop → <b>Chat → WhatsApp templates</b> → open it → <b>Refresh</b>.</li>
<li><b>See why a campaign stopped:</b> open the campaign from <b>Marketing → Whatsapp Campaigns</b>; the reason is on its page.</li>
</ul>
</aside>
