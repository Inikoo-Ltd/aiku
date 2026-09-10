# WhatsApp

Two-way WhatsApp conversations in the chat inbox, and marketing campaigns sent to a chosen audience.

| Document | Read it if |
| --- | --- |
| [setup.md](setup.md) | You are connecting a shop to WhatsApp for the first time. |
| [sending-campaigns.md](sending-campaigns.md) | You are sending campaigns. Templates, audiences, and what the figures mean. |
| [troubleshooting.md](troubleshooting.md) | Something is not working. |

## Where to look in the app

- **Chat inbox** — `/org/{organisation}/shops/{shop}/chat/inbox`. Conversations, both directions.
- **WhatsApp templates** — `/org/{organisation}/shops/{shop}/chat/whatsapp-templates`. Write them,
  submit them to Meta, see the verdicts.
- **Campaigns** — `/org/{organisation}/shops/{shop}/marketing/whatsapp-campaigns`.
- **Subscribers** — the Subscribers tab on the campaigns page. Who has opted in.

## The one thing to know before anything else

The connection has two halves and they are set up on **two different pages**:

- The **Meta app** — access token, app id, app secret — belongs to the **organisation**.
- The **phone number** and **business account** belong to the **shop**.

A shop with its own half filled in and its organisation's half empty looks connected everywhere in
the app and then fails at the moment of sending. If something is mysteriously not working, check
both pages before anything else. [setup.md](setup.md) covers both.

## Which shops have it

WhatsApp is switched on per shop, not globally. A shop has it once somebody has turned on **Enable
WhatsApp Channel** in its settings and filled in both halves above. Until then the shop carries on
as if the feature did not exist.
