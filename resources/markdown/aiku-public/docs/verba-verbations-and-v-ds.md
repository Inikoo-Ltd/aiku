---
title: Verbations and V·Ds, private groups and direct messages
summary: Private groups and direct messages in Verba - starting one, writing, translation, quick replies, ticks, bringing your Slack conversations, the messaging bar, and Ask CRM / Ask warehouse.
date: 2026-11-27
tags: chat, messaging, verba, crm, dispatch
category: help-desk
help_routes: grp.chat.staff.groups, grp.chat.staff.show, grp.chat.staff.index
series: Verba
order: 4
---

<aside class="tldr">
A <b>V·D</b> is a direct message, to one colleague or a few. A <b>Verbation</b> is a private group with a name. Only the people in a conversation can see it. If you got a chat called <b>CRM · </b><i>order reference</i> that you did not expect, jump to <a href="#who-receives">Who receives Ask CRM</a>.
</aside>

<h2 id="v-ds">V·Ds: direct messages</h2>

Start one with **New Verb** on the **News** or **V·Ds** tab, or **Add coworkers** at the bottom of the V·Ds list, and pick one or more names. Writing to one person makes a direct message; adding more makes a group chat. You can also click anyone's picture and press **Message**.

Type in **Write a message…** and press **Enter**. You can attach a picture, send a GIF or an emoji, reply to one message (**Reply**) and react with a quick emoji. The **More actions** menu on a message also has **Create a task from this message**.

Things that help on the warehouse floor:

- **Quick replies.** Chips like **Done**, **Help!**, **Call me**, **OK**, **Thanks** send the word with one tap. Group admins can change them in the group's **Settings → Verba → Quick replies**.
- **Automatic translation.** Each message is shown in the language you use in aiku. Tap **original** to see what was really written.

**Ticks** under your messages: one grey tick is **Sent**, two grey ticks **Received**, two blue ticks **Read**. In a group, click them to see who has read it.

The **X** at the top (**Done talking to…** or **Leave this conversation for now**) hides the chat for you only. It comes back by itself when someone writes again. Nothing is deleted.

Click the star next to a chat to keep it in **Starred** at the top of the list.

<h2 id="verbations">Verbations: private groups</h2>

Press **New Verbation**, give it a **Name** (for example "buying-team"), add **People** and press **Create**. It is made as a Slack private channel, created as you, so only the people you invite see it, in Slack and in Verba. Creating one needs your Slack account connected.

Private channels from Slack are read only in Verba for now: they say **Private channel, read only** and you reply in Slack.

A member can switch on **Show this channel's name in Verbations, so others can ask to join**. Its messages stay for members only. Channels shown that way appear to everyone else under **Other private channels**, with an **Ask to join** button.

<h2 id="slack">Bringing your Slack conversations</h2>

Your Slack direct messages, group chats and private channels do not come to Verba by themselves. You choose:

- **Your direct messages and group chats:** on the **V·Ds** or **Verbations** tab, press **Read and say yes** on the card **Bring your Slack conversations**, or switch on **Private conversations** in your **Personal settings** under **Slack**. A dialog explains exactly what Verba keeps and who can see it; read it, tick **I understand** and press **Bring my messages**. In direct messages and group chats only your own words come; the private channels you are in come whole. Where a colleague has not said yes yet, you see **Not brought to Verba yet** in place of their message.
- **A private channel, whole, for everyone in it:** open the channel in Slack and type `/invite @aiku`. It appears in Verbations in a moment, with a line saying who brought it.

Slack direct messages in Verba are read only for now: you reply in Slack.

To stop, switch **Private conversations** off in your **Personal settings** under **Slack**. The dialog tells you what happens: the direct messages Verba brought are deleted from Verba, private channels stay because they belong to all their members, and nothing changes in Slack.

<h2 id="bar">The messaging bar</h2>

Every aiku screen has a thin bar on the right. Click it to open: **Everyone online**, **Online in my organisation**, **My team** and **Messages**, with a red count for anything unread. Click a name to write to them. Click the star next to someone (**Add to my team**) to put them in **My team**; your team also shows in the [quick call button](/docs/verba-calls#quick-call).

<h2 id="ask-crm">Ask CRM and Ask warehouse</h2>

Some screens have a button that opens a chat about that exact document, with the right people already in it:

- **Delivery note** page: **Ask CRM**.
- **Order** page: **Ask warehouse** and **Ask CRM**.
- **Picking session** page: **Ask CRM**.

The chat is named after its document, for example **CRM · AFR26782**. Pressing the button again later opens the same chat. These chats are listed under **Legacy chats** at the bottom of the **V·Ds** list.

<h2 id="who-receives">Who receives Ask CRM and Ask warehouse</h2>

The people are a list each shop keeps:

1. Open the shop's **Settings** and find the **Verba** section.
2. **Ask CRM goes to** is the main list. **Ask CRM backup** is used when nobody on the main list is active right now.
3. **Ask warehouse goes to** and **Ask warehouse backup** work the same way.

The organisation's **Settings → Verba** holds the warehouse lists used when a shop has none of its own.

When the button is pressed: if someone on the main list is active, only the active ones are added; otherwise the active people on the backup list; if nobody on either list is active, everyone on both lists is added. If the shop has no lists at all, the question goes to everyone with the customer service roles for that shop (or the warehouse roles for that warehouse). So if a question reaches the wrong person, fix the list in the shop's settings.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Direct message:</b> <b>Verba → V·Ds</b> → <b>New Verb</b> or <b>Add coworkers</b> → name.</li>
<li><b>Private group:</b> <b>Verba → Verbations</b> → <b>New Verbation</b>.</li>
<li><b>Bring your Slack conversations:</b> <b>V·Ds</b> or <b>Verbations</b> → <b>Read and say yes</b>; or your avatar → <b>Personal settings</b> → <b>Slack</b> → <b>Private conversations</b>.</li>
<li><b>Ask about a document:</b> delivery note, order or picking session → <b>Ask CRM</b> / <b>Ask warehouse</b>.</li>
<li><b>Who gets those questions:</b> shop → <b>Settings → Verba</b>; organisation → <b>Settings → Verba</b> for the warehouse default.</li>
<li><b>Quick replies:</b> group <b>Settings → Verba → Quick replies</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li><b>V·Ds:</b> any aiku login.</li>
<li><b>New Verbation:</b> your Slack account connected.</li>
<li><b>Ask CRM / Ask warehouse:</b> whoever can open the delivery note, order or picking session.</li>
<li><b>Edit the Ask lists:</b> whoever can edit the shop's or organisation's settings.</li>
<li><b>Edit quick replies:</b> group admin.</li>
</ul>
</aside>
