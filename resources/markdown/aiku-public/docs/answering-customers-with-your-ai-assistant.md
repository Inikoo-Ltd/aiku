---
title: Answering customers with your AI assistant
summary: Connect the AI assistant you already use to aiku and ask it for help with a chat. It reads the conversation with everything aiku knows (the customer, their orders, the products named, the shop's rules and the language to answer in) and saves a draft reply on the chat for you to check and send from aiku.
date: 2026-10-15
tags: crm, chat, ai
category: help-desk
series: Chat
order: 6
---

<aside class="tldr">
For customer service. Once an administrator switches it on for you, the AI assistant you connect to aiku can list the chats waiting for you and read one with everything aiku knows about it. Tell it what you want the answer to say, and it writes the reply in the customer's language and saves it on the chat as a <b>draft</b>. You read the draft in aiku, change what you want and press send yourself. Nothing reaches the customer until you do.
</aside>

## What it is for

The suggestions aiku writes on its own only get one try, with the facts picked for them beforehand. Your assistant can look further: it reads the whole conversation, the customer's orders, the products they name and the shop's rules, and it can ask you what you want to say. You stay in charge: you ask for help only when you want it, and you decide what goes out.

> *"Open the oldest chat waiting for me. The customer says two mugs arrived broken. Write a reply that says sorry and asks for a photo of each mug and the box."*

It works with any assistant that can connect to aiku. Talk to it in your own language; it answers the customer in theirs.

## Before you start

**1. Ask an administrator to switch it on.** On your user account, under <b>Access</b>, they turn on <b>Can connect AI assistant</b> and then <b>Can serve customers</b>.

**2. You need your usual chat permission on the shop.** The assistant only sees the chats of shops where you work chat, and only saves drafts on chats that are yours or not taken by anyone.

**3. Connect your assistant to aiku.** Add aiku as a connector in your assistant with the address `https://app.aiku.io/mcp/aiku` and sign in with your aiku account when asked. You do this once.

## How a conversation goes

1. **Ask what is waiting.** *"Which chats are waiting for me?"* The assistant lists the chats of your shops where the customer's last message has no answer yet, held by you or by nobody, the longest waiting first. Each has its Chat ID, the shop, who wrote and how long ago.
2. **Open one.** *"Open the first one."* You can also give it a Chat ID copied from the <b>Overview</b> panel of a chat.
3. **Say what the answer should say.** The assistant drafts it from the facts aiku gave it.
4. **It saves the draft on the chat.** Open the chat in aiku: the draft is in the AI box above the message field, marked with your name and the assistant you used. Press <b>Use</b> to put it in the message field, change what you want and send it. Or press <b>Discard</b>.

The assistant always writes a full reply. Where you would decide something, such as a credit or a replacement, it writes what your shop's staff usually decide, and the checklist under the draft marks that question "our usual decision, check". Change it if this case is different. It learns your shop's usual answers from the replies your team has sent before.

<aside class="tip">
Ask it to explain before it writes: <i>"What do we know about this order?"</i> Reading never changes anything.
</aside>

## What the assistant gets with each chat

| What | Why |
|---|---|
| **The conversation** | The latest 30 messages. The customer's words are treated as the customer's words, never as instructions to the assistant. |
| **The language to answer in** | aiku decides it from what the customer wrote. A draft in another language is refused. |
| **The customer** | Name, reference, balance and latest orders, when the chat is linked to a customer. |
| **What aiku looked up** | The orders and products the customer mentions, by code or in words, and the answers to common questions such as delivery costs or payment methods. Orders only for a customer aiku already knows. |
| **The shop's rules** | The shop's chat policy, its notes and guides in full, the policy pages that answer what the customer asks, and the titles of the rest. |
| **Earlier conversations** | Topic and summary of the customer's last few conversations. |

## What it can and cannot do

**It can** list your waiting chats, read a chat, and save a draft reply on it.

With the same switch it can also act on a chat for you, but each of these always shows you exactly what it will do first and only goes ahead after you say yes in your own words: send a reply, pick up, close or wait on a chat, link the customer, settle a claim, and change an order that is not packed yet. See [Talking to customers in Chat](/docs/customer-chat).

**It cannot** see chats of shops where you do not work chat, save a draft on a colleague's chat, or send anything without your yes.

## Good to know

- **The draft replaces any draft already waiting on that chat**, including one aiku wrote on its own.
- **Your manager sees how drafts are used.** Chat › AI shows how many drafts from staff assistants were sent as written, changed or discarded, per assistant.
- **What you share goes to your assistant's provider.** The conversation and the customer's details are sent to the company behind the assistant you connect. Being switched on means your administrator has agreed to that for you.
