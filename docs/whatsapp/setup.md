# Connecting a shop to WhatsApp

For whoever sets it up. You will need access to the Meta app dashboard and to Aiku's organisation
and shop settings. One short step needs an engineer; it is marked.

---

## What you are connecting

WhatsApp is not one account. It is a **Meta app** (which the organisation owns), a **WhatsApp
Business Account** — everyone says WABA — and a **phone number** under it (which belong to the
shop). Aiku needs all three, and they are entered on two different pages.

Get these five values together before you start. Everything else is filling in forms.

| Value | Where it is in Meta | Looks like |
| --- | --- | --- |
| App ID | App dashboard, top of the page | a long number |
| App secret | App dashboard → Settings → Basic | a long letters-and-numbers string |
| Access token | Business settings → System users → Generate token | a very long string starting `EAA` |
| WABA ID | WhatsApp → API Setup | a long number |
| Phone number ID | WhatsApp → API Setup, under the number | a long number |

**The access token must be a permanent one**, generated for a system user. The dashboard offers a
temporary token first and it expires in 24 hours — everything stops working the next day, with no
warning and no obvious cause. This is the most common setup mistake by a distance.

---

## Step 1 — Point Meta at us

In the Meta app dashboard, under WhatsApp → Configuration:

- **Callback URL**: `https://{your Aiku domain}/webhooks/whatsapp`
- **Verify token**: a password you make up. It has to match a setting on our side, so **ask an
  engineer to set it first** and use what they give you — it is a config change, not a code change.

Press **Verify and save**. Meta calls us straight away; if it complains, the token does not match or
the domain is wrong.

Then **Manage** the webhook fields and subscribe to exactly two:

- **messages** — everything a customer sends you, plus delivery and read receipts.
- **message_template_status_update** — Meta's verdict when it finishes reviewing a template.

Subscribing to more does nothing useful. Subscribing to fewer breaks something: without the first
you receive no messages, without the second your templates stay stuck saying "pending" forever even
after Meta has approved them.

## Step 2 — The organisation half

**Organisation settings → Meta-configuration.** Three fields:

| Field | Paste in | What it does |
| --- | --- | --- |
| Access Key | the access token | Signs everything we send. Nothing sends without it. |
| App ID | the app id | Used when uploading the sample image a template is reviewed against. |
| App Secret | the app secret | Proves an incoming message really came from Meta rather than someone pretending. |

Every organisation uses its own Meta app, so there is no shared fallback. Blank here means blank —
the system does not borrow another organisation's settings.

## Step 3 — The shop half

**Shop settings → Chat.** Turn on **Enable WhatsApp Channel**, which reveals three more fields:

| Field | Paste in | What it does |
| --- | --- | --- |
| Phone Number ID | the phone number id | Which number we send from, and how we know an incoming message belongs to this shop. |
| WABA ID | the WABA id | Needed for anything to do with templates. |
| Phone Number | the number itself, e.g. +44 7700 900123 | Shown in the campaign preview. Display only. |

## Step 4 — Check it worked

Three checks, in this order. Each one tests something the next depends on.

1. **Meta accepted the webhook.** It said so when you pressed Verify and save.
2. **Messages arrive.** Send a WhatsApp message to the number from your own phone. Within a few
   seconds a conversation should appear in the chat inbox. If nothing appears, incoming messages are
   not reaching us — [troubleshooting.md](troubleshooting.md) has the causes.
3. **Templates sync.** Open WhatsApp templates and press **Sync**. Any template already approved in
   Meta should appear in the list. If the button errors, the WABA ID or the access token is wrong.

Once all three pass, the shop can hold conversations and send campaigns.

---

## After setup

**Templates are not optional.** WhatsApp does not allow a business to open a conversation with free
text. Every campaign, and every reply sent more than 24 hours after the customer last wrote, must
use a template Meta approved in advance. Write and submit them from the WhatsApp templates page, and
expect review to take anywhere from minutes to a day.

**A template for campaigns must be in the Marketing category.** Templates categorised as Utility or
Authentication are approved and usable in conversations, but they will not appear in the campaign
picker. Meta enforces this, not us.

**Opt-in is separate from having a phone number.** Customers subscribe to the WhatsApp newsletter at
registration and at checkout. Having someone's number is not permission to market to them, and by
default a campaign goes only to people who opted in. See
[sending-campaigns.md](sending-campaigns.md).

## Things worth writing down somewhere

- Which system user the access token belongs to. When someone deletes that user, WhatsApp stops and
  nobody remembers why.
- Who in the business can approve a template's wording before it goes to Meta. A rejected template
  is a slow round trip.
