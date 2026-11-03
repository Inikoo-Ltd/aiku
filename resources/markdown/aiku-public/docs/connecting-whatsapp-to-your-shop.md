---
title: Connecting WhatsApp to your shop
summary: What to collect from Meta, which of the two settings pages each value goes on, and how to check the connection works before you rely on it.
date: 2026-11-03
tags: marketing, whatsapp, shop
category: help-desk
series: WhatsApp
order: 1
---

<aside class="tldr">
WhatsApp needs settings in <b>two places</b>, and this is what catches almost everyone out. The Meta app — access key, app id, app secret — goes on the <b>organisation's</b> settings page. The phone number and business account go on the <b>shop's</b> settings page. A shop with only its own half filled in looks connected everywhere in aiku and then fails at the moment it tries to send. Fill in both, then check the three things at the end of this page. And if the number is in use on the <b>WhatsApp Business app</b> on a phone, do not delete it from the app: connect it with <b>Connect WhatsApp Business app</b> instead, as described below.
</aside>

## What you are connecting

WhatsApp is not one account. It is a **Meta app**, which the organisation owns, plus a **WhatsApp Business Account** — everyone calls it a WABA — and a **phone number** under it, which belong to the shop.

Collect these five values from the Meta dashboard before you start. Everything after that is filling in two forms.

| Value | Where it is in Meta |
| --- | --- |
| App ID | App dashboard, at the top |
| App secret | App dashboard → Settings → Basic |
| Access token | Business settings → System users → Generate token |
| WABA ID | WhatsApp → API Setup |
| Phone number ID | WhatsApp → API Setup, under the number |

**The access token must be a permanent one**, generated for a system user. Meta offers a temporary token first and it expires after 24 hours — everything stops working the next day with no warning and nothing obvious to point at. This is the most common setup mistake by a wide margin.

## A number already on the WhatsApp Business app

The normal setup only takes a number that is not on the WhatsApp Business app, so the usual advice is to delete the app account first. **Do not.** Deleting it closes the number's old WhatsApp registration for good, and every customer who ever chatted with it then sees "This person is no longer on WhatsApp" with no box to type in. They only get through again once they delete and re-save the contact, write from WhatsApp Web, or receive a message from you first. That happened to shops that were moved this way.

A number on the app is connected with **Connect WhatsApp Business app** instead, which Meta calls Coexistence. The number stays on the app on the shop phone, and aiku reads and writes it too:

- **Replies typed on the phone appear in aiku** as replies from the shop, in the customer's conversation, so a chat answered on the phone does not wait in the inbox as unanswered. They do not reopen a closed conversation.
- **The chat history comes across once.** During the signup the phone asks whether to share it. If you agree, up to six months of one-to-one chats are copied in as closed conversations, already read. Pictures and files in it arrive as a note saying something was shared, not as the file. Group chats are never copied.
- **Calls ring on the phone only.** WhatsApp calls to the number cannot be answered in aiku.
- **Open the WhatsApp Business app on that phone at least every 13 days.** Otherwise Meta disconnects the number from aiku.
- **Campaigns send a little slower.** Meta allows such a number 20 messages a second.

Only a number that is on the app right now can be connected this way. A number already moved to the normal setup cannot be switched back.

To connect one:

1. The organisation's **Meta-configuration** needs the **Embedded Signup configuration ID** as well as the App ID and App Secret. Ask your developer: it is created in the Meta app dashboard.
2. In the shop's settings, under **Chat**, switch on **Enable WhatsApp Channel**. Under **Phone Number Status**, press **Connect WhatsApp Business app**.
3. Meta's window opens. Choose the business, then pick the option to connect the WhatsApp Business app number. It shows a QR code: scan it with the WhatsApp Business app on the shop phone, and agree to share the chat history if you want it.
4. When the window closes, aiku fills in the Phone Number ID, WABA ID and Phone Number itself, and the status shows **Coexistence**. There is nothing to verify or register: the number already is.

Do it in one go. The chat history can only be asked for once, within a day of connecting, and aiku asks for it as part of the connection.

## Telling Meta where to send messages

In the Meta app dashboard, under **WhatsApp → Configuration**, set the callback address to your aiku domain followed by `/webhooks/whatsapp`, and a verify token. The verify token has to match a setting on aiku's side, so **ask your developer for it first** and paste in what they give you — it is a settings change for them, not a code change.

