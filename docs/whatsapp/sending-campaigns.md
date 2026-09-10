# Sending a WhatsApp campaign

For marketing. No technical knowledge assumed.

---

## What a campaign is

A message sent to many people at once, through WhatsApp, from the shop's own number. It lands in the
same conversation the customer would use to reply — so if they answer, the answer arrives in the
chat inbox and an agent can pick it up. A campaign is not a broadcast into the void; expect replies
and be ready to answer them.

## Why you cannot just write a message

WhatsApp does not let a business start a conversation with free text. **Everything a campaign sends
must be a template Meta approved in advance.** You write the template, submit it, and Meta reviews
it — usually quickly, sometimes overnight. Only then can a campaign use it.

Two rules that catch people out:

- A template must be in the **Marketing** category to appear in the campaign picker. Utility and
  Authentication templates are fine for conversations but will not show up here.
- Once approved, the wording is fixed. Changing the message means a new template and another review.

Write templates on the WhatsApp templates page, under Chat.

---

## The four steps

Every campaign goes through the same short journey, and the page shows you where you are.

1. **Create** — press Campaign on the campaigns page. You get an empty campaign with a default name
   you can change.
2. **Compose** — pick the template, then pick who it goes to.
3. **Preview & send** — see the message as the customer will see it, with real values filled in.
4. **Send now, or schedule** — for a date and time in the future.

You can edit a campaign freely until it is scheduled or sent. Once it is scheduled, the content is
frozen: cancel the schedule first if you want to change something. This is deliberate — the audience
you chose is the audience it goes to.

## Choosing who gets it

Three groups, which you can combine:

| Group | Who that means |
| --- | --- |
| **Subscribers** *(on by default)* | Customers who opted in to the WhatsApp newsletter |
| **Contacted** | Anyone who has ever messaged the shop on WhatsApp — including people who are not customers |
| **Customers** | Customers with a usable phone number, whether they opted in or not |

On top of the groups you can add the usual customer filters, and the count updates as you change
them.

**Think before using Customers.** Having someone's phone number is not the same as their permission
to market to them. Subscribers is the default for a reason, and in most places sending marketing to
people who did not opt in is both a legal problem and the fastest way to get a number reported and
blocked by Meta.

### Why the audience is smaller than you expected

Two filters run whether you ask for them or not:

- **Opt-in.** With the default group, only people who ticked the WhatsApp newsletter box count. That
  will be a small fraction of the customer list at first, and grows as people register and check
  out.
- **Deliverable numbers.** A number WhatsApp cannot route is dropped: no country code, an email
  address typed into a phone field, a landline written as a mobile. These are not attempted and not
  counted.

So the number of recipients will usually be well below the number of customers. That is the system
being honest about who it can actually reach.

## Personalising the message

A template can carry blanks — the customer's first name, say — that get filled in per person. You
map each blank to a customer field when you compose, and the preview shows real values so you can
see what it will look like.

**If a value is missing for someone, they are skipped rather than sent a broken message.** WhatsApp
rejects a message with an empty blank in it, and a message reading "Hi ," would be worse than none.
Those contacts show up afterwards as failed, saying which value was missing — usually a sign a
customer record is incomplete rather than anything being wrong with the campaign.

---

## Sending, and what happens next

**Send now** starts it going out immediately, in batches of 50. A large audience takes a while; the
page updates as it goes.

**Schedule** holds it until the time you set. The system checks every minute for campaigns that have
come due.

A scheduled campaign is re-checked at the moment it fires, not just when you scheduled it. If
something has changed in the meantime — the shop's WhatsApp connection was removed, the template was
deleted — the campaign stops instead of sending, and says why on its own page. It does not sit
there pretending it is about to send.

### What the states mean

| State | What it means for you |
| --- | --- |
| In process | Being written. Nothing has gone anywhere. |
| Ready | Has a template and an audience. Ready to send or schedule. |
| Scheduled | Waiting for its time. Cancel the schedule to edit it again. |
| Sending | Going out now. |
| Sent | Everything has been sent. |
| Cancelled | The schedule was cancelled before it fired. |
| Stopped | It ended without sending. The campaign page says why. |

---

## Reading the figures

Four numbers per campaign, which come from WhatsApp itself as it delivers:

- **Sent** — we handed it to WhatsApp.
- **Delivered** — it reached the customer's phone.
- **Read** — they opened it. The blue ticks. Customers can switch read receipts off, so this is a
  floor, not a truth.
- **Failed** — it did not go. Either WhatsApp refused it, or we skipped the person because a
  personalisation value was missing.

**Clicked is always 0.** Click tracking through WhatsApp does not exist in Aiku yet. The box is
there and it will stay at zero — that is not a fault and not a sign nobody clicked.

Delivery reports arrive over minutes, sometimes longer, so the numbers keep moving after a campaign
says Sent. Check back rather than judging a campaign the moment it finishes.

## What this cannot tell you

- **Whether anyone clicked a link.** See above.
- **Whether it sold anything.** WhatsApp campaigns are not yet connected to the marketing
  attribution system, so revenue is not credited to them the way it is for newsletters.
- **Why a number failed**, beyond WhatsApp's own reason, which is sometimes just "invalid".
- **Anything about people you never reached** — the ones filtered out for having no opt-in or an
  unusable number are simply absent from the figures.

## Rules of thumb

- Send to Subscribers unless you have a specific, defensible reason not to.
- Keep the first campaign small. Meta watches new numbers closely and a burst of blocks or reports
  early on can get a number limited.
- Expect replies, and make sure somebody is watching the inbox for a few hours afterwards.
- A high Delivered but low Read is normal. A high Failed is worth investigating — start with
  [troubleshooting.md](troubleshooting.md).
- Do not compare Read here to Opened in an email campaign. They are different mechanisms measuring
  different things.
