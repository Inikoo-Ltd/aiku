# Troubleshooting

Symptom → cause → fix. Everything here is something you can check or change yourself, except where
it says to ask an engineer.

---

## Nothing arrives in the inbox when I message the number

**Cause.** Incoming messages are not reaching us, or are reaching us and being turned away.

Work through it in this order. Each step is a different cause with a different fix, and they are
ordered by how often they turn out to be the culprit.

1. **Is the webhook still verified in Meta?** WhatsApp → Configuration. If it shows an error there,
   the callback URL or the verify token is wrong.
2. **Is the `messages` field subscribed?** Verifying the webhook and subscribing to its fields are
   two separate steps in Meta, and it is easy to do the first and forget the second.
3. **Does the shop have the right Phone Number ID?** An incoming message is matched to a shop by
   that number. A wrong or blank one means the message arrives and goes nowhere.
4. **Does the organisation have an App Secret?** Without it every incoming message is turned away
   before it is read.

If all four look right, ask an engineer to check the logs — they can tell steps 3 and 4 apart, which
you cannot from the outside.

---

## Templates stay "pending" although Meta approved them

**Cause.** Meta's verdict is not reaching us, or cannot be matched to a shop.

**Fix.** Two things to check.

First, that **message_template_status_update** is subscribed in Meta. It is a separate tick from
`messages` and it is usually the one missing.

Second, that the shop's **WABA ID** is filled in. Template verdicts are matched to a shop by
business account rather than by phone number, so a shop can be receiving messages perfectly while
losing every template verdict. This is the one setting that only affects templates, which is what
makes the symptom so confusing.

In the meantime, **Refresh** on the template asks Meta for its current status directly and unsticks
it.

---

## "WhatsApp is not configured for this shop"

**Cause.** The shop has no Phone Number ID.

**Fix.** Shop settings → Chat → fill in the WhatsApp Connection fields.

This appears at the moment of sending rather than while you are composing, which is why a campaign
can look perfectly ready and then refuse at the last step. That is deliberate: shop settings are not
part of a campaign's readiness, or changing one shop setting would un-ready every campaign in the
shop at once.

---

## A campaign is stuck saying "Sending"

**Cause.** Some batch never finished. A campaign goes out in batches of 50, and the campaign only
says Sent once every batch is done.

**Fix.** Give it time first — a large audience takes a while, and delivery reports keep arriving
after the last message goes out. If it has not moved in an hour, ask an engineer: there are commands
to push the remaining batches and close the campaign, and it usually means the background workers
have stopped rather than anything being wrong with the campaign itself.

---

## A campaign went straight to "Stopped"

**Cause.** When its scheduled time arrived it could not be sent, so it ended rather than sitting
there pretending it was about to go out.

**Fix.** The campaign page says why. It will be one of: no template chosen, no recipients, or the
shop's WhatsApp connection is missing. Fix that and send a new campaign — a stopped one does not
resume.

---

## The audience is far smaller than the customer list

**Cause.** Usually not a fault at all.

By default a campaign goes only to people who **opted in** to the WhatsApp newsletter, which is a
small fraction of customers to begin with and grows over time. On top of that, numbers WhatsApp
cannot deliver to — no country code, an email address typed into a phone field — are dropped
without being counted.

**Fix.** If you genuinely want a wider audience, add the Contacted or Customers groups when
composing. Read the warning in [sending-campaigns.md](sending-campaigns.md) before you do.

---

## Lots of messages failed with "Missing" something

**Cause.** The template has a personalised blank in it, and those customers have no value to put in
it. They were skipped rather than sent a message with a hole in it.

**Fix.** Either fill in the missing field on those customer records, or use a template whose blanks
every recipient can answer. There is no way to send a template with an empty blank — WhatsApp
rejects it outright.

---

## A template does not appear in the campaign picker

**Cause.** It is not approved yet, or it is not in the Marketing category.

**Fix.** Both are required. The category is chosen when the template is created and cannot be
changed afterwards, so a Utility template has to be rewritten as a Marketing one and submitted
again.

---

## Everything stopped working at once, and nobody changed anything

**Cause.** Nine times out of ten, an expired access token.

**Fix.** Meta hands out a temporary 24-hour token by default, and it is easy to save one during
setup by mistake. Generate a permanent one for a system user and paste it into organisation settings
→ Meta-configuration → Access Key. [setup.md](setup.md) has the detail.

The other candidates, in order of likelihood: the system user that owned the token was deleted, the
number was limited by Meta after reports or blocks, or the app was switched back into development
mode.