Press **Verify and save**. Meta calls aiku immediately, so if it complains, either the address or the token is wrong.

Then open **Manage** next to the webhook fields and subscribe to exactly two:

- **messages** — everything a customer sends you, plus delivery and read receipts.
- **message_template_status_update** — Meta's verdict when it finishes reviewing a template.

Subscribing to fewer breaks something specific: without the first you receive no messages at all, and without the second your templates sit saying "pending" forever even after Meta has approved them.

If any shop on the organisation's Meta app uses a WhatsApp Business app number, also subscribe to **smb_message_echoes** (replies typed on the phone), **history** (the chats copied in at signup) and **smb_app_state_sync** (the phone's contacts, which aiku does not use). Shops on the normal setup are not affected by them.

## The organisation half

Open your **organisation's settings** and find **Meta-configuration**.

- **Access Key** — the access token. It signs everything aiku sends. Nothing goes out without it.
- **App ID** — used when uploading the sample image a template is reviewed against.
- **App Secret** — proves an incoming message really came from Meta rather than from somebody pretending.

Every organisation uses its own Meta app, so there is nothing to fall back on. Blank here means blank: aiku will not borrow another organisation's settings.

## The shop half

Open the **shop's settings** and go to **Chat**. Switch on **Enable WhatsApp Channel**, which reveals three more fields.

- **Phone Number ID** — which number you send from, and how aiku knows an incoming message belongs to this shop.
- **WABA ID** — needed for anything to do with templates.
- **Phone Number** — the number itself, written the way a customer would see it. This one is for display only; it appears in the campaign preview.

## Checking it worked

Three checks, in this order. Each one depends on the one before, so a failure tells you where to look.

1. **Meta accepted the webhook.** It said so when you pressed Verify and save.
2. **Messages arrive.** Send a WhatsApp message to the number from your own phone. Within a few seconds the conversation should appear in the shop's chat inbox. If nothing appears, incoming messages are not reaching aiku — see [when WhatsApp is not working](/docs/when-whatsapp-is-not-working).
3. **Templates sync.** Open **Chat → WhatsApp templates** and press **Sync**. Anything already approved in Meta should appear. If the button errors, the WABA ID or the access token is wrong.

Once all three pass, the shop can hold conversations and send campaigns.

## Before you send anything

**Templates are not optional.** WhatsApp does not allow a business to open a conversation with free text. Every campaign, and every reply sent more than 24 hours after the customer last wrote to you, has to use a template Meta approved beforehand. Write and submit them from **Chat → WhatsApp templates**, and expect the review to take anywhere from a few minutes to a day.

**A template for campaigns must be in the Marketing category.** Templates in the Utility or Authentication categories are perfectly usable in conversations but will never appear in the campaign picker. Meta decides this, not aiku, and the category cannot be changed after a template is created.

**Having a phone number is not permission to market to someone.** Customers opt in to the WhatsApp newsletter when they register and at checkout, and by default campaigns go only to the people who did. [Sending a WhatsApp campaign](/docs/sending-a-whatsapp-campaign) covers the choice.

## Worth writing down somewhere

- Which Meta system user the access token belongs to. When somebody eventually deletes that user, WhatsApp stops and nobody remembers why.
- Who in the business approves a template's wording before it goes to Meta. A rejected template is a slow round trip.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>The organisation half:</b> your organisation → <b>Settings</b> → <b>Meta-configuration</b> → Access Key, App ID, App Secret.</li>
<li><b>The shop half:</b> your shop → <b>Settings</b> → <b>Chat</b> → switch on <b>Enable WhatsApp Channel</b>, then Phone Number ID, WABA ID and Phone Number.</li>
<li><b>A number on the WhatsApp Business app:</b> your organisation → <b>Settings</b> → <b>Meta-configuration</b> → Embedded Signup configuration ID; then your shop → <b>Settings</b> → <b>Chat</b> → <b>Phone Number Status</b> → <b>Connect WhatsApp Business app</b>.</li>
<li><b>Check messages arrive:</b> your shop → <b>Chat → Inbox</b>, after messaging the number from your phone.</li>
<li><b>Check templates:</b> your shop → <b>Chat → WhatsApp templates</b> → <b>Sync</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Organisation settings and shop settings are both administrator screens. If you cannot see <b>Meta-configuration</b> on the organisation, you do not have the rights to complete this and will need somebody who does.</li>
</ul>
</aside>
