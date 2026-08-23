---
title: The chat widget knows your order
summary: A live chat on every storefront, answered from the staff app by agents who see the customer's account, orders and history beside the conversation; messages translated both ways; a session that can become a ticket, a Slack thread or a summary; presence and typing over WebSockets; a dashboard of who is on the site right now and from where. And the tone rules we wrote down when an assistant started drafting replies.
date: 2026-07-15
tags: chat, customer-service, realtime, ux
---

<aside class="tldr"><strong>TL;DR</strong>aiku built its own storefront live chat because a third-party widget can't show the agent the customer's account, orders and history beside the conversation. A chat session starts anonymous and links to a customer on login; messages translate both ways over the same helper the catalogue uses; a session can become a ticket, a Slack thread or a summary. When an assistant started drafting agent replies, the team wrote down tone rules: warm and human, no scripted phrases, no bot tells, and hand over to a person the moment anyone asks.</aside>

In the corner of every storefront there is a small chat bubble. Behind it is a customer‑service agent in the staff application, and behind the agent is the whole system: the customer's account, their open orders, the delivery note that is sitting in *waiting*, the invoice they are asking about. That is the one thing a third‑party chat widget could never give us, and the reason we built our own. This note is how it works and what we decided along the way.

## A session, a visitor, an agent

A **chat session** begins when a visitor opens the widget: anonymous at first, with a guest profile (country from the address, language from the browser, the page they are on); linked to a customer the moment they log in or are recognised. Messages, attachments, typing and read receipts travel over WebSockets; an agent's presence is a channel too, so "someone is online" on the widget is true rather than decorative. If nobody is online the widget takes an **offline message** and the session waits.

**Agents** are staff users with a chat role, assigned to scopes — shops, languages, specialisations — so a Spanish‑speaking visitor on the Spanish storefront lands with an agent who can answer. Sessions can be **assigned**, reassigned, given a priority, marked as spam, closed and reopened; every one of those is an event on the session's timeline.

## The agent sees the customer, not just the chat

This is the point. Beside the conversation the agent has the **customer profile** — who they are, which shop, their balance, their flags — and a **timeline**: orders, invoices, delivery notes, previous chats, emails sent, in one list. "Where is my order" is answered by reading the delivery note's state from the same panel, with a link to it, and a note about the case goes on the order, not in a separate tool. The agent never asks the customer for an order number they could look up; they ask for the customer's name and the system does the rest.

## Translated both ways, quietly

A customer writes in Romanian; the agent reads it in English and answers in English; the customer reads Romanian. Translation is a queued job on every message, in both directions, using the same translation helper the product catalogue uses; the original is kept under the translation and a tap shows it. Agents are hired for what they know about the products, not for the number of languages they speak.

## A session can become other things

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>The core session model is [app/Models/Chat/ChatSession.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Chat/ChatSession.php), surfaced via [app/Http/Resources/CRM/Livechat/ChatSessionResource.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Http/Resources/CRM/Livechat/ChatSessionResource.php).</li>
<li>Turning a session into something else is its own action: [app/Actions/Chat/ChatSession/SummarizeChatSession.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Chat/ChatSession/SummarizeChatSession.php) and [app/Actions/Chat/ChatSession/ShareChatSessionToSlack.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Chat/ChatSession/ShareChatSessionToSlack.php).</li>
</ul></aside>

From a session, with the context pre‑filled: a **ticket** in the help project (the conversation, the customer, the order, the agent's chosen priority and labels); a **Slack thread** for a shop's channel when the team wants eyes on something; a **summary**, generated on demand, for the handover at the end of a shift or the note on the customer's record. Closing a session does not lose it — closed sessions are searchable, exportable, and appear on the customer's timeline for the next agent.

## The dashboard

For the customer‑service lead: active sessions, waiting sessions, who is answering what, response times; and a live view of **visitors on the site right now**, by country and by page, which turned out to be the most‑watched screen in the room. At group level, the same across every shop.

## The tone rules

This summer an AI assistant began drafting replies for agents to send, and that forced us to write down what a good reply sounds like — rules that had lived in people's heads:

- **Warm and human, not sterile.** The internal register (tickets, Slack) is impersonal on purpose; the customer chat is the opposite. Short, kind, first person.
- **No scripted call‑centre phrases.** No "I understand your frustration", no "is there anything else I can help you with today".
- **No bot tells.** No bullet lists in a chat bubble, no headings, no over‑eager exclamation marks.
- **If a customer asks whether they are talking to a bot, hand over to a person.** Immediately, without debate. The assistant drafts; a person sends; a person is always one tap away.
- **Never paste internal identifiers or internal notes.** The customer's reference, yes; our row ids, never.

The rules are served to the assistant from the same place the MCP tools are, so every client gets the same ones.

## What it replaced

A hosted widget that knew the visitor's name and nothing else, and a team that had to alt‑tab to find an order. The chat is not a feature on top of the system; it is a window into it, with a person on the other side.

<aside class="tldr bottom"><strong>In one paragraph</strong>The chat is not a feature bolted onto the system, it is a window into it — an agent who sees the whole customer record beside the conversation, with tone rules written down the moment a machine started drafting the words.</aside>
