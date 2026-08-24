---
title: Staff chat for people holding a scanner
summary: We built an internal messenger into the ERP instead of sending the warehouse to a third-party app. Why it had to be separate from customer chat, why the first users were pickers on tablets, transparent translation between languages, chats that start from an order, and the rule that staff chat is work — so HR may read it.
date: 2026-08-23
tags: chat, warehouse, realtime, hr
---

<aside class="tldr"><strong>TL;DR</strong>aiku got its own internal messenger for warehouse and office staff, kept structurally separate from customer chat (own tables, routes, realtime channels), designed first for pickers on tablets, with automatic message translation, conversations that attach to an order or delivery note, presence that combines realtime and recent activity, and a written rule that staff chat is work communication HR may read, not private.</aside>

A warehouse runs on small questions. *Is there more of this in the back? The customer wants to add a line, have you picked it yet? Which box did the fragile one go in?* For years those questions travelled by shouting, by phone, and by a messaging app on personal phones that nobody in the office could see. In August 2026 we built a messenger into aiku itself. This is what we decided and why.

## Separate from customer chat, structurally

aiku already has a live chat for customers on the storefronts, with agents answering from the staff app. The tempting shortcut was to reuse it for staff talking to staff. We refused, and made the refusal structural: staff messaging has its own tables, its own routes under the staff app only, and its realtime channels are typed for staff users and nothing else. There is no code path by which a staff message could end up in front of a customer, because there is no shared code path at all. The conversation about *where* an order is must never be one bug away from the person who placed it.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Staff messaging has its own models: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Chat/StaffConversation.php">app/Models/Chat/StaffConversation.php</a> and <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Chat/StaffMessage.php">app/Models/Chat/StaffMessage.php</a>, separate from the customer-facing <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Chat/ChatSession.php">app/Models/Chat/ChatSession.php</a>.</li>
<li>Sending goes through <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Chat/Staff/SendStaffMessage.php">app/Actions/Chat/Staff/SendStaffMessage.php</a>; translation is queued per message by <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Chat/Staff/TranslateStaffMessage.php">app/Actions/Chat/Staff/TranslateStaffMessage.php</a>, storing each translation in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Chat/StaffMessageTranslation.php">app/Models/Chat/StaffMessageTranslation.php</a>.</li>
<li>Conversations can be archived and reopened via <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Chat/Staff/ArchiveStaffConversation.php">app/Actions/Chat/Staff/ArchiveStaffConversation.php</a>, dispatching <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Events/StaffConversationArchived.php">app/Events/StaffConversationArchived.php</a>.</li>
</ul></aside>

## The first users hold a scanner, not a mouse

The primary users are pickers, packers and stock controllers, on tablets, often with one hand. So the design rules were the warehouse's rules: on a small screen the chat is a full‑screen sheet; targets are big; there are quick‑reply chips for the ten things people actually say; pasting a photo or a screenshot is one gesture; a new message makes a sound because nobody is looking at the screen. The desktop experience — a right‑hand rail with presence dots, unread counts and your team — is the second audience, not the first.

## Translation you don't have to ask for

Our warehouses and offices do not share one language. Every message is queued for translation into each participant's language the moment it is sent; the original stays, and the translation appears underneath when it arrives. Nobody chooses a language, nobody presses a button. It reuses the same translation helper the product catalogue uses, so it cost us a job class and a table.

## Chats that start from the thing you are looking at

The most useful conversations are not "person to person" but "about this delivery note". So an order and a delivery note both carry buttons — *Ask warehouse*, *Ask CRM* — that open (or re‑open) a conversation keyed to that record and addressed to the right audience: the dispatch and stock roles for that warehouse, or the customer‑service roles for that shop. The record's reference is in the conversation title; a month later, the question about order 48213 is still attached to order 48213.

Audiences are derived from existing roles, not maintained by hand; "who is on shift in the Spanish warehouse" is already known to the system for a dozen other reasons.

## Presence that means something

"Online" is two signals together: the realtime presence channel says the tab is open, and a cache stamp written by the request middleware says they did something in the last fifteen minutes. Open‑but‑idle shows amber. This replaced an older trick — whispering "which page are you on" over the live channel — which drifted on tabs left open for days and made people look busy when they were at lunch.

## Work, not private

One decision caught people by surprise, so it is written down plainly in the system: **staff chat is work communication and is not private**. HR and organisation administrators can read it, from the HR section, scoped to their organisation. Sysadmins get analytics — who chats with whom, how much, when, about which records — but no message bodies. The analytics exist because chat is a signal: a warehouse and a customer‑service team who talk all day are probably papering over a process problem worth fixing.

We would rather say this up front than have someone discover it.

## What we did not build (yet)

Rooms, threads and floating chat heads are in the schema and not in the UI. Closing a chat archives it for you and it comes back on the next message. Nicknames, themes, emoji and a GIF picker arrived because people asked within a day. The rest waits until a few weeks of warehouse‑to‑office use tells us what is actually missing — a pattern we try to hold to for every module: ship the thing people will use on day one, and let day thirty write the next list.

<aside class="tldr bottom"><strong>In one paragraph</strong>Build staff messaging as its own structurally isolated system, design it first for the person holding a scanner, and say plainly that it is work communication, not private.</aside>
