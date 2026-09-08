---
title: Talking to colleagues in staff chat
summary: The messaging bar on the right of every aiku screen - message a coworker, ask CRM or the warehouse about a specific order or delivery note in one tap, and decide who receives those questions.
date: 2026-09-02
tags: crm, dispatch, hr, chat, messaging
category: crm
help_routes: grp.chat.staff
---

<aside class="tldr">
Staff chat is for people who work in aiku talking to each other. It is completely separate from the customer chat on your website: customers never see it and it never reaches them. Warehouse staff use it from tablets to reach customer service without leaving the packing bench, and customer service use it to answer them. If you got a message titled <b>CRM · </b><i>order reference</i> that you did not expect, jump to <a href="#who-receives">Who receives "Ask CRM"</a>: that is a setting your shop controls.
</aside>

## Where it lives

Every screen in aiku has a thin **messaging bar** on the right. Click it to expand. It shows who is **online now**, your **team**, and your open **messages**, with a red count for anything unread. On a phone or tablet a conversation opens as a full-screen sheet with big buttons; on a desktop it opens as a small window at the bottom of the page, and **Open full view** takes you to a full-page version with the conversation list on the left.

The bar also carries the **Customer chats** entry at the bottom for people who are set up as chat agents. That is the website chat described in [Talking to customers in Chat](/docs/customer-chat), a different thing.

## Messaging a coworker

Open the bar, find the person under **Everyone online** or **Find a coworker…**, and click their name. A coworker is anyone who works in an organisation you also have access to. Type and press Enter. Everything you would expect from a messenger is there: paste a screenshot or attach an image, reply to one particular message, react with an emoji, send a GIF, and mention someone with **@** so they get an accent badge on the message.

Two things are aiku-specific:

- **Quick replies.** Above the composer sit chips such as **Done**, **Help!**, **Call me**, **OK**, **Thanks**. One tap sends the word. They exist because a packer with gloves on a tablet should not have to type. Your group can change the list.
- **Automatic translation.** Every message is translated into the language each participant uses in aiku. You read it in yours, they read it in theirs, and either side can tap **original** to see what was really written. Nobody needs to write in English.

Your **My team** list is the handful of people you talk to most. Add them with **Add to my team** and they sit at the top of the bar with their online dot. Online means the person has the app open and has done something in the last quarter of an hour; amber means idle.

## Asking CRM or the warehouse about an order

This is what most staff chat traffic is. Some screens have a button that opens a conversation about that exact document, with the right people already in it:

- **Delivery note** page: **Ask CRM**.
- **Order** page: **Ask warehouse** and **Ask CRM**.
- **Picking session** page: **Ask CRM**.
- When a carrier refuses to make a shipping label, the shipment box shows the carrier's own error and an **Ask CRM about this** button. Tapping it posts the shipper and the error into the delivery note's CRM conversation for you, no typing.

The conversation is named after its document, for example **CRM · AFR26782**, so everyone can see what it is about before opening it. Pressing the button again later returns to the same conversation rather than starting a new one.

Nobody but you sees a fresh conversation until the first message is sent, so opening one by accident bothers no one. If there is nobody on the other side (an empty recipient list with no one holding the role), aiku tells you so instead of sending the message into an empty room.

<h2 id="who-receives">Who receives "Ask CRM" and "Ask warehouse"</h2>

The recipients are a list you curate, not everyone who has ever been a customer service clerk. Each shop has its own lists, because a shop's customers are looked after by that shop's team:

1. Open the shop's **Settings** and find the **Staff chat** section.
2. **Ask CRM goes to** is the primary list. **Ask CRM backup** is used when nobody on the primary list is active right now.
3. **Ask warehouse goes to** and **Ask warehouse backup** work the same way for questions going the other direction.

The organisation's **Settings** also have a **Staff chat** section, but only for the warehouse lists: they are the default when a shop has none of its own. Ask CRM is always per shop.

The rules aiku follows when the button is pressed:

- If someone on the primary list is active, only the active ones are added.
- Otherwise, the active people on the backup list.
- If nobody on either list is active, everyone on both lists is added, so the question waits for the first person back.
- If the shop has no lists at all, the question goes to everyone holding the customer service roles for that shop (or the warehouse roles for that warehouse).

So if a warehouse question reaches someone who should not get it, the fix is in the shop's Settings, not in the chat. Take them off the list, or give the shop a list if it had none.

## Closing and housekeeping

Closing a conversation with **X** archives it for you only; it comes back by itself when someone writes in it again. You can also **Leave this conversation for now** from a group conversation. Nothing is deleted.

Staff chats are work conversations, not private ones. HR and organisation admins can read them from **Human Resources → Staff chat**, which lists conversations with their messages. The sysadmin page shows counts and who chats with whom, never the text.

## Making it yours

In **My profile** you can set a **Chat nickname**, which replaces your name in the bar, and pick a **chat theme** for the bar's colours. Group admins can edit the quick replies in the group's **Settings → Staff chat**, one per line.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Message someone:</b> messaging bar on the right → <b>Everyone online</b> or <b>Find a coworker…</b> → their name → type → Enter.</li>
<li><b>Ask about a document:</b> open the delivery note, order or picking session → <b>Ask CRM</b> / <b>Ask warehouse</b>.</li>
<li><b>Choose who gets those questions:</b> your organisation → your shop → <b>Settings → Staff chat</b> → <b>Ask CRM goes to</b>, <b>Ask CRM backup</b>, <b>Ask warehouse goes to</b>, <b>Ask warehouse backup</b>. Organisation → <b>Settings → Staff chat</b> holds the warehouse default.</li>
<li><b>Change the quick replies:</b> group <b>Settings → Staff chat → Quick replies</b>.</li>
<li><b>Nickname and colours:</b> your avatar → <b>My profile</b> → <b>Chat nickname</b>; <b>Settings</b> → chat theme.</li>
<li><b>Read staff conversations as HR:</b> organisation → <b>Human Resources → Staff chat</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li><b>Send messages:</b> any aiku login. You can reach anyone who works in an organisation you also have access to.</li>
<li><b>Ask CRM / Ask warehouse buttons:</b> whoever can open the delivery note, order or picking session.</li>
<li><b>Edit the recipient lists:</b> whoever can edit the shop's or organisation's settings.</li>
<li><b>Edit quick replies:</b> group admin.</li>
<li><b>Read other people's staff chats:</b> HR or organisation admin.</li>
</ul>
</aside>
